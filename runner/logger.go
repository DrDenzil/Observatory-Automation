package main

import (
	"encoding/json"
	"fmt"
	"os"
	"sync"
	"time"
)

// LogEntry is one structured (JSON) log line.
type LogEntry struct {
	Timestamp string         `json:"timestamp"`
	Level     string         `json:"level"`
	Component string         `json:"component"`
	Message   string         `json:"message"`
	Context   map[string]any `json:"context,omitempty"`
}

var levelRank = map[string]int{"debug": 0, "info": 1, "warn": 2, "error": 3, "fatal": 4}

// Logger writes JSON log lines to stdout (and optionally a file), and keeps a
// bounded in-memory ring buffer so the REST /logs endpoint can serve recent
// entries without reading the file back.
type Logger struct {
	mu      sync.Mutex
	file    *os.File
	level   int
	ring    []LogEntry
	ringMax int
}

func NewLogger(level, filePath string) (*Logger, error) {
	rank, ok := levelRank[level]
	if !ok {
		rank = levelRank["info"]
	}
	l := &Logger{level: rank, ringMax: 500}
	if filePath != "" {
		f, err := os.OpenFile(filePath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
		if err != nil {
			return nil, err
		}
		l.file = f
	}
	return l, nil
}

func (l *Logger) log(level, component, msg string, ctx map[string]any) {
	if levelRank[level] < l.level {
		return
	}
	e := LogEntry{
		Timestamp: time.Now().UTC().Format(time.RFC3339Nano),
		Level:     level,
		Component: component,
		Message:   msg,
		Context:   ctx,
	}
	b, _ := json.Marshal(e)
	line := string(b) + "\n"

	l.mu.Lock()
	defer l.mu.Unlock()
	fmt.Fprint(os.Stdout, line)
	if l.file != nil {
		fmt.Fprint(l.file, line)
	}
	l.ring = append(l.ring, e)
	if len(l.ring) > l.ringMax {
		l.ring = l.ring[len(l.ring)-l.ringMax:]
	}
}

func (l *Logger) Debug(c, m string, ctx map[string]any) { l.log("debug", c, m, ctx) }
func (l *Logger) Info(c, m string, ctx map[string]any)  { l.log("info", c, m, ctx) }
func (l *Logger) Warn(c, m string, ctx map[string]any)  { l.log("warn", c, m, ctx) }
func (l *Logger) Error(c, m string, ctx map[string]any) { l.log("error", c, m, ctx) }

// Recent returns up to limit entries at or above minLevel (oldest first).
func (l *Logger) Recent(limit int, minLevel string) []LogEntry {
	min, ok := levelRank[minLevel]
	if !ok {
		min = 0
	}
	l.mu.Lock()
	defer l.mu.Unlock()
	out := make([]LogEntry, 0, len(l.ring))
	for _, e := range l.ring {
		if levelRank[e.Level] >= min {
			out = append(out, e)
		}
	}
	if limit > 0 && len(out) > limit {
		out = out[len(out)-limit:]
	}
	return out
}
