import { useState, useEffect } from 'react';
import { api } from '../api/client';
import type { TelescopeConfig } from '../api/types';
import styles from './ExposureCalculator.module.css';

// ─── Physics constants (ported from expcalc.php) ─────────────────────────────

// Zero-magnitude flux in e-/s per cm² of aperture (× system efficiency)
const FLUX0F: Record<string, number> = {
  I:    26336.80149 * 16,
  R:    33768.64782 * 16,
  V:    40062.31374 * 16,
  G:    40062.31374 * 16, // Green ≈ V band
  B:    91274.92985 * 16,
  L:   200890.0234  * 16, // Luminance ≈ Clear
  C:   200890.0234  * 16,
  Ha:   2345.329852 * 16,
  OIII: 3363.985517 * 16,
  SII:  2987.506088 * 16,
};

const EXT_COEFF: Record<string, number> = {
  I: 0.10, R: 0.15, V: 0.25, G: 0.25, B: 0.40,
  L: 0.25, C: 0.25, Ha: 0.13, OIII: 0.25, SII: 0.13,
};

// Atmospheric transmission % through filter + optics (CKT-derived defaults)
const FILTER_TRANS: Record<string, number> = {
  I: 65, R: 84, V: 79, G: 79, B: 63, L: 79, C: 79, Ha: 84, OIII: 79, SII: 84,
};

// Filter bandpass efficiency %
const FILTER_EFF: Record<string, number> = {
  I: 100, R: 84, V: 88, G: 88, B: 75, L: 100, C: 100, Ha: 82, OIII: 85, SII: 100,
};

// Gaussian encircled energy integral (0.1 sigma steps, from expcalc.php)
const INTEGRAL = [
  0.000000, 0.005159, 0.020478, 0.043585, 0.077596, 0.118150,
  0.164268, 0.217997, 0.274646, 0.332261, 0.393318, 0.454462,
  0.512948, 0.570322, 0.624838, 0.675165, 0.722630, 0.764472,
  0.802221, 0.835890, 0.864689, 0.889805, 0.911216, 0.929147,
  0.943950, 0.956116, 0.965995, 0.973828, 0.980198, 0.985077,
  0.988897, 0.991824, 0.994022, 0.995690, 0.996918, 0.997812,
  0.998470, 0.998938, 0.999268, 0.999503, 0.999665, 0.999776,
  0.999853, 0.999904, 0.999938, 0.999960, 0.999975, 0.999984,
  0.999990, 0.999994, 0.999996, 0.999998, 0.999999, 0.999999,
  1.000000, 1.000000, 1.000000, 1.000000, 1.000000, 1.000000,
];

const EXP_TIMES = [1, 2, 3, 4, 5, 10, 15, 20, 30, 45, 60, 90, 120, 180, 240, 300];

// ─── Camera database ──────────────────────────────────────────────────────────

interface CameraSpec {
  label: string;
  pixel_um: number;
  rn_e: number;
  gain: Record<number, number>;
  idark: number;   // pA/cm²
  temp: number;    // °C operating
  ddouble: number; // °C per doubling
  tref: number;    // °C reference
  sensor: 'ccd' | 'aps';
  qe: Partial<Record<string, number>>; // % per filter
}

const CAMERAS: Record<string, CameraSpec> = {
  ASI6200: {
    label: 'ZWO ASI6200MM', pixel_um: 3.76, rn_e: 1.5,
    gain: { 1: 0.247, 2: 0.987, 3: 2.220, 4: 3.947 },
    idark: 0.000222, temp: 0, ddouble: 5.8, tref: 0, sensor: 'aps',
    qe: { L: 60, R: 70, G: 90, V: 90, B: 70, C: 60, I: 30, Ha: 60, OIII: 90, SII: 60 },
  },
  STX16803: {
    label: 'SBIG STX-16803', pixel_um: 9.0, rn_e: 10.0,
    gain: { 1: 1.27, 2: 2.30, 3: 2.30, 4: 2.30 },
    idark: 0.8, temp: -20, ddouble: 6.3, tref: 25, sensor: 'ccd',
    qe: { L: 55, R: 65, G: 60, V: 60, B: 40, C: 55, I: 35, Ha: 50, OIII: 60, SII: 50 },
  },
  STL6303: {
    label: 'SBIG STL-6303', pixel_um: 9.0, rn_e: 13.5,
    gain: { 1: 1.40, 2: 2.30, 3: 2.30, 4: 2.30 },
    idark: 1.0, temp: -20, ddouble: 6.3, tref: 25, sensor: 'ccd',
    qe: { L: 45, R: 65, G: 50, V: 50, B: 30, C: 45, I: 30 },
  },
  Q49000: {
    label: 'MI Q4-9000', pixel_um: 12.0, rn_e: 7.0,
    gain: { 1: 1.50, 2: 1.70, 3: 1.70, 4: 1.70 },
    idark: 0.55, temp: -20, ddouble: 7.0, tref: 25, sensor: 'ccd',
    qe: { L: 60, R: 70, G: 60, V: 60, B: 40, C: 60, I: 45 },
  },
  GENERIC: {
    label: 'Generic CCD', pixel_um: 9.0, rn_e: 10.0,
    gain: { 1: 1.50, 2: 1.50, 3: 1.50, 4: 1.50 },
    idark: 1.0, temp: -20, ddouble: 7.0, tref: 25, sensor: 'ccd',
    qe: { L: 50, R: 60, G: 55, V: 55, B: 40, C: 50, I: 35, Ha: 45, OIII: 50, SII: 45 },
  },
};

// Match a free-text camera name from telescope config to a camera key
function matchCamera(name: string | null): string {
  if (!name) return 'GENERIC';
  const n = name.toLowerCase().replace(/[-\s]/g, '');
  if (n.includes('asi6200')) return 'ASI6200';
  if (n.includes('stx') || n.includes('16803')) return 'STX16803';
  if (n.includes('stl') || n.includes('6303')) return 'STL6303';
  if (n.includes('q4') || n.includes('49000')) return 'Q49000';
  return 'GENERIC';
}

// ─── Calculation engine ───────────────────────────────────────────────────────

interface CalcInputs {
  aperture_mm: number;
  focal_mm: number;
  filter: string;
  camera_key: string;
  binning: number;
  obj_mag: number;
  seeing: number;
  airmass: number;
  sky_mag: number;
  ap_radius_px: number;
  exptime: number;
  reps: number;
  temp_c: number;
}

interface CalcResults {
  snr: number;
  mag_err: number;
  peak_adu: number;
  image_scale: number;
  fwhm_px: number;
  total_exp_s: number;
  obj_flux: number;
  source_pct: number;
  sky_pct: number;
  read_pct: number;
  dark_pct: number;
  saturated: boolean;
  warn_short: boolean;
}

function aperfrac(aperxfwhm: number): number {
  const sigmas = aperxfwhm * 2.354820 / 2;
  const index = sigmas * 10;
  if (index < 0) return 0;
  if (index >= 59) return 1;
  const i0 = Math.floor(index);
  const frac = index - i0;
  return (1 - frac) * INTEGRAL[i0] + frac * INTEGRAL[i0 + 1];
}

function calcSNR(inp: CalcInputs): CalcResults {
  const cam = CAMERAS[inp.camera_key] ?? CAMERAS.GENERIC;
  const f = inp.filter;

  // Aperture area in cm²
  const r = inp.aperture_mm / 2;
  const area_cm2 = Math.PI * r * r / 100;

  // Image scale arcsec/px
  const scale = (cam.pixel_um * inp.binning) / (inp.focal_mm / 206264.8062) / 1000;

  // Effective zero-mag flux for this telescope + filter + camera
  const trans = (FILTER_TRANS[f] ?? 75) / 100;
  const eff   = (FILTER_EFF[f]   ?? 80) / 100;
  const qe    = ((cam.qe[f] ?? cam.qe.C ?? 50)) / 100;
  const flux0 = (FLUX0F[f] ?? FLUX0F.C) * trans * area_cm2 * eff * qe;

  // Object flux (e-/s) after atmospheric extinction
  const k = EXT_COEFF[f] ?? 0.2;
  const ext_mag = inp.obj_mag + k * inp.airmass;
  const obj_flux = Math.pow(2.5118864315, -ext_mag) * flux0;

  // Sky flux per binned pixel (e-/s)
  const sky_flux = flux0 * Math.pow(10, -0.4 * inp.sky_mag) * scale * scale;

  // Dark current per binned pixel (e-/s)
  const t = inp.temp_c;
  const unbinned_dark = cam.pixel_um * cam.pixel_um * (cam.idark / 16.022) *
    Math.exp(0.69315 * (t - cam.tref) / cam.ddouble);
  const dark_current = unbinned_dark * inp.binning * inp.binning;

  // Gain
  const b = Math.min(Math.max(inp.binning, 1), 4) as 1 | 2 | 3 | 4;
  const gain = cam.gain[b] ?? cam.gain[1];

  // Noise² terms
  const dark_noise  = dark_current * inp.exptime * inp.reps;
  const read_noise  = cam.sensor === 'aps'
    ? cam.rn_e * cam.rn_e * inp.binning * inp.binning * inp.reps
    : cam.rn_e * cam.rn_e * inp.reps;

  // Aperture
  const fwhm_px     = inp.seeing / scale;
  const ap_fwhm     = 2 * inp.ap_radius_px * scale / inp.seeing;
  const ap_frac     = aperfrac(ap_fwhm);
  const ap_area_px  = Math.PI * inp.ap_radius_px * inp.ap_radius_px;

  // Signal
  const signal = obj_flux * inp.exptime * inp.reps * ap_frac;

  // Noise components (in e-²)
  const src_noise = signal;
  const sky_noise = sky_flux * inp.exptime * inp.reps * ap_area_px;
  const rn_noise  = read_noise * ap_area_px;
  const dk_noise  = dark_noise * ap_area_px;

  const snr = signal / Math.sqrt(src_noise + sky_noise + rn_noise + dk_noise);

  // Noise percentages (as sqrt contributions)
  const sn_sqrt = Math.sqrt(src_noise);
  const sk_sqrt = Math.sqrt(sky_noise);
  const rn_sqrt = Math.sqrt(rn_noise);
  const dk_sqrt = Math.sqrt(dk_noise);
  const total   = sn_sqrt + sk_sqrt + rn_sqrt + dk_sqrt;

  // Peak pixel ADU
  const bg_count = (sky_flux + dark_current) * inp.exptime / gain + 100;
  const obj_count = obj_flux * inp.exptime / gain;
  const peak_adu = obj_count / (2 * Math.PI * Math.pow(0.51 * fwhm_px, 2)) + bg_count;

  return {
    snr:         Math.max(0, snr),
    mag_err:     snr > 0 ? 2.5 * Math.log10(1 + 1 / snr) : 99,
    peak_adu,
    image_scale: scale,
    fwhm_px,
    total_exp_s: inp.exptime * inp.reps,
    obj_flux,
    source_pct:  total > 0 ? 100 * sn_sqrt / total : 0,
    sky_pct:     total > 0 ? 100 * sk_sqrt / total : 0,
    read_pct:    total > 0 ? 100 * rn_sqrt / total : 0,
    dark_pct:    total > 0 ? 100 * dk_sqrt / total : 0,
    saturated:   peak_adu > 50000,
    warn_short:  inp.exptime < 10,
  };
}

function findMaxExp(base: CalcInputs): { exptime: number; results: CalcResults } {
  let best = { ...base, exptime: EXP_TIMES[0] };
  let bestRes = calcSNR(best);
  for (const t of EXP_TIMES) {
    const inp = { ...base, exptime: t };
    const res = calcSNR(inp);
    if (res.peak_adu > 30000) break;
    best = inp; bestRes = res;
  }
  return { exptime: best.exptime, results: bestRes };
}

function findTargetSNR(base: CalcInputs, targetSNR: number): { exptime: number; reps: number; results: CalcResults } {
  // First find single-exposure that reaches SNR
  let exptime = EXP_TIMES[0];
  for (const t of EXP_TIMES) {
    exptime = t;
    const res = calcSNR({ ...base, exptime: t, reps: 1 });
    if (res.snr >= targetSNR) return { exptime: t, reps: 1, results: res };
  }
  // Then stack
  for (let reps = 2; reps <= 288; reps++) {
    const res = calcSNR({ ...base, exptime, reps });
    if (res.snr >= targetSNR) return { exptime, reps, results: res };
  }
  const res = calcSNR({ ...base, exptime, reps: 288 });
  return { exptime, reps: 288, results: res };
}

// ─── Component ────────────────────────────────────────────────────────────────

const ALL_FILTERS = ['L', 'R', 'G', 'B', 'Ha', 'OIII', 'SII', 'C', 'V', 'I'];

export function ExposureCalculator() {
  const [telescopes, setTelescopes]   = useState<TelescopeConfig[]>([]);
  const [telId, setTelId]             = useState('');
  const [cameraKey, setCameraKey]     = useState('GENERIC');
  const [filter, setFilter]           = useState('R');
  const [objMag, setObjMag]           = useState(12);
  const [seeing, setSeeing]           = useState(2.5);
  const [airmass, setAirmass]         = useState(1.0);
  const [skyMag, setSkyMag]           = useState(18.0);
  const [exptime, setExptime]         = useState(60);
  const [reps, setReps]               = useState(1);
  const [apRadius, setApRadius]       = useState(10);
  const [tempC, setTempC]             = useState(-20);
  const [targetSNR, setTargetSNR]     = useState(50);
  const [results, setResults]         = useState<CalcResults | null>(null);
  const [solvedExp, setSolvedExp]     = useState<number | null>(null);
  const [solvedReps, setSolvedReps]   = useState<number | null>(null);

  useEffect(() => {
    api.get<TelescopeConfig[]>('/telescopes').then(setTelescopes).catch(console.error);
  }, []);

  const selectedTel = telescopes.find(t => t.id === telId);
  const availableFilters = selectedTel?.filters.length ? selectedTel.filters : ALL_FILTERS;

  // When telescope changes, auto-fill from config
  useEffect(() => {
    if (!selectedTel) return;
    const key = matchCamera(selectedTel.camera);
    setCameraKey(key);
    const cam = CAMERAS[key];
    if (cam) setTempC(cam.temp);
    // Default to first filter that has physics data
    const firstValid = selectedTel.filters.find(f => FLUX0F[f]);
    if (firstValid) setFilter(firstValid);
  }, [telId]);

  const buildInputs = (): CalcInputs => ({
    aperture_mm: selectedTel?.aperture_mm ?? 400,
    focal_mm:    selectedTel?.focal_length_mm ?? 4000,
    filter,
    camera_key:  cameraKey,
    binning:     selectedTel?.min_binning ?? 1,
    obj_mag:     objMag,
    seeing,
    airmass,
    sky_mag:     skyMag,
    ap_radius_px: apRadius,
    exptime,
    reps,
    temp_c:      tempC,
  });

  const cam = CAMERAS[cameraKey] ?? CAMERAS.GENERIC;

  const handleCalc = () => {
    setResults(calcSNR(buildInputs()));
    setSolvedExp(null); setSolvedReps(null);
  };

  const handleFindMax = () => {
    const { exptime: t, results: r } = findMaxExp(buildInputs());
    setExptime(t); setReps(1);
    setResults(r);
    setSolvedExp(t); setSolvedReps(1);
  };

  const handleSolveSNR = () => {
    const { exptime: t, reps: n, results: r } = findTargetSNR(buildInputs(), targetSNR);
    setExptime(t); setReps(n);
    setResults(r);
    setSolvedExp(t); setSolvedReps(n);
  };

  const fmtTime = (s: number) => {
    if (s < 120) return `${s}s`;
    if (s < 7200) return `${(s / 60).toFixed(1)} min`;
    return `${(s / 3600).toFixed(2)} hr`;
  };

  const snrClass = results
    ? results.snr >= 50 ? styles.good : results.snr >= 20 ? styles.ok : styles.poor
    : '';

  return (
    <div>
      <h1 className={styles.heading}>Exposure Calculator</h1>
      <p className={styles.subtext}>
        Estimate SNR and exposure time for point-source photometry.
        Select your telescope and filter, enter the object magnitude, then calculate.
      </p>

      <div className={styles.grid}>
        {/* ── Telescope & Camera ── */}
        <div className={`card ${styles.panel}`}>
          <h2 className={styles.panelTitle}>Telescope & Camera</h2>

          <label className={styles.field}>
            <span>Telescope</span>
            <select value={telId} onChange={e => setTelId(e.target.value)}>
              <option value="">— manual entry —</option>
              {telescopes.map(t => (
                <option key={t.id} value={t.id}>#{t.num} {t.short_name}</option>
              ))}
            </select>
          </label>

          <div className={styles.specRow}>
            <div className={styles.specItem}>
              <span className={styles.specLabel}>Aperture</span>
              <span className={styles.specVal}>{selectedTel?.aperture_mm ?? '—'} mm</span>
            </div>
            <div className={styles.specItem}>
              <span className={styles.specLabel}>Focal length</span>
              <span className={styles.specVal}>{selectedTel?.focal_length_mm ?? '—'} mm</span>
            </div>
            <div className={styles.specItem}>
              <span className={styles.specLabel}>f/ratio</span>
              <span className={styles.specVal}>
                {selectedTel?.aperture_mm && selectedTel?.focal_length_mm
                  ? `f/${(selectedTel.focal_length_mm / selectedTel.aperture_mm).toFixed(1)}`
                  : '—'}
              </span>
            </div>
          </div>

          <label className={styles.field}>
            <span>Camera</span>
            <select value={cameraKey} onChange={e => { setCameraKey(e.target.value); setTempC(CAMERAS[e.target.value]?.temp ?? -20); }}>
              {Object.entries(CAMERAS).map(([k, v]) => (
                <option key={k} value={k}>{v.label}</option>
              ))}
            </select>
          </label>

          <div className={styles.camSpec}>
            <span>Pixel {cam.pixel_um} µm</span>
            <span>RN {cam.rn_e} e⁻</span>
            <span>QE {cam.qe[filter] ?? '—'} %</span>
            <span>{cam.sensor.toUpperCase()}</span>
          </div>

          <label className={styles.field}>
            <span>Sensor temperature (°C)</span>
            <input type="number" value={tempC} onChange={e => setTempC(Number(e.target.value))} />
          </label>
        </div>

        {/* ── Target & Conditions ── */}
        <div className={`card ${styles.panel}`}>
          <h2 className={styles.panelTitle}>Target & Conditions</h2>

          <label className={styles.field}>
            <span>Filter</span>
            <select value={filter} onChange={e => setFilter(e.target.value)}>
              {availableFilters.map(f => <option key={f} value={f}>{f}</option>)}
            </select>
          </label>

          <label className={styles.field}>
            <span>Object magnitude</span>
            <input type="number" step="0.1" value={objMag}
              onChange={e => setObjMag(parseFloat(e.target.value) || 0)} />
          </label>

          <div className={styles.row}>
            <label className={styles.field}>
              <span>Seeing (″ FWHM)</span>
              <input type="number" step="0.1" min="0.5" value={seeing}
                onChange={e => setSeeing(parseFloat(e.target.value) || 1)} />
            </label>
            <label className={styles.field}>
              <span>Airmass</span>
              <input type="number" step="0.1" min="1" value={airmass}
                onChange={e => setAirmass(parseFloat(e.target.value) || 1)} />
            </label>
          </div>

          <label className={styles.field}>
            <span>Sky brightness (mag/″²)</span>
            <input type="number" step="0.5" value={skyMag}
              onChange={e => setSkyMag(parseFloat(e.target.value) || 18)} />
          </label>

          <label className={styles.field}>
            <span>Aperture radius (px)</span>
            <input type="number" min="2" value={apRadius}
              onChange={e => setApRadius(parseInt(e.target.value) || 10)} />
          </label>
        </div>

        {/* ── Exposure ── */}
        <div className={`card ${styles.panel}`}>
          <h2 className={styles.panelTitle}>Exposure</h2>

          <div className={styles.row}>
            <label className={styles.field}>
              <span>Exposure time (s)</span>
              <input type="number" min="1" max="3600" value={exptime}
                onChange={e => setExptime(parseInt(e.target.value) || 60)} />
            </label>
            <label className={styles.field}>
              <span>Repeats (stacked)</span>
              <input type="number" min="1" value={reps}
                onChange={e => setReps(parseInt(e.target.value) || 1)} />
            </label>
          </div>

          <button className="btn btn-primary" style={{ width: '100%', justifyContent: 'center', marginTop: '0.5rem' }}
            onClick={handleCalc}>
            Calculate SNR
          </button>

          <div className={styles.divider} />

          <div className={styles.solverRow}>
            <button className="btn btn-secondary" onClick={handleFindMax}>
              Find max exposure
            </button>
            <span className={styles.solverNote}>before saturation</span>
          </div>

          <div className={styles.solverRow}>
            <label className={styles.inlineField}>
              <span>Target SNR</span>
              <input type="number" min="1" value={targetSNR}
                onChange={e => setTargetSNR(parseInt(e.target.value) || 50)} />
            </label>
            <button className="btn btn-secondary" onClick={handleSolveSNR}>
              Solve to SNR
            </button>
          </div>

          {(solvedExp !== null && solvedReps !== null) && (
            <div className={styles.solvedBanner}>
              Solved: <strong>{solvedExp}s × {solvedReps}</strong> = {fmtTime(solvedExp * solvedReps)}
            </div>
          )}
        </div>
      </div>

      {/* ── Results ── */}
      {results && (
        <div className={`card ${styles.results}`}>
          <h2 className={styles.panelTitle}>Results</h2>

          {results.saturated && (
            <div className={styles.warnBanner}>
              Peak pixel ({results.peak_adu.toFixed(0)} ADU) exceeds 50 000 — image will saturate.
            </div>
          )}
          {!results.saturated && results.peak_adu > 40000 && (
            <div className={styles.warnBanner}>
              Peak pixel ({results.peak_adu.toFixed(0)} ADU) is close to saturation (&gt;40 000).
            </div>
          )}
          {results.warn_short && (
            <div className={styles.infoBanner}>
              Short exposures (&lt;10 s) may not capture enough stars for plate solving.
            </div>
          )}

          <div className={styles.resultGrid}>
            <div className={`${styles.metric} ${snrClass}`}>
              <span className={styles.metricVal}>{results.snr.toFixed(1)}</span>
              <span className={styles.metricLbl}>SNR</span>
            </div>
            <div className={styles.metric}>
              <span className={styles.metricVal}>±{results.mag_err.toFixed(3)}</span>
              <span className={styles.metricLbl}>mag uncertainty</span>
            </div>
            <div className={styles.metric}>
              <span className={styles.metricVal}>{fmtTime(results.total_exp_s)}</span>
              <span className={styles.metricLbl}>total exposure</span>
            </div>
            <div className={styles.metric}>
              <span className={styles.metricVal}>{results.image_scale.toFixed(3)}″</span>
              <span className={styles.metricLbl}>image scale/px</span>
            </div>
            <div className={styles.metric}>
              <span className={styles.metricVal}>{results.fwhm_px.toFixed(1)} px</span>
              <span className={styles.metricLbl}>FWHM</span>
            </div>
            <div className={`${styles.metric} ${results.saturated ? styles.poor : results.peak_adu > 40000 ? styles.ok : ''}`}>
              <span className={styles.metricVal}>{results.peak_adu.toFixed(0)}</span>
              <span className={styles.metricLbl}>peak ADU</span>
            </div>
          </div>

          <div className={styles.noiseSection}>
            <span className={styles.noiseTitle}>Noise budget</span>
            <div className={styles.noiseBar}>
              <div className={`${styles.noiseSeg} ${styles.noiseSource}`} style={{ width: `${results.source_pct}%` }} title={`Source: ${results.source_pct.toFixed(0)}%`} />
              <div className={`${styles.noiseSeg} ${styles.noiseSky}`}    style={{ width: `${results.sky_pct}%` }}    title={`Sky: ${results.sky_pct.toFixed(0)}%`} />
              <div className={`${styles.noiseSeg} ${styles.noiseRead}`}   style={{ width: `${results.read_pct}%` }}   title={`Read: ${results.read_pct.toFixed(0)}%`} />
              <div className={`${styles.noiseSeg} ${styles.noiseDark}`}   style={{ width: `${results.dark_pct}%` }}   title={`Dark: ${results.dark_pct.toFixed(0)}%`} />
            </div>
            <div className={styles.noiseLegend}>
              <span><span className={`${styles.dot} ${styles.noiseSource}`}/>Source {results.source_pct.toFixed(0)}%</span>
              <span><span className={`${styles.dot} ${styles.noiseSky}`}/>Sky {results.sky_pct.toFixed(0)}%</span>
              <span><span className={`${styles.dot} ${styles.noiseRead}`}/>Read {results.read_pct.toFixed(0)}%</span>
              <span><span className={`${styles.dot} ${styles.noiseDark}`}/>Dark {results.dark_pct.toFixed(0)}%</span>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
