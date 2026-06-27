package main

import (
	"bufio"
	"bytes"
	"context"
	"fmt"
	"net/http"
	"os"
	"os/exec"
	"strconv"
	"sync"
	"time"
)

const mjpegBoundary = "mjpegframe"

// streamBcast distributes JPEG frames from a single ffmpeg process to N HTTP viewers.
type streamBcast struct {
	mu     sync.Mutex
	subs   map[chan []byte]struct{}
	cancel context.CancelFunc
	latest []byte // most recent frame, used by snapshot
}

func newStreamBcast(cancel context.CancelFunc) *streamBcast {
	return &streamBcast{subs: make(map[chan []byte]struct{}), cancel: cancel}
}

func (sb *streamBcast) lastFrame() []byte {
	sb.mu.Lock()
	defer sb.mu.Unlock()
	return sb.latest
}

func (sb *streamBcast) subscribe() chan []byte {
	ch := make(chan []byte, 10)
	sb.mu.Lock()
	sb.subs[ch] = struct{}{}
	sb.mu.Unlock()
	return ch
}

func (sb *streamBcast) unsubscribe(ch chan []byte) int {
	sb.mu.Lock()
	delete(sb.subs, ch)
	n := len(sb.subs)
	sb.mu.Unlock()
	return n
}

func (sb *streamBcast) send(frame []byte) {
	sb.mu.Lock()
	sb.latest = frame
	for ch := range sb.subs {
		select {
		case ch <- frame:
		default: // slow consumer — drop frame rather than block
		}
	}
	sb.mu.Unlock()
}

// closeAll signals every subscriber that the stream has ended.
func (sb *streamBcast) closeAll() {
	sb.mu.Lock()
	for ch := range sb.subs {
		close(ch)
	}
	sb.subs = make(map[chan []byte]struct{})
	sb.mu.Unlock()
}

// WebcamServer serves an on-demand MJPEG stream from a V4L2 device.
// A single ffmpeg process is shared across all connected viewers — the device
// is opened only when someone is watching and released when the last viewer leaves.
type WebcamServer struct {
	cfg     *Config
	log     *Logger
	avail   bool
	viewers int32 // updated under bcastMu
	arduino *ArduinoController

	bcastMu sync.Mutex
	bcast   *streamBcast // nil when no stream is active
}

func NewWebcamServer(cfg *Config, log *Logger, arduino *ArduinoController) *WebcamServer {
	_, err := os.Stat(cfg.Webcam.Device)
	avail := err == nil
	if avail {
		log.Info("webcam", "device found", map[string]any{"device": cfg.Webcam.Device, "port": cfg.Webcam.Port})
	}
	return &WebcamServer{cfg: cfg, log: log, avail: avail, arduino: arduino}
}

func (ws *WebcamServer) Available() bool { return ws.avail }
func (ws *WebcamServer) Viewers() int {
	ws.bcastMu.Lock()
	defer ws.bcastMu.Unlock()
	if ws.bcast == nil {
		return 0
	}
	ws.bcast.mu.Lock()
	defer ws.bcast.mu.Unlock()
	return len(ws.bcast.subs)
}

// Start launches the HTTP server. No-op if no device was found.
func (ws *WebcamServer) Start(ctx context.Context) {
	if !ws.avail {
		return
	}
	mux := http.NewServeMux()
	mux.HandleFunc("/webcam/stream", ws.handleStream)
	mux.HandleFunc("/webcam/snapshot", ws.handleSnapshot)
	mux.HandleFunc("/webcam/status", ws.handleStatus)
	mux.HandleFunc("/arduino/ir", ws.handleIR)

	srv := &http.Server{Addr: fmt.Sprintf(":%d", ws.cfg.Webcam.Port), Handler: mux}

	go func() {
		ws.log.Info("webcam", "server listening", map[string]any{"addr": srv.Addr})
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			ws.log.Warn("webcam", "server error", map[string]any{"err": err.Error()})
		}
	}()
	go func() {
		<-ctx.Done()
		srv.Close()
	}()
}

// handleStream serves a multipart/x-mixed-replace MJPEG stream.
// The first viewer starts a shared ffmpeg process; subsequent viewers join the
// same stream. ffmpeg is stopped when the last viewer disconnects.
func (ws *WebcamServer) handleStream(w http.ResponseWriter, r *http.Request) {
	// Subscribe — start ffmpeg if this is the first viewer.
	ws.bcastMu.Lock()
	if ws.bcast == nil {
		ctx, cancel := context.WithCancel(context.Background())
		ws.bcast = newStreamBcast(cancel)
		go ws.runFFmpeg(ctx, ws.bcast)
	}
	ch := ws.bcast.subscribe()
	ws.bcastMu.Unlock()

	defer func() {
		ws.bcastMu.Lock()
		if ws.bcast != nil {
			remaining := ws.bcast.unsubscribe(ch)
			if remaining == 0 {
				ws.bcast.cancel()
				ws.bcast = nil
			}
		}
		ws.bcastMu.Unlock()
	}()

	// Wait for the first frame (up to 10s) before committing the 200 response.
	// This lets us return a proper 503 if ffmpeg fails to open the device.
	var firstFrame []byte
	select {
	case <-r.Context().Done():
		return
	case <-time.After(10 * time.Second):
		http.Error(w, "webcam unavailable — device may be in use by telescope software", http.StatusServiceUnavailable)
		return
	case frame, ok := <-ch:
		if !ok {
			http.Error(w, "webcam unavailable — ffmpeg failed to start", http.StatusServiceUnavailable)
			return
		}
		firstFrame = frame
	}

	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "streaming unsupported", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "multipart/x-mixed-replace; boundary="+mjpegBoundary)
	w.Header().Set("Cache-Control", "no-cache, no-store, must-revalidate")
	w.Header().Set("Connection", "close")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	writeMJPEGFrame(w, flusher, firstFrame)

	for {
		select {
		case <-r.Context().Done():
			return
		case frame, ok := <-ch:
			if !ok {
				return
			}
			writeMJPEGFrame(w, flusher, frame)
		}
	}
}

func writeMJPEGFrame(w http.ResponseWriter, flusher http.Flusher, frame []byte) {
	fmt.Fprintf(w, "--%s\r\nContent-Type: image/jpeg\r\nContent-Length: %d\r\n\r\n",
		mjpegBoundary, len(frame))
	w.Write(frame) //nolint:errcheck
	fmt.Fprintf(w, "\r\n")
	flusher.Flush()
}

// runFFmpeg runs a single ffmpeg process and broadcasts its frames to all subscribers.
func (ws *WebcamServer) runFFmpeg(ctx context.Context, bcast *streamBcast) {
	c := ws.cfg.Webcam
	cmd := exec.CommandContext(ctx, "ffmpeg",
		"-f", "v4l2",
		"-framerate", fmt.Sprintf("%d", c.Framerate),
		"-video_size", fmt.Sprintf("%dx%d", c.Width, c.Height),
		"-i", c.Device,
		"-vf", fmt.Sprintf("fps=%d", c.StreamFPS),
		"-f", "image2pipe",
		"-vcodec", "mjpeg",
		"-q:v", fmt.Sprintf("%d", c.Quality),
		"pipe:1",
	)
	cmd.Stderr = nil

	stdout, err := cmd.StdoutPipe()
	if err != nil {
		ws.log.Warn("webcam", "ffmpeg pipe error", map[string]any{"err": err.Error()})
		bcast.closeAll()
		return
	}
	if err := cmd.Start(); err != nil {
		ws.log.Warn("webcam", "ffmpeg start failed (device may be in use by INDI)", map[string]any{"err": err.Error()})
		bcast.closeAll()
		return
	}
	defer func() {
		cmd.Process.Kill() //nolint:errcheck
		cmd.Wait()         //nolint:errcheck
		bcast.closeAll()
	}()

	ws.log.Info("webcam", "ffmpeg started", map[string]any{"device": c.Device})
	broadcastFrames(bcast, bufio.NewReaderSize(stdout, 65536))
	ws.log.Info("webcam", "ffmpeg stopped", nil)
}

// broadcastFrames parses raw JPEG frames from ffmpeg's image2pipe output and
// broadcasts each complete frame to all subscribers.
func broadcastFrames(bcast *streamBcast, r *bufio.Reader) {
	var buf bytes.Buffer
	inFrame := false
	var prev byte

	for {
		b, err := r.ReadByte()
		if err != nil {
			return
		}

		if !inFrame {
			if prev == 0xFF && b == 0xD8 {
				inFrame = true
				buf.Reset()
				buf.WriteByte(0xFF)
				buf.WriteByte(0xD8)
			}
		} else {
			buf.WriteByte(b)
			if prev == 0xFF && b == 0xD9 {
				frame := make([]byte, buf.Len())
				copy(frame, buf.Bytes())
				bcast.send(frame)
				inFrame = false
				buf.Reset()
			}
		}
		prev = b
	}
}

// handleSnapshot returns a single JPEG frame.
// If a stream is active the latest broadcasted frame is returned immediately
// (no second ffmpeg, no device conflict). Otherwise a short-lived ffmpeg grabs one frame.
func (ws *WebcamServer) handleSnapshot(w http.ResponseWriter, r *http.Request) {
	ws.bcastMu.Lock()
	bcast := ws.bcast
	ws.bcastMu.Unlock()

	var data []byte
	if bcast != nil {
		data = bcast.lastFrame()
	}

	if len(data) == 0 {
		// No active stream — spin up a brief ffmpeg to grab a single frame.
		ctx, cancel := context.WithCancel(r.Context())
		defer cancel()
		c := ws.cfg.Webcam
		cmd := exec.CommandContext(ctx, "ffmpeg",
			"-f", "v4l2",
			"-i", c.Device,
			"-frames:v", "1",
			"-f", "image2",
			"-vcodec", "mjpeg",
			"pipe:1",
		)
		cmd.Stderr = nil
		var err error
		data, err = cmd.Output()
		if err != nil {
			http.Error(w, "snapshot failed — device may be in use", http.StatusServiceUnavailable)
			return
		}
	}

	w.Header().Set("Content-Type", "image/jpeg")
	w.Header().Set("Content-Length", fmt.Sprintf("%d", len(data)))
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Write(data) //nolint:errcheck
}

// handleStatus returns a quick JSON health check.
func (ws *WebcamServer) handleStatus(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	irAvail := ws.arduino != nil && ws.arduino.Available()
	irLevel := -1
	if irAvail {
		irLevel = ws.arduino.IRLevel()
	}
	fmt.Fprintf(w, `{"available":true,"viewers":%d,"ir_available":%v,"ir_level":%d}`,
		ws.Viewers(), irAvail, irLevel)
}

// handleIR sets the IR LED brightness. POST /arduino/ir?level=0..9
func (ws *WebcamServer) handleIR(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "POST required", http.StatusMethodNotAllowed)
		return
	}
	if ws.arduino == nil || !ws.arduino.Available() {
		http.Error(w, "arduino not available", http.StatusServiceUnavailable)
		return
	}
	level, err := strconv.Atoi(r.URL.Query().Get("level"))
	if err != nil || level < 0 || level > 9 {
		http.Error(w, "level must be 0-9", http.StatusBadRequest)
		return
	}
	if err := ws.arduino.SetIR(level); err != nil {
		http.Error(w, "serial write failed: "+err.Error(), http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	fmt.Fprintf(w, `{"ir_level":%d}`, level)
}
