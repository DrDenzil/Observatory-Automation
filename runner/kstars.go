package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"
)

// KStars abstracts driving the EKOS scheduler. Two implementations exist:
// DBusKStars (talks to a real KStars over D-Bus) and SimKStars (a fake used
// for development and CI where no telescope/KStars is present).
type KStars interface {
	Name() string
	Available() bool
	// INDIConnected returns true when INDI devices are fully online (status=2).
	INDIConnected() bool
	// EnsureINDI starts EKOS/INDI if not connected and waits up to timeout.
	EnsureINDI(timeout time.Duration) error
	LoadSchedule(eslPath string) error
	Start() error
	// Status reports "running", "complete", or "aborted".
	Status() (string, error)
	Stop() error
	// Park stops EKOS/INDI so the hardware can run its shutdown sequence
	// (park mount, close dome, etc. as configured in the EKOS profile).
	// EnsureINDI will bring everything back up when the next job arrives.
	Park() error
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

func (s *SimKStars) Name() string                        { return "simulator" }
func (s *SimKStars) Available() bool                     { return true }
func (s *SimKStars) INDIConnected() bool                 { return true }
func (s *SimKStars) EnsureINDI(_ time.Duration) error    { return nil }

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

func (s *SimKStars) Park() error { return nil }

// ---- Real KStars over D-Bus (qdbus) ----

const (
	dbusService    = "org.kde.kstars"
	ekosPath       = "/KStars/Ekos"
	ekosIface      = "org.kde.kstars.Ekos"
	schedulerPath  = "/KStars/Ekos/Scheduler"
	schedulerIface = "org.kde.kstars.Ekos.Scheduler"
)

type DBusKStars struct {
	mu          sync.Mutex
	profile     string
	seenRunning bool // true once we've observed RUNNING state
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

func (k *DBusKStars) qdbusEkos(method string, args ...string) (string, error) {
	cmdArgs := []string{dbusService, ekosPath, ekosIface + "." + method}
	cmdArgs = append(cmdArgs, args...)
	out, err := exec.Command("qdbus", cmdArgs...).CombinedOutput()
	if err != nil {
		return "", fmt.Errorf("qdbus Ekos.%s: %v: %s", method, err, strings.TrimSpace(string(out)))
	}
	return strings.TrimSpace(string(out)), nil
}

func (k *DBusKStars) INDIConnected() bool {
	out, err := k.qdbusEkos("indiStatus")
	return err == nil && strings.TrimSpace(out) == "2"
}

// launchKStars starts KStars as a detached process (new session) so it
// survives runner restarts. It inherits the runner's environment and fills in
// DISPLAY / XAUTHORITY if they are missing (common when running under systemd).
func (k *DBusKStars) launchKStars() error {
	env := os.Environ()

	hasDisplay := false
	hasXAuth := false
	for _, e := range env {
		if strings.HasPrefix(e, "DISPLAY=") {
			hasDisplay = true
		}
		if strings.HasPrefix(e, "XAUTHORITY=") {
			hasXAuth = true
		}
	}
	if !hasDisplay {
		env = append(env, "DISPLAY=:0")
	}
	if !hasXAuth {
		// Wayland/XWayland auth file lives at a session-specific path; glob for it.
		if matches, _ := filepath.Glob("/run/user/*/.mutter-Xwaylandauth.*"); len(matches) > 0 {
			env = append(env, "XAUTHORITY="+matches[0])
		}
	}

	cmd := exec.Command("kstars")
	cmd.Env = env
	// Setsid detaches KStars into its own session so systemd doesn't kill it
	// when it stops/restarts the runner service.
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	cmd.Stdout = nil
	cmd.Stderr = nil
	return cmd.Start()
}

// EnsureINDI brings up the full KStars→EKOS→INDI stack within timeout:
//  1. If KStars isn't on D-Bus, launch it and wait for it to appear.
//  2. If INDI isn't connected, call Ekos.start() and wait for devices.
func (k *DBusKStars) EnsureINDI(timeout time.Duration) error {
	deadline := time.Now().Add(timeout)

	// Step 1 — KStars itself
	if !k.Available() {
		if err := k.launchKStars(); err != nil {
			return fmt.Errorf("failed to launch KStars: %w", err)
		}
		for time.Now().Before(deadline) {
			time.Sleep(3 * time.Second)
			if k.Available() {
				break
			}
		}
		if !k.Available() {
			return fmt.Errorf("KStars did not appear on D-Bus within %s", timeout)
		}
		// Brief pause so EKOS UI finishes initialising before we poke it.
		time.Sleep(3 * time.Second)
	}

	// Step 2 — INDI devices
	if k.INDIConnected() {
		return nil
	}

	// If INDI is mid-connection with a stale/wrong profile, stop it cleanly first.
	if status, _ := k.qdbusEkos("indiStatus"); status == "1" {
		_, _ = k.qdbusEkos("stop")
		time.Sleep(2 * time.Second)
	}

	// Always set the profile before starting so we use the one in runner.yaml,
	// not whatever was last selected manually in the KStars UI.
	if k.profile != "" {
		if _, err := k.qdbusEkos("setProfile", k.profile); err != nil {
			return fmt.Errorf("Ekos.setProfile(%q): %w", k.profile, err)
		}
	}

	if _, err := k.qdbusEkos("start"); err != nil {
		return fmt.Errorf("Ekos.start: %w", err)
	}
	for time.Now().Before(deadline) {
		time.Sleep(2 * time.Second)
		if k.INDIConnected() {
			return nil
		}
	}
	return fmt.Errorf("INDI did not connect within %s", timeout)
}

func (k *DBusKStars) LoadSchedule(eslPath string) error {
	k.mu.Lock()
	k.seenRunning = false
	k.mu.Unlock()
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
	//
	// The scheduler can transition RUNNING(2) → COMPLETED(6) → IDLE(0) faster
	// than our 2-second poll catches it. So we track whether we ever saw RUNNING,
	// and treat a subsequent IDLE as completion (not "still waiting").
	out, err := k.qdbus(schedulerIface + ".status")
	if err != nil {
		return "", err
	}
	k.mu.Lock()
	defer k.mu.Unlock()
	switch strings.TrimSpace(out) {
	case "6":
		return "complete", nil
	case "5":
		return "aborted", nil
	case "2":
		k.seenRunning = true
		return "running", nil
	case "0":
		// IDLE after we saw RUNNING means the scheduler finished all jobs.
		if k.seenRunning {
			return "complete", nil
		}
		return "running", nil
	default:
		return "running", nil
	}
}

func (k *DBusKStars) Stop() error {
	_, err := k.qdbus(schedulerIface + ".stop")
	return err
}

// Park stops EKOS (disconnecting all INDI devices). If the EKOS profile has a
// shutdown sequence configured, it will run — parking the mount, closing the dome, etc.
// EnsureINDI will reconnect everything cleanly when the next job arrives.
func (k *DBusKStars) Park() error {
	_, err := k.qdbusEkos("stop")
	return err
}
