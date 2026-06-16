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
	} `yaml:"runner"`

	KStars struct {
		Profile     string   `yaml:"profile"`
		LoadTimeout Duration `yaml:"load_timeout"`
	} `yaml:"kstars"`

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
	if c.Logging.Level == "" {
		c.Logging.Level = "info"
	}
}
