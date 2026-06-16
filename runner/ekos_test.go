package main

import (
	"strings"
	"testing"
)

func sampleBundle() *JobBundle {
	return &JobBundle{
		JobID:       "abc123",
		QueueRef:    "ekos_abc123_test",
		ScopeID:     "scope03",
		EkosProfile: "scope03",
		ProjectName: "Orion Test",
		Targets: []BundleTarget{
			{
				TargetName:      "M42",
				RA:              83.82, // degrees
				Dec:             -5.39,
				Filters:         []string{"L", "Ha"},
				ExposureSeconds: 300,
				Count:           10,
				Binning:         1,
			},
		},
	}
}

func TestSchedulerConvertsRAToHours(t *testing.T) {
	out := buildScheduler(sampleBundle(), "/tmp/seq.esq", true)
	// 83.82 / 15 = 5.588
	if !strings.Contains(out, "<J2000RA>5.588</J2000RA>") {
		t.Errorf("RA not converted to hours; got:\n%s", out)
	}
	if !strings.Contains(out, "<J2000DE>-5.39</J2000DE>") {
		t.Errorf("Dec should be preserved; got:\n%s", out)
	}
}

func TestSchedulerSimulatorUsesTrackOnly(t *testing.T) {
	sim := buildScheduler(sampleBundle(), "/tmp/seq.esq", true)
	if strings.Contains(sim, "<Step>Focus</Step>") {
		t.Error("simulator schedule should not contain Focus step")
	}
	if !strings.Contains(sim, "<Step>Track</Step>") {
		t.Error("simulator schedule should contain Track step")
	}

	full := buildScheduler(sampleBundle(), "/tmp/seq.esq", false)
	for _, step := range []string{"Track", "Focus", "Align", "Guide"} {
		if !strings.Contains(full, "<Step>"+step+"</Step>") {
			t.Errorf("production schedule missing step %s", step)
		}
	}
}

func TestSequenceExpandsOneJobPerFilter(t *testing.T) {
	out := buildSequence(sampleBundle(), "/tmp/captures")
	if got := strings.Count(out, "<Job>"); got != 2 {
		t.Errorf("expected 2 sequence jobs (one per filter), got %d", got)
	}
	if !strings.Contains(out, "<Filter>L</Filter>") || !strings.Contains(out, "<Filter>Ha</Filter>") {
		t.Errorf("both filters should appear; got:\n%s", out)
	}
	if !strings.Contains(out, "<Count>10</Count>") {
		t.Error("count should propagate to sequence job")
	}
}

func TestXMLEscaping(t *testing.T) {
	b := sampleBundle()
	b.Targets[0].TargetName = "M42 & <friends>"
	out := buildSequence(b, "/tmp/captures")
	if strings.Contains(out, "M42 & <friends>") {
		t.Error("special characters must be XML-escaped")
	}
	if !strings.Contains(out, "M42 &amp; &lt;friends&gt;") {
		t.Errorf("expected escaped target name; got:\n%s", out)
	}
}
