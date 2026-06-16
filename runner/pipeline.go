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

	paths, err := prepareBundle(bundle, a.cfg.Runner.WorkDir, a.cfg.Runner.Simulator)
	if err != nil {
		a.failJob(bundle, fmt.Sprintf("bundle build failed: %v", err))
		return
	}
	a.log.Info("pipeline", "bundle prepared", map[string]any{"job_id": bundle.JobID, "scheduler": paths.Scheduler})

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

// finishJob returns the agent to idle, ready for the next poll.
func (a *Agent) finishJob() {
	a.setCurrentJob("")
	a.setProgress(Progress{})
	a.setState(StateIdle)
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
	}
	if err := a.client.Heartbeat(body); err != nil {
		a.log.Warn("heartbeat", "failed", map[string]any{"error": err.Error()})
		a.setNetwork(false)
		return
	}
	a.setNetwork(true)
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
	if a.snapshot().State != StateIdle {
		return // already busy
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
