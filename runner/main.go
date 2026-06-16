package main

import (
	"context"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"
)

func main() {
	configPath := flag.String("config", "runner.yaml", "path to YAML config file")
	flag.Parse()

	cfg, err := LoadConfig(*configPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, "config error:", err)
		os.Exit(1)
	}

	logger, err := NewLogger(cfg.Logging.Level, cfg.Logging.File)
	if err != nil {
		fmt.Fprintln(os.Stderr, "logger error:", err)
		os.Exit(1)
	}

	client := NewClient(cfg.Web.BaseURL, cfg.Web.APIKey, cfg.Web.Timeout.D())

	var ks KStars
	if cfg.Runner.Simulator {
		ks = NewSimKStars(cfg.Runner.SimulatorJobSeconds)
	} else {
		ks = NewDBusKStars(cfg.KStars.Profile)
	}

	agent := NewAgent(cfg, logger, client, ks)
	avail := ks.Available()
	agent.setHardware(Hardware{KStars: avail, INDI: avail, Network: true})

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	go agent.heartbeatLoop(ctx)
	go agent.pollLoop(ctx)

	server := NewServer(agent)
	addr := fmt.Sprintf("%s:%d", cfg.Runner.APIHost, cfg.Runner.APIPort)
	go func() {
		if err := server.Run(ctx, addr); err != nil {
			logger.Error("server", "http server error", map[string]any{"error": err.Error()})
		}
	}()

	logger.Info("main", "runner started", map[string]any{
		"machine":   cfg.Machine.ID,
		"web":       cfg.Web.BaseURL,
		"api_addr":  addr,
		"simulator": cfg.Runner.Simulator,
		"kstars":    ks.Name(),
	})

	<-ctx.Done()
	logger.Info("main", "shutting down", nil)
	agent.sendHeartbeat("offline")
}
