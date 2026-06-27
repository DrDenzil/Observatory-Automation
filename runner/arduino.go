package main

import (
	"fmt"
	"os"
	"sync"
	"time"

	"go.bug.st/serial"
)

// ArduinoController manages the serial connection to the Arduino Leonardo shield.
// IR LEDs are controlled by sending a single ASCII digit '0'–'9' at 9600 baud:
//   '0' = off, '1' = dim (25/255) … '9' = full (255/255)
// The sketch also supports stair LEDs and a NeoPixel strip but we only expose IR here.
type ArduinoController struct {
	cfg   *Config
	log   *Logger
	avail bool
	mu    sync.Mutex
	port  serial.Port
	level int // current IR level 0-9
}

func NewArduinoController(cfg *Config, log *Logger) *ArduinoController {
	_, err := os.Stat(cfg.Arduino.Device)
	if err != nil {
		return &ArduinoController{cfg: cfg, log: log, avail: false}
	}

	ac := &ArduinoController{cfg: cfg, log: log}
	if err := ac.connect(); err != nil {
		log.Warn("arduino", "failed to open serial port", map[string]any{"device": cfg.Arduino.Device, "err": err.Error()})
		return &ArduinoController{cfg: cfg, log: log, avail: false}
	}
	return ac
}

func (ac *ArduinoController) connect() error {
	mode := &serial.Mode{BaudRate: 9600}
	port, err := serial.Open(ac.cfg.Arduino.Device, mode)
	if err != nil {
		return err
	}
	ac.port = port
	ac.avail = true

	// The Arduino Leonardo sketch runs `while (!Serial)` in setup(), which blocks
	// until the serial port is opened. Give it 2s to finish setup() after we connect.
	time.Sleep(2 * time.Second)

	// Start with IR off
	ac.sendByte('0')
	ac.level = 0
	ac.log.Info("arduino", "connected, IR off", map[string]any{"device": ac.cfg.Arduino.Device})
	return nil
}

func (ac *ArduinoController) sendByte(b byte) error {
	if ac.port == nil {
		return fmt.Errorf("port not open")
	}
	_, err := ac.port.Write([]byte{b})
	return err
}

// SetIR sets the IR LED brightness to level 0 (off) through 9 (full).
func (ac *ArduinoController) SetIR(level int) error {
	if !ac.avail {
		return fmt.Errorf("arduino not available")
	}
	if level < 0 {
		level = 0
	}
	if level > 9 {
		level = 9
	}
	ac.mu.Lock()
	defer ac.mu.Unlock()
	if err := ac.sendByte(byte('0' + level)); err != nil {
		return err
	}
	ac.level = level
	ac.log.Info("arduino", "IR level set", map[string]any{"level": level})
	return nil
}

func (ac *ArduinoController) IRLevel() int  { return ac.level }
func (ac *ArduinoController) Available() bool { return ac.avail }

func (ac *ArduinoController) Close() {
	if ac.port != nil {
		ac.sendByte('0') // IR off on shutdown
		ac.port.Close()
	}
}
