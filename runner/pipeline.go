package main

import (
	"context"
	"fmt"
	"time"
)

// report sends a progress update to the web API, logging (but not failing) on error.
func (a *Agent) report(jobID string, b progressBody) {
	if err := a.client.ReportProgress(jobID, b); err != nil {
		a.log.Warn("pipeline", "progress report failed", map[string]any{"job_id": jobID, "error": err.Error()})
	}
}

// runJob executes one job end to end: build bundle -> load -> run -> monitor -> report.
func (a *Agent) runJob(bundle *JobBundle) {
	a.cancelIdlePark() // defuse any pending park — a job just arrived
	a.setCurrentJob(bundle.JobID)
	a.log.Info("pipeline", "job claimed", map[string]any{
		"job_id":    bundle.JobID,
		"queue_ref": bundle.QueueRef,
		"project":   bundle.ProjectName,
		"targets":   targetNames(bundle),
	})

	// --- Build EKOS bundle ---
	a.setState(StateProcessing)
	a.setProgress(Progress{Step: "processing", Description: "Building EKOS bundle"})
	a.report(bundle.JobID, progressBody{Status: "running", ProgressStep: "processing", ProgressMessage: "Building EKOS bundle"})

	// Use the locally configured KStars profile name, not the scope_id the backend sends.
	if a.cfg.KStars.Profile != "" {
		bundle.EkosProfile = a.cfg.KStars.Profile
	}

	paths, err := prepareBundle(bundle, a.cfg.Runner.WorkDir, a.cfg.Runner.Simulator)
	if err != nil {
		a.failJob(bundle, fmt.Sprintf("bundle build failed: %v", err))
		return
	}
	a.log.Info("pipeline", "bundle prepared", map[string]any{"job_id": bundle.JobID, "scheduler": paths.Scheduler})

	// --- Ensure KStars and INDI are up before handing off to the scheduler ---
	// 90s gives time to launch KStars from scratch (~30-60s) plus INDI init.
	a.setProgress(Progress{Step: "processing", Description: "Starting KStars / INDI"})
	a.report(bundle.JobID, progressBody{ProgressStep: "processing", ProgressMessage: "Starting KStars / INDI"})
	if err := a.kstars.EnsureINDI(90 * time.Second); err != nil {
		a.failJob(bundle, fmt.Sprintf("KStars/INDI unavailable: %v", err))
		return
	}
	a.setHardware(Hardware{KStars: true, INDI: true, Network: a.snapshot().Hardware.Network})

	// --- Load into KStars / EKOS ---
	a.setState(StateExecuting)
	if err := a.kstars.LoadSchedule(paths.Scheduler); err != nil {
		a.failJob(bundle, fmt.Sprintf("load failed: %v", err))
		return
	}
	if err := a.kstars.Start(); err != nil {
		a.failJob(bundle, fmt.Sprintf("start failed: %v", err))
		return
	}

	desc := fmt.Sprintf("Capturing %s", targetNames(bundle))
	a.setProgress(Progress{Step: "executing", Description: desc})
	a.report(bundle.JobID, progressBody{ProgressStep: "executing", ProgressMessage: desc})

	// --- Monitor until complete / aborted / timeout ---
	start := time.Now()
	maxDur := a.cfg.Runner.MaxJobDuration.D()
	lastWebUpdate := time.Now()

	for {
		time.Sleep(2 * time.Second)

		st, err := a.kstars.Status()
		if err != nil {
			a.failJob(bundle, fmt.Sprintf("status error: %v", err))
			return
		}

		elapsed := int(time.Since(start).Seconds())
		a.setProgress(Progress{Step: "executing", Description: desc, Elapsed: elapsed})

		// Throttle web progress updates to ~every 10s.
		if time.Since(lastWebUpdate) >= 10*time.Second {
			a.report(bundle.JobID, progressBody{ProgressStep: "executing", ProgressMessage: desc})
			lastWebUpdate = time.Now()
		}

		// Weather check: abort if conditions have become unsafe.
		if a.cfg.Weather.Enabled && !a.isWeatherSafe() {
			a.log.Warn("pipeline", "weather unsafe mid-job, aborting and requeueing", map[string]any{
				"job_id":  bundle.JobID,
				"weather": a.weatherMessage(),
			})
			_ = a.kstars.Stop()
			time.Sleep(2 * time.Second)
			_ = a.kstars.Park()
			a.requeueJob(bundle, "weather unsafe: "+a.weatherMessage())
			return
		}

		switch st {
		case "complete":
			a.completeJob(bundle, paths)
			return
		case "aborted":
			a.failJob(bundle, "scheduler aborted")
			return
		}

		if time.Since(start) > maxDur {
			_ = a.kstars.Stop()
			a.failJob(bundle, fmt.Sprintf("job exceeded max duration (%s)", maxDur))
			return
		}
	}
}

func (a *Agent) completeJob(bundle *JobBundle, paths *BundlePaths) {
	a.setState(StateUploading)
	a.setProgress(Progress{Step: "uploading", Description: "Finalising captures"})
	a.log.Info("pipeline", "job complete", map[string]any{"job_id": bundle.JobID, "captures": paths.Captures})

	a.report(bundle.JobID, progressBody{Status: "completed", ProgressStep: "done", ProgressMessage: "Completed"})
	a.recordJob(JobRecord{
		JobID:    bundle.JobID,
		QueueRef: bundle.QueueRef,
		Project:  bundle.ProjectName,
		Outcome:  "completed",
		At:       time.Now().UTC(),
	})
	a.finishJob()
}

func (a *Agent) failJob(bundle *JobBundle, reason string) {
	a.log.Error("pipeline", "job failed", map[string]any{"job_id": bundle.JobID, "reason": reason})
	a.setState(StateFailed)
	a.report(bundle.JobID, progressBody{Status: "failed", ErrorMessage: reason})
	a.recordJob(JobRecord{
		JobID:    bundle.JobID,
		QueueRef: bundle.QueueRef,
		Project:  bundle.ProjectName,
		Outcome:  "failed",
		Error:    reason,
		At:       time.Now().UTC(),
	})
	a.finishJob()
}

// requeueJob reports weather_abort to the backend (which resets job to "queued")
// and leaves the runner in weather_hold so it doesn't immediately re-claim.
func (a *Agent) requeueJob(bundle *JobBundle, reason string) {
	a.log.Warn("pipeline", "job requeued due to weather", map[string]any{"job_id": bundle.JobID, "reason": reason})
	a.report(bundle.JobID, progressBody{Status: "weather_abort", ErrorMessage: reason})
	a.recordJob(JobRecord{
		JobID:    bundle.JobID,
		QueueRef: bundle.QueueRef,
		Project:  bundle.ProjectName,
		Outcome:  "weather_abort",
		Error:    reason,
		At:       time.Now().UTC(),
	})
	a.setCurrentJob("")
	a.setProgress(Progress{})
	a.setState(StateWeatherHold)
}

// finishJob returns the agent to idle and arms the idle-park timer if configured.
func (a *Agent) finishJob() {
	a.setCurrentJob("")
	a.setProgress(Progress{})
	a.setState(StateIdle)
	a.scheduleIdlePark()
}

// scheduleIdlePark arms (or resets) the timer that stops INDI after idle_park_after.
// If a new job arrives before the timer fires, cancelIdlePark defuses it.
func (a *Agent) scheduleIdlePark() {
	delay := a.cfg.Runner.IdleParkAfter.D()
	if delay == 0 {
		return
	}
	a.parkMu.Lock()
	defer a.parkMu.Unlock()
	if a.parkTimer != nil {
		a.parkTimer.Stop()
	}
	a.parkTimer = time.AfterFunc(delay, func() {
		a.log.Info("park", "idle timeout reached, stopping INDI", map[string]any{
			"after": delay.String(),
		})
		if err := a.kstars.Park(); err != nil {
			a.log.Warn("park", "park failed", map[string]any{"error": err.Error()})
		}
	})
}

// cancelIdlePark stops the timer so a new job can proceed without parking first.
func (a *Agent) cancelIdlePark() {
	a.parkMu.Lock()
	defer a.parkMu.Unlock()
	if a.parkTimer != nil {
		a.parkTimer.Stop()
		a.parkTimer = nil
	}
}

// ---- Loops ----

func (a *Agent) sendHeartbeat(stateOverride string) {
	s := a.snapshot()
	state := string(s.State)
	if stateOverride != "" {
		state = stateOverride
	}
	body := heartbeatBody{
		ScopeID:          a.cfg.Machine.ID,
		Name:             a.cfg.Machine.Name,
		State:            state,
		CurrentJobID:     s.CurrentJob,
		ProgressStep:     s.Progress.Step,
		ProgressMessage:  s.Progress.Description,
		KStarsRunning:    s.Hardware.KStars,
		INDIRunning:      s.Hardware.INDI,
		NetworkConnected: s.Hardware.Network,
		WeatherSafe:      s.Weather.Safe,
		WeatherMessage:   s.Weather.Message,
	}
	if err := a.client.Heartbeat(body); err != nil {
		a.log.Warn("heartbeat", "failed", map[string]any{"error": err.Error()})
		a.setNetwork(false)
		return
	}
	a.setNetwork(true)
}

// hardwareLoop polls KStars/INDI status every 30s and keeps Hardware accurate.
func (a *Agent) hardwareLoop(ctx context.Context) {
	tick := time.NewTicker(30 * time.Second)
	defer tick.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-tick.C:
			net := a.snapshot().Hardware.Network
			a.setHardware(Hardware{
				KStars:  a.kstars.Available(),
				INDI:    a.kstars.INDIConnected(),
				Network: net,
			})
		}
	}
}

func (a *Agent) heartbeatLoop(ctx context.Context) {
	a.sendHeartbeat("") // immediate first beat
	t := time.NewTicker(a.cfg.Runner.HeartbeatInterval.D())
	defer t.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			a.sendHeartbeat("")
		}
	}
}

func (a *Agent) pollLoop(ctx context.Context) {
	t := time.NewTicker(a.cfg.Runner.PollInterval.D())
	defer t.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			a.pollOnce(ctx)
		case <-a.trigger:
			a.log.Info("pipeline", "manual trigger received", nil)
			a.pollOnce(ctx)
		}
	}
}

func (a *Agent) pollOnce(ctx context.Context) {
	state := a.snapshot().State
	if state != StateIdle && state != StateWeatherHold {
		return // already busy with a real job
	}

	// Block job claims while weather is unsafe.
	if a.cfg.Weather.Enabled && !a.isWeatherSafe() {
		if state != StateWeatherHold {
			a.log.Warn("pipeline", "weather unsafe, holding", map[string]any{"weather": a.weatherMessage()})
			a.setState(StateWeatherHold)
		}
		return
	}

	// Weather is safe (or not enabled) — resume from hold if needed.
	if state == StateWeatherHold {
		a.log.Info("pipeline", "weather cleared, resuming operations", nil)
		a.setState(StateIdle)
	}

	a.setState(StateFetching)
	bundle, err := a.client.ClaimNext(a.cfg.Machine.ID)
	if err != nil {
		a.log.Warn("pipeline", "claim failed", map[string]any{"error": err.Error()})
		a.setState(StateIdle)
		return
	}
	if bundle == nil {
		a.setState(StateIdle)
		return
	}
	a.runJob(bundle)
}

// weatherLoop polls the weather source on a fixed interval and updates agent state.
// It runs an immediate check on startup so the runner knows weather safety before
// the first poll cycle.
func (a *Agent) weatherLoop(ctx context.Context) {
	if !a.cfg.Weather.Enabled {
		return
	}
	a.checkWeather()
	tick := time.NewTicker(a.cfg.Weather.CheckInterval.D())
	defer tick.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-tick.C:
			a.checkWeather()
		}
	}
}

func (a *Agent) checkWeather() {
	reading, err := a.weather.Check()
	if err != nil {
		a.log.Warn("weather", "check failed", map[string]any{"error": err.Error()})
		return
	}

	a.weatherMu.Lock()
	prevSafe := a.weatherSafe
	if reading.Safe {
		a.consecutiveSafe++
	} else {
		a.consecutiveSafe = 0
	}
	// Become safe only after enough consecutive safe readings (prevents flapping).
	// Become unsafe immediately on first unsafe reading.
	if !reading.Safe {
		a.weatherSafe = false
	} else if a.consecutiveSafe >= a.cfg.Weather.MinSafeReadings {
		a.weatherSafe = true
	}
	a.weatherMu.Unlock()

	a.setWeather(a.weatherSafe, reading.Message)

	if prevSafe && !a.weatherSafe {
		a.log.Warn("weather", "conditions unsafe", map[string]any{"message": reading.Message})
	} else if !prevSafe && a.weatherSafe {
		a.log.Info("weather", "conditions cleared", map[string]any{"message": reading.Message})
	} else {
		a.log.Info("weather", "check ok", map[string]any{"safe": a.weatherSafe, "message": reading.Message})
	}
}
