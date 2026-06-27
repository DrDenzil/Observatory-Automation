package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"time"
)

// Client talks to the observatory web API's runner-facing endpoints.
type Client struct {
	baseURL string
	apiKey  string
	http    *http.Client
}

func NewClient(baseURL, apiKey string, timeout time.Duration) *Client {
	if timeout == 0 {
		timeout = 10 * time.Second
	}
	return &Client{
		baseURL: baseURL,
		apiKey:  apiKey,
		http:    &http.Client{Timeout: timeout},
	}
}

// ---- Wire types (match app/schemas/runner.py) ----

type BundleTarget struct {
	TargetName      string   `json:"target_name"`
	RA              float64  `json:"ra"`
	Dec             float64  `json:"dec"`
	Filters         []string `json:"filters"`
	ExposureSeconds float64  `json:"exposure_seconds"`
	Count           int      `json:"count"`
	Binning         int      `json:"binning"`
}

type JobBundle struct {
	JobID       string         `json:"job_id"`
	QueueRef    string         `json:"queue_ref"`
	ScopeID     string         `json:"scope_id"`
	EkosProfile string         `json:"ekos_profile"`
	ProjectName string         `json:"project_name"`
	Priority    int            `json:"priority"`
	Targets     []BundleTarget `json:"targets"`
}

type heartbeatBody struct {
	ScopeID          string `json:"scope_id"`
	Name             string `json:"name"`
	State            string `json:"state"`
	CurrentJobID     string `json:"current_job_id,omitempty"`
	ProgressStep     string `json:"progress_step,omitempty"`
	ProgressMessage  string `json:"progress_message,omitempty"`
	KStarsRunning    bool   `json:"kstars_running"`
	INDIRunning      bool   `json:"indi_running"`
	NetworkConnected bool   `json:"network_connected"`
	WeatherSafe        *bool  `json:"weather_safe,omitempty"`
	WeatherMessage     string `json:"weather_message,omitempty"`
	WebcamAvailable    bool   `json:"webcam_available"`
	ArduinoAvailable   bool   `json:"arduino_available"`
}

type progressBody struct {
	Status          string `json:"status,omitempty"`
	ProgressStep    string `json:"progress_step,omitempty"`
	ProgressMessage string `json:"progress_message,omitempty"`
	ErrorMessage    string `json:"error_message,omitempty"`
}

func (c *Client) do(method, path string, body any, out any) error {
	var rdr io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return err
		}
		rdr = bytes.NewReader(b)
	}
	req, err := http.NewRequest(method, c.baseURL+path, rdr)
	if err != nil {
		return err
	}
	req.Header.Set("X-Runner-Key", c.apiKey)
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	resp, err := c.http.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	data, _ := io.ReadAll(resp.Body)
	if resp.StatusCode >= 300 {
		return fmt.Errorf("%s %s -> %d: %s", method, path, resp.StatusCode, string(data))
	}
	if out != nil && len(data) > 0 && string(data) != "null" {
		return json.Unmarshal(data, out)
	}
	return nil
}

func (c *Client) Heartbeat(b heartbeatBody) error {
	return c.do("POST", "/api/runner/heartbeat", b, nil)
}

// ClaimNext returns the next job for the scope, or nil if the queue is empty.
func (c *Client) ClaimNext(scopeID string) (*JobBundle, error) {
	var bundle JobBundle
	path := "/api/runner/jobs/next?scope_id=" + url.QueryEscape(scopeID)
	if err := c.do("GET", path, nil, &bundle); err != nil {
		return nil, err
	}
	if bundle.JobID == "" {
		return nil, nil
	}
	return &bundle, nil
}

func (c *Client) ReportProgress(jobID string, b progressBody) error {
	return c.do("POST", "/api/runner/jobs/"+url.PathEscape(jobID)+"/progress", b, nil)
}
