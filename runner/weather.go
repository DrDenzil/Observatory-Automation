package main

import (
	"fmt"
	"os/exec"
	"strings"
)

// WeatherReading is the result of a single weather safety check.
type WeatherReading struct {
	Safe    bool
	Message string // e.g. "SAFE: All clear" or "UNSAFE: Wind too high: 65 km/h"
}

// WeatherChecker polls a weather source and returns a safety reading.
type WeatherChecker interface {
	Check() (WeatherReading, error)
}

// ScriptWeather calls weather_safety.py --check and parses its first output line.
// "SAFE: ..." → safe=true; "UNSAFE: ..." → safe=false.
type ScriptWeather struct {
	scriptPath string
}

func NewScriptWeather(scriptPath string) *ScriptWeather {
	return &ScriptWeather{scriptPath: scriptPath}
}

func (w *ScriptWeather) Check() (WeatherReading, error) {
	out, err := exec.Command("python3", w.scriptPath, "--check").CombinedOutput()
	if err != nil {
		return WeatherReading{}, fmt.Errorf("weather script: %v: %s", err, strings.TrimSpace(string(out)))
	}
	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	if len(lines) == 0 {
		return WeatherReading{}, fmt.Errorf("weather script produced no output")
	}
	first := lines[0]
	safe := strings.HasPrefix(first, "SAFE")
	return WeatherReading{Safe: safe, Message: first}, nil
}

// SimWeather always reports safe — used when weather.enabled=false.
type SimWeather struct{}

func (w *SimWeather) Check() (WeatherReading, error) {
	return WeatherReading{Safe: true, Message: "SAFE (weather monitoring disabled)"}, nil
}
