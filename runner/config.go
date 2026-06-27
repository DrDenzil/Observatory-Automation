package main

import (
	"fmt"
	"os"
	"time"

	"gopkg.in/yaml.v3"
)

// Duration is a time.Duration that unmarshals from YAML strings like "15s".
type Duration time.Duration

func (d *Duration) UnmarshalYAML(value *yaml.Node) error {
	var s string
	if err := value.Decode(&s); err != nil {
		return err
	}
	parsed, err := time.ParseDuration(s)
	if err != nil {
		return fmt.Errorf("invalid duration %q: %w", s, err)
	}
	*d = Duration(parsed)
	return nil
}

func (d Duration) D() time.Duration { return time.Duration(d) }

type Config struct {
	Machine struct {
		ID   string `yaml:"id"`
		Name string `yaml:"name"`
	} `yaml:"machine"`

	Web struct {
		BaseURL string   `yaml:"base_url"`
		APIKey  string   `yaml:"api_key"`
		Timeout Duration `yaml:"timeout"`
	} `yaml:"web"`

	Runner struct {
		PollInterval        Duration `yaml:"poll_interval"`
		HeartbeatInterval   Duration `yaml:"heartbeat_interval"`
		APIHost             string   `yaml:"api_host"`
		APIPort             int      `yaml:"api_port"`
		WorkDir             string   `yaml:"work_dir"`
		Simulator           bool     `yaml:"simulator"`
		SimulatorJobSeconds int      `yaml:"simulator_job_seconds"`
		MaxJobDuration      Duration `yaml:"max_job_duration"`
		// IdleParkAfter: stop INDI this long after the last job finishes.
		// A new job arriving before the timer fires cancels the park.
		// 0 = disabled (leave INDI running indefinitely).
		IdleParkAfter Duration `yaml:"idle_park_after"`
	} `yaml:"runner"`

	KStars struct {
		Profile     string   `yaml:"profile"`
		LoadTimeout Duration `yaml:"load_timeout"`
	} `yaml:"kstars"`

	Weather struct {
		Enabled         bool     `yaml:"enabled"`
		Script          string   `yaml:"script"`           // path to weather_safety.py
		CheckInterval   Duration `yaml:"check_interval"`   // how often to poll weather
		MinSafeReadings int      `yaml:"min_safe_readings"` // consecutive safe readings needed to resume after hold
	} `yaml:"weather"`

	Webcam struct {
		Device     string `yaml:"device"`      // e.g. /dev/video0
		Port       int    `yaml:"port"`        // HTTP server port, default 8765
		Width      int    `yaml:"width"`       // capture width, default 640
		Height     int    `yaml:"height"`      // capture height, default 480
		Framerate  int    `yaml:"framerate"`   // input framerate, default 10
		StreamFPS  int    `yaml:"stream_fps"`  // output fps to client, default 5
		Quality    int    `yaml:"quality"`     // JPEG quality 1-31 (lower=better), default 5
	} `yaml:"webcam"`

	Arduino struct {
		Device string `yaml:"device"` // serial device, default /dev/ttyACM0
	} `yaml:"arduino"`

	Logging struct {
		Level string `yaml:"level"`
		File  string `yaml:"file"`
	} `yaml:"logging"`
}

func LoadConfig(path string) (*Config, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var c Config
	if err := yaml.Unmarshal(b, &c); err != nil {
		return nil, err
	}

	// Environment overrides (handy for containers / CI).
	if v := os.Getenv("RUNNER_MACHINE_ID"); v != "" {
		c.Machine.ID = v
	}
	if v := os.Getenv("RUNNER_WEB_URL"); v != "" {
		c.Web.BaseURL = v
	}
	if v := os.Getenv("RUNNER_API_KEY"); v != "" {
		c.Web.APIKey = v
	}

	c.applyDefaults()
	if c.Machine.ID == "" {
		return nil, fmt.Errorf("machine.id is required")
	}
	return &c, nil
}

func (c *Config) applyDefaults() {
	if c.Web.BaseURL == "" {
		c.Web.BaseURL = "http://localhost:8000"
	}
	if c.Web.Timeout == 0 {
		c.Web.Timeout = Duration(10 * time.Second)
	}
	if c.Runner.PollInterval == 0 {
		c.Runner.PollInterval = Duration(15 * time.Second)
	}
	if c.Runner.HeartbeatInterval == 0 {
		c.Runner.HeartbeatInterval = Duration(10 * time.Second)
	}
	if c.Runner.APIHost == "" {
		c.Runner.APIHost = "0.0.0.0"
	}
	if c.Runner.APIPort == 0 {
		c.Runner.APIPort = 9090
	}
	if c.Runner.WorkDir == "" {
		c.Runner.WorkDir = "./runner-work"
	}
	if c.Runner.SimulatorJobSeconds == 0 {
		c.Runner.SimulatorJobSeconds = 8
	}
	if c.Runner.MaxJobDuration == 0 {
		c.Runner.MaxJobDuration = Duration(2 * time.Hour)
	}
	if c.KStars.Profile == "" {
		c.KStars.Profile = c.Machine.ID
	}
	if c.KStars.LoadTimeout == 0 {
		c.KStars.LoadTimeout = Duration(30 * time.Second)
	}
	if c.Weather.Script == "" {
		c.Weather.Script = "/usr/local/share/indi/scripts/weather_safety.py"
	}
	if c.Weather.CheckInterval == 0 {
		c.Weather.CheckInterval = Duration(60 * time.Second)
	}
	if c.Weather.MinSafeReadings == 0 {
		c.Weather.MinSafeReadings = 2
	}
	if c.Webcam.Device == "" {
		c.Webcam.Device = "/dev/video0"
	}
	if c.Webcam.Port == 0 {
		c.Webcam.Port = 8765
	}
	if c.Webcam.Width == 0 {
		c.Webcam.Width = 640
	}
	if c.Webcam.Height == 0 {
		c.Webcam.Height = 480
	}
	if c.Webcam.Framerate == 0 {
		c.Webcam.Framerate = 10
	}
	if c.Webcam.StreamFPS == 0 {
		c.Webcam.StreamFPS = 5
	}
	if c.Webcam.Quality == 0 {
		c.Webcam.Quality = 5
	}
	if c.Arduino.Device == "" {
		c.Arduino.Device = "/dev/ttyACM0"
	}
	if c.Logging.Level == "" {
		c.Logging.Level = "info"
	}
}
