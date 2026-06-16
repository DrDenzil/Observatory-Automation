package main

import (
	"context"
	"encoding/json"
	"net/http"
	"strconv"
	"time"
)

// Server exposes the runner's own REST API (the one the blueprint specifies the
// web interface can call directly: /health, /status, /logs, /jobs, /trigger).
type Server struct {
	agent *Agent
}

func NewServer(agent *Agent) *Server { return &Server{agent: agent} }

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}

func (s *Server) routes() *http.ServeMux {
	mux := http.NewServeMux()
	mux.HandleFunc("/health", s.handleHealth)
	mux.HandleFunc("/status", s.handleStatus)
	mux.HandleFunc("/logs", s.handleLogs)
	mux.HandleFunc("/jobs", s.handleJobs)
	mux.HandleFunc("/trigger", s.handleTrigger)
	return mux
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{
		"status":         "ok",
		"uptime_seconds": s.agent.uptimeSeconds(),
	})
}

func (s *Server) handleStatus(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, s.agent.snapshot())
}

func (s *Server) handleLogs(w http.ResponseWriter, r *http.Request) {
	limit := 100
	if v := r.URL.Query().Get("limit"); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			limit = n
		}
	}
	level := r.URL.Query().Get("level")
	if level == "" {
		level = "debug"
	}
	writeJSON(w, http.StatusOK, s.agent.log.Recent(limit, level))
}

func (s *Server) handleJobs(w http.ResponseWriter, r *http.Request) {
	snap := s.agent.snapshot()
	current := any(nil)
	if snap.CurrentJob != "" {
		current = map[string]any{
			"job_id":   snap.CurrentJob,
			"state":    snap.State,
			"progress": snap.Progress,
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"current":   current,
		"completed": s.agent.history(),
	})
}

func (s *Server) handleTrigger(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"error": "POST required"})
		return
	}
	select {
	case s.agent.trigger <- struct{}{}:
	default: // a trigger is already pending
	}
	writeJSON(w, http.StatusOK, map[string]any{"status": "triggered"})
}

func (s *Server) Run(ctx context.Context, addr string) error {
	srv := &http.Server{Addr: addr, Handler: s.routes()}
	go func() {
		<-ctx.Done()
		shutdownCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = srv.Shutdown(shutdownCtx)
	}()
	if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		return err
	}
	return nil
}
