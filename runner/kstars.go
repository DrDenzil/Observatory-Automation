package main

import (
	"fmt"
	"os/exec"
	"strings"
	"sync"
	"time"
)

// KStars abstracts driving the EKOS scheduler. Two implementations exist:
// DBusKStars (talks to a real KStars over D-Bus) and SimKStars (a fake used
// for development and CI where no telescope/KStars is present).
type KStars interface {
	Name() string
	Available() bool
	LoadSchedule(eslPath string) error
	Start() error
	// Status reports "running", "complete", or "aborted".
	Status() (string, error)
	Stop() error
}

// ---- Simulator ----

type SimKStars struct {
	mu      sync.Mutex
	jobDur  time.Duration
	endTime time.Time
	running bool
}

func NewSimKStars(jobSeconds int) *SimKStars {
	if jobSeconds <= 0 {
		jobSeconds = 8
	}
	return &SimKStars{jobDur: time.Duration(jobSeconds) * time.Second}
}

func (s *SimKStars) Name() string    { return "simulator" }
func (s *SimKStars) Available() bool { return true }

func (s *SimKStars) LoadSchedule(eslPath string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.running = false
	return nil
}

func (s *SimKStars) Start() error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.endTime = time.Now().Add(s.jobDur)
	s.running = true
	return nil
}

func (s *SimKStars) Status() (string, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !s.running {
		return "aborted", nil
	}
	if time.Now().After(s.endTime) {
		s.running = false
		return "complete", nil
	}
	return "running", nil
}

func (s *SimKStars) Stop() error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.running = false
	return nil
}

// ---- Real KStars over D-Bus (qdbus) ----

const (
	dbusService    = "org.kde.kstars"
	schedulerPath  = "/KStars/Ekos/Scheduler"
	schedulerIface = "org.kde.kstars.Ekos.Scheduler"
)

type DBusKStars struct {
	profile string
}

func NewDBusKStars(profile string) *DBusKStars {
	return &DBusKStars{profile: profile}
}

func (k *DBusKStars) Name() string { return "kstars-dbus" }

func (k *DBusKStars) Available() bool {
	// `qdbus org.kde.kstars` lists object paths if KStars is up.
	return exec.Command("qdbus", dbusService).Run() == nil
}

func (k *DBusKStars) qdbus(args ...string) (string, error) {
	full := append([]string{dbusService, schedulerPath}, args...)
	out, err := exec.Command("qdbus", full...).CombinedOutput()
	if err != nil {
		return "", fmt.Errorf("qdbus %s: %v: %s", strings.Join(args, " "), err, strings.TrimSpace(string(out)))
	}
	return strings.TrimSpace(string(out)), nil
}

func (k *DBusKStars) LoadSchedule(eslPath string) error {
	_, err := k.qdbus(schedulerIface+".loadScheduler", eslPath)
	return err
}

func (k *DBusKStars) Start() error {
	_, err := k.qdbus(schedulerIface + ".start")
	return err
}

func (k *DBusKStars) Status() (string, error) {
	// EKOS SchedulerState enum: 0 IDLE, 1 STARTUP, 2 RUNNING, 3 PAUSED,
	// 4 SHUTDOWN, 5 ABORTED, 6 COMPLETED (values per KStars source).
	out, err := k.qdbus(schedulerIface + ".status")
	if err != nil {
		return "", err
	}
	switch strings.TrimSpace(out) {
	case "6":
		return "complete", nil
	case "5":
		return "aborted", nil
	default:
		return "running", nil
	}
}

func (k *DBusKStars) Stop() error {
	_, err := k.qdbus(schedulerIface + ".stop")
	return err
}
