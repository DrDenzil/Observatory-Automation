package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

const (
	defaultFrameType   = "Light"
	defaultSeqFormat   = "FITS"
	defaultSeqEncoding = "FITS"
	defaultUploadMode  = 0
	defaultMinAltitude = 15.0
)

var xmlEscaper = strings.NewReplacer(
	"&", "&amp;",
	"<", "&lt;",
	">", "&gt;",
	"\"", "&quot;",
	"'", "&apos;",
)

func esc(s string) string { return xmlEscaper.Replace(s) }

// ftoa formats a float the way the original Python runner did (Python's %g,
// i.e. 6 significant figures, trailing zeros trimmed). This keeps the EKOS
// output identical to the proven format KStars consumes, e.g. 83.82/15 ->
// "5.588" rather than Go's shortest-round-trip "5.587999999999999".
func ftoa(v float64) string { return strconv.FormatFloat(v, 'g', 6, 64) }

// BundlePaths holds the artifacts produced for one job.
type BundlePaths struct {
	Dir       string
	Sequence  string
	Scheduler string
	Manifest  string
	Captures  string
}

func isSimulatorProfile(profile string) bool {
	p := strings.ToLower(profile)
	return strings.Contains(p, "sim") || strings.Contains(p, "test") || p == "scope03"
}

// prepareBundle writes the .esq/.esl/manifest for a job under
// <workDir>/<machine>/generated/<queue_ref>/.
func prepareBundle(bundle *JobBundle, workDir string, simulator bool) (*BundlePaths, error) {
	dir := filepath.Join(workDir, bundle.ScopeID, "generated", bundle.QueueRef)
	captures := filepath.Join(dir, "captures")
	if err := os.MkdirAll(captures, 0o755); err != nil {
		return nil, err
	}

	seqPath := filepath.Join(dir, bundle.QueueRef+".esq")
	if err := os.WriteFile(seqPath, []byte(buildSequence(bundle, captures)), 0o644); err != nil {
		return nil, err
	}

	sim := simulator || isSimulatorProfile(bundle.EkosProfile)
	eslPath := filepath.Join(dir, bundle.QueueRef+".esl")
	if err := os.WriteFile(eslPath, []byte(buildScheduler(bundle, seqPath, sim)), 0o644); err != nil {
		return nil, err
	}

	manifestPath := filepath.Join(dir, "manifest.json")
	if err := writeManifest(bundle, manifestPath, seqPath, eslPath); err != nil {
		return nil, err
	}

	return &BundlePaths{
		Dir:       dir,
		Sequence:  seqPath,
		Scheduler: eslPath,
		Manifest:  manifestPath,
		Captures:  captures,
	}, nil
}

func buildSequence(bundle *JobBundle, capturesDir string) string {
	var b strings.Builder
	b.WriteString("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n")
	b.WriteString("<SequenceQueue version='2.4'>\n")
	fmt.Fprintf(&b, "  <!-- Sequence for %s -->\n", esc(bundle.QueueRef))
	if bundle.ProjectName != "" {
		fmt.Fprintf(&b, "  <Observer>%s</Observer>\n", esc(bundle.ProjectName))
	}
	b.WriteString("  <GuideDeviation enabled=\"false\">1.5</GuideDeviation>\n")
	b.WriteString("  <GuideStartDeviation enabled=\"false\">1.5</GuideStartDeviation>\n")
	b.WriteString("  <HFRCheck enabled=\"false\">\n")
	b.WriteString("    <HFRDeviation>0</HFRDeviation>\n")
	b.WriteString("    <HFRCheckAlgorithm>0</HFRCheckAlgorithm>\n")
	b.WriteString("    <HFRCheckThreshold>0</HFRCheckThreshold>\n")
	b.WriteString("    <HFRCheckFrames>0</HFRCheckFrames>\n")
	b.WriteString("  </HFRCheck>\n")
	b.WriteString("  <RefocusOnTemperatureDelta enabled=\"false\">0</RefocusOnTemperatureDelta>\n")
	b.WriteString("  <RefocusEveryN enabled=\"false\">0</RefocusEveryN>\n")
	b.WriteString("  <RefocusOnMeridianFlip enabled=\"false\"/>\n")

	for _, t := range bundle.Targets {
		filters := t.Filters
		if len(filters) == 0 {
			filters = []string{""}
		}
		for _, f := range filters {
			writeSequenceJob(&b, t, f, capturesDir)
		}
	}

	b.WriteString("</SequenceQueue>\n")
	return b.String()
}

func writeSequenceJob(b *strings.Builder, t BundleTarget, filter, capturesDir string) {
	count := t.Count
	if count < 1 {
		count = 1
	}
	binning := t.Binning
	if binning < 1 {
		binning = 1
	}
	b.WriteString("  <Job>\n")
	fmt.Fprintf(b, "    <Exposure>%s</Exposure>\n", ftoa(t.ExposureSeconds))
	fmt.Fprintf(b, "    <Format>%s</Format>\n", defaultSeqFormat)
	fmt.Fprintf(b, "    <Encoding>%s</Encoding>\n", defaultSeqEncoding)
	b.WriteString("    <Binning>\n")
	fmt.Fprintf(b, "      <X>%d</X>\n", binning)
	fmt.Fprintf(b, "      <Y>%d</Y>\n", binning)
	b.WriteString("    </Binning>\n")
	b.WriteString("    <Frame>\n      <X>0</X>\n      <Y>0</Y>\n      <W>0</W>\n      <H>0</H>\n    </Frame>\n")
	if filter != "" {
		fmt.Fprintf(b, "    <Filter>%s</Filter>\n", esc(filter))
	}
	fmt.Fprintf(b, "    <Type>%s</Type>\n", defaultFrameType)
	fmt.Fprintf(b, "    <Count>%d</Count>\n", count)
	b.WriteString("    <Delay>0</Delay>\n")
	fmt.Fprintf(b, "    <TargetName>%s</TargetName>\n", esc(t.TargetName))
	b.WriteString("    <GuideDitherPerJob>-1</GuideDitherPerJob>\n")
	fmt.Fprintf(b, "    <FITSDirectory>%s</FITSDirectory>\n", esc(capturesDir))
	b.WriteString("    <PlaceholderFormat>%t_%F_%e_%D</PlaceholderFormat>\n")
	b.WriteString("    <PlaceholderSuffix>0</PlaceholderSuffix>\n")
	fmt.Fprintf(b, "    <UploadMode>%d</UploadMode>\n", defaultUploadMode)
	b.WriteString("    <Properties>\n    </Properties>\n")
	b.WriteString("    <Calibration>\n")
	b.WriteString("      <PreAction>\n        <Type>0</Type>\n      </PreAction>\n")
	b.WriteString("      <FlatDuration dark=\"false\">\n        <Type>Manual</Type>\n      </FlatDuration>\n")
	b.WriteString("    </Calibration>\n")
	b.WriteString("  </Job>\n")
}

func buildScheduler(bundle *JobBundle, sequencePath string, simulator bool) string {
	var b strings.Builder
	b.WriteString("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n")
	b.WriteString("<SchedulerList version='2.2'>\n")
	fmt.Fprintf(&b, "  <!-- Scheduler for %s -->\n", esc(bundle.QueueRef))
	fmt.Fprintf(&b, "  <Profile>%s</Profile>\n", esc(bundle.EkosProfile))

	for _, t := range bundle.Targets {
		// RA arrives in degrees from the website; EKOS expects hours.
		raHours := t.RA / 15.0
		steps := []string{"Track", "Focus", "Align", "Guide"}
		if simulator {
			steps = []string{"Track"}
		}

		b.WriteString("  <Job>\n")
		b.WriteString("    <JobType lead=\"true\"/>\n")
		fmt.Fprintf(&b, "    <Name>%s</Name>\n", esc(t.TargetName))
		group := bundle.ProjectName
		if group == "" {
			group = bundle.QueueRef
		}
		fmt.Fprintf(&b, "    <Group>%s</Group>\n", esc(group))
		b.WriteString("    <Coordinates>\n")
		fmt.Fprintf(&b, "      <J2000RA>%s</J2000RA>\n", esc(ftoa(raHours)))
		fmt.Fprintf(&b, "      <J2000DE>%s</J2000DE>\n", esc(ftoa(t.Dec)))
		b.WriteString("    </Coordinates>\n")
		b.WriteString("    <PositionAngle>0</PositionAngle>\n")
		fmt.Fprintf(&b, "    <Sequence>%s</Sequence>\n", esc(sequencePath))
		b.WriteString("    <StartupCondition>\n      <Condition>ASAP</Condition>\n    </StartupCondition>\n")

		if simulator {
			b.WriteString("    <Constraints>\n    </Constraints>\n")
		} else {
			b.WriteString("    <Constraints>\n")
			fmt.Fprintf(&b, "      <Constraint value=\"%s\">MinimumAltitude</Constraint>\n", ftoa(defaultMinAltitude))
			b.WriteString("      <Constraint>EnforceTwilight</Constraint>\n")
			b.WriteString("    </Constraints>\n")
		}

		b.WriteString("    <CompletionCondition>\n      <Condition>Sequence</Condition>\n    </CompletionCondition>\n")
		b.WriteString("    <Steps>\n")
		for _, s := range steps {
			fmt.Fprintf(&b, "      <Step>%s</Step>\n", s)
		}
		b.WriteString("    </Steps>\n")
		b.WriteString("  </Job>\n")
	}

	b.WriteString("  <SchedulerAlgorithm value=\"1\"/>\n")
	b.WriteString("  <ErrorHandlingStrategy value=\"0\">\n    <delay>0</delay>\n  </ErrorHandlingStrategy>\n")
	b.WriteString("  <StartupProcedure enabled=\"false\">\n  </StartupProcedure>\n")
	b.WriteString("  <ShutdownProcedure enabled=\"false\">\n  </ShutdownProcedure>\n")
	b.WriteString("</SchedulerList>\n")
	return b.String()
}

func writeManifest(bundle *JobBundle, path, seqPath, eslPath string) error {
	manifest := map[string]any{
		"queue_ref":    bundle.QueueRef,
		"job_id":       bundle.JobID,
		"machine_id":   bundle.ScopeID,
		"project":      bundle.ProjectName,
		"generated_at": time.Now().UTC().Format(time.RFC3339),
		"artifacts": map[string]any{
			"sequence_file":  seqPath,
			"scheduler_file": eslPath,
		},
		"target_count": len(bundle.Targets),
	}
	b, err := json.MarshalIndent(manifest, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o644)
}

func targetNames(bundle *JobBundle) string {
	names := make([]string, 0, len(bundle.Targets))
	for _, t := range bundle.Targets {
		names = append(names, t.TargetName)
	}
	return strings.Join(names, ", ")
}
