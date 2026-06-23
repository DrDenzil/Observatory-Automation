package main

import (
	"sync"
	"time"
)

type State string

const (
	StateIdle        State = "idle"
	StateFetching    State = "fetching"
	StateProcessing  State = "processing"
	StateExecuting   State = "executing"
	StateUploading   State = "uploading"
	StateFailed      State = "failed"
	StateWeatherHold State = "weather_hold"
)

type Progress struct {
	Step        string `json:"step"`
	Description string `json:"step_description"`
	Elapsed     int    `json:"elapsed_seconds"`
	EstTotal    int    `json:"estimated_total_seconds"`
}

type Hardware struct {
	KStars  bool `json:"kstars_running"`
	INDI    bool `json:"indi_running"`
	Network bool `json:"network_connected"`
}

type Weather struct {
	Safe    *bool  `json:"safe"`    // nil = not yet checked
	Message string `json:"message"`
}

type Status struct {
	MachineID    string    `json:"machine_id"`
	State        State     `json:"state"`
	CurrentJob   string    `json:"current_job"`
	Progress     Progress  `json:"progress"`
	Hardware     Hardware  `json:"hardware"`
	Weather      Weather   `json:"weather"`
	LastActivity time.Time `json:"last_activity"`
}

// JobRecord is a completed (or failed) job kept for the /jobs endpoint.
type JobRecord struct {
	JobID    string    `json:"job_id"`
	QueueRef string    `json:"queue_ref"`
	Project  string    `json:"project_name"`
	Outcome  string    `json:"outcome"`
	Error    string    `json:"error,omitempty"`
	At       time.Time `json:"at"`
}

// Agent holds the runner's shared state and collaborators.
type Agent struct {
	mu        sync.RWMutex
	status    Status
	completed []JobRecord

	cfg       *Config
	log       *Logger
	client    *Client
	kstars    KStars
	weather   WeatherChecker
	startTime time.Time
	trigger   chan struct{}

	parkMu    sync.Mutex
	parkTimer *time.Timer

	// Weather safety tracking (written by weatherLoop, read by pollLoop/runJob).
	weatherMu       sync.RWMutex
	weatherSafe     bool // starts true (optimistic); set false on first unsafe reading
	consecutiveSafe int  // increments each safe check; resets to 0 on unsafe
}

func NewAgent(cfg *Config, log *Logger, client *Client, kstars KStars, wc WeatherChecker) *Agent {
	return &Agent{
		cfg:         cfg,
		log:         log,
		client:      client,
		kstars:      kstars,
		weather:     wc,
		weatherSafe: true, // optimistic until first unsafe reading
		startTime:   time.Now(),
		trigger:     make(chan struct{}, 1),
		status: Status{
			MachineID:    cfg.Machine.ID,
			State:        StateIdle,
			Hardware:     Hardware{Network: true},
			LastActivity: time.Now().UTC(),
		},
	}
}

func (a *Agent) snapshot() Status {
	a.mu.RLock()
	defer a.mu.RUnlock()
	return a.status
}

func (a *Agent) setState(s State) {
	a.mu.Lock()
	a.status.State = s
	a.status.LastActivity = time.Now().UTC()
	a.mu.Unlock()
}

func (a *Agent) setProgress(p Progress) {
	a.mu.Lock()
	a.status.Progress = p
	a.status.LastActivity = time.Now().UTC()
	a.mu.Unlock()
}

func (a *Agent) setCurrentJob(id string) {
	a.mu.Lock()
	a.status.CurrentJob = id
	a.mu.Unlock()
}

func (a *Agent) setHardware(h Hardware) {
	a.mu.Lock()
	a.status.Hardware = h
	a.mu.Unlock()
}

func (a *Agent) setNetwork(ok bool) {
	a.mu.Lock()
	a.status.Hardware.Network = ok
	a.mu.Unlock()
}

func (a *Agent) recordJob(r JobRecord) {
	a.mu.Lock()
	a.completed = append(a.completed, r)
	if len(a.completed) > 20 {
		a.completed = a.completed[len(a.completed)-20:]
	}
	a.mu.Unlock()
}

func (a *Agent) history() []JobRecord {
	a.mu.RLock()
	defer a.mu.RUnlock()
	out := make([]JobRecord, len(a.completed))
	copy(out, a.completed)
	return out
}

func (a *Agent) uptimeSeconds() int {
	return int(time.Since(a.startTime).Seconds())
}

func (a *Agent) setWeather(safe bool, msg string) {
	t := safe
	a.mu.Lock()
	a.status.Weather = Weather{Safe: &t, Message: msg}
	a.mu.Unlock()
}

func (a *Agent) isWeatherSafe() bool {
	a.weatherMu.RLock()
	defer a.weatherMu.RUnlock()
	return a.weatherSafe
}

func (a *Agent) weatherMessage() string {
	a.mu.RLock()
	defer a.mu.RUnlock()
	return a.status.Weather.Message
}
