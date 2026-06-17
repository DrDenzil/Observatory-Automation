export interface User {
  id: string;
  legacy_id: number | null;
  email: string;
  name: string;
  role: 'observer' | 'staff' | 'admin';
  user_type: 'student' | 'staff' | 'external';
  is_active: boolean;
  department: string | null;
  created_at: string;
  updated_at: string;
}

export interface Target {
  id: string;
  target_name: string;
  ra: number;
  dec: number;
  filters: string[];
  exposure_seconds: number;
  count: number;
  binning: number;
}

export interface ObservationRequest {
  id: string;
  user_id: string;
  project_name: string;
  description: string | null;
  status: 'draft' | 'submitted' | 'approved' | 'rejected';
  priority: number;
  created_at: string;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_reason: string | null;
  telescope_id: string | null;
  telescope_name: string | null;
  targets: Target[];
  user_name: string | null;
  approver_name: string | null;
}

export interface TargetInput {
  target_name: string;
  ra: number;
  dec: number;
  filters: string[];
  exposure_seconds: number;
  count: number;
  binning: number;
}

export interface RequestInput {
  project_name: string;
  description?: string;
  priority?: number;
  telescope_id: string;
  targets: TargetInput[];
}

export interface Job {
  id: string;
  request_id: string;
  queue_ref: string | null;
  scope_id: string | null;
  status: 'queued' | 'running' | 'completed' | 'failed';
  created_at: string;
  started_at: string | null;
  completed_at: string | null;
  error_message: string | null;
  project_name: string | null;
  user_name: string | null;
  target_summary: string | null;
}

export interface Scope {
  id: string;
  name: string | null;
  state: 'idle' | 'fetching' | 'processing' | 'executing' | 'uploading' | 'failed' | 'offline';
  current_job_id: string | null;
  progress_step: string | null;
  progress_message: string | null;
  kstars_running: boolean;
  indi_running: boolean;
  network_connected: boolean;
  last_heartbeat: string | null;
  online: boolean;
}

export type TelescopeStatus = 'manual' | 'maintenance' | 'automatic';

export interface TelescopeConfig {
  id: string;
  num: number;
  short_name: string;
  telescope: string;
  aperture_mm: number | null;
  focal_length_mm: number | null;
  camera: string | null;
  pixel_width_um: number | null;
  fov_w_arcmin: number | null;
  fov_h_arcmin: number | null;
  filters: string[];
  dec_lower: number | null;
  dec_upper: number | null;
  min_binning: number;
  status: TelescopeStatus;
  status_reason: string | null;
  scope_id: string | null;
  created_at: string;
  updated_at: string;
}

export type TelescopeInput = Omit<TelescopeConfig, 'id' | 'created_at' | 'updated_at'>;
