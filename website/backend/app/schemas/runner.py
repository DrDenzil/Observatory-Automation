from datetime import datetime

from pydantic import BaseModel


# ---- Heartbeat (runner -> web) ----

class HeartbeatIn(BaseModel):
    scope_id: str
    name: str | None = None
    state: str = "idle"
    current_job_id: str | None = None
    progress_step: str | None = None
    progress_message: str | None = None
    kstars_running: bool = False
    indi_running: bool = False
    network_connected: bool = True
    weather_safe: bool | None = None
    weather_message: str | None = None


# ---- Job bundle (web -> runner) ----

class BundleTarget(BaseModel):
    target_name: str
    ra: float
    dec: float
    filters: list[str]
    exposure_seconds: float
    count: int
    binning: int


class JobBundle(BaseModel):
    """Everything a runner needs to build an EKOS sequence + scheduler."""
    job_id: str
    queue_ref: str
    scope_id: str
    ekos_profile: str
    project_name: str
    priority: int
    targets: list[BundleTarget]


# ---- Progress report (runner -> web) ----

class ProgressIn(BaseModel):
    status: str | None = None          # running | completed | failed | weather_abort
    progress_step: str | None = None
    progress_message: str | None = None
    error_message: str | None = None


# ---- Scope status (web -> dashboard) ----

class ScopeOut(BaseModel):
    id: str
    name: str | None
    state: str
    current_job_id: str | None
    progress_step: str | None
    progress_message: str | None
    kstars_running: bool
    indi_running: bool
    network_connected: bool
    weather_safe: bool | None
    weather_message: str | None
    last_heartbeat: datetime | None
    online: bool

    model_config = {"from_attributes": True}
