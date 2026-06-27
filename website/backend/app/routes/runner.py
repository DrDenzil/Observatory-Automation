"""Runner-facing API.

Telescope runner agents authenticate with the X-Runner-Key header and use
these endpoints to:
  - send heartbeats (so the dashboard can show live scope status)
  - claim the next queued job for their scope
  - report progress / completion

All job handoff flows through here, so the runner never touches the DB directly.
"""

from datetime import datetime, timezone
UTC = timezone.utc

from fastapi import APIRouter, Depends, HTTPException, Query, Request
from sqlalchemy import select, or_
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.database import get_db
from app.models.job import Job
from app.models.request import ObservationRequest
from app.models.scope import Scope
from app.schemas.runner import (
    BundleTarget,
    HeartbeatIn,
    JobBundle,
    ProgressIn,
)
from app.services.auth import require_runner

router = APIRouter(prefix="/api/runner", tags=["runner"], dependencies=[Depends(require_runner)])


def _make_queue_ref(job: Job) -> str:
    stamp = datetime.now(UTC).strftime("%Y%m%dT%H%M%SZ")
    return f"ekos_{job.id[:8]}_{stamp}"


@router.post("/heartbeat")
async def heartbeat(body: HeartbeatIn, request: Request, db: AsyncSession = Depends(get_db)):
    """Upsert a scope's live status. Auto-registers the scope on first contact."""
    scope = await db.get(Scope, body.scope_id)
    if scope is None:
        scope = Scope(id=body.scope_id)
        db.add(scope)

    scope.name = body.name or scope.name
    scope.state = body.state
    scope.current_job_id = body.current_job_id
    scope.progress_step = body.progress_step
    scope.progress_message = body.progress_message
    scope.kstars_running = body.kstars_running
    scope.indi_running = body.indi_running
    scope.network_connected = body.network_connected
    if body.weather_safe is not None:
        scope.weather_safe = body.weather_safe
    if body.weather_message:
        scope.weather_message = body.weather_message
    scope.webcam_available = body.webcam_available
    scope.arduino_available = body.arduino_available
    scope.last_ip = request.client.host if request.client else None
    scope.last_heartbeat = datetime.now(UTC)

    await db.commit()
    return {"status": "ok"}


@router.get("/jobs/next", response_model=JobBundle | None)
async def claim_next_job(
    scope_id: str = Query(...),
    db: AsyncSession = Depends(get_db),
):
    """Atomically claim the next queued job for this scope and return its bundle.

    Picks jobs explicitly assigned to this scope, or unassigned jobs, ordered by
    request priority (desc) then age (oldest first). Returns null (204-ish) when
    the queue is empty or automation is disabled for this scope.
    """
    scope = await db.get(Scope, scope_id)
    if scope is not None and not scope.automation_enabled:
        return None

    query = (
        select(Job)
        .options(
            selectinload(Job.request).selectinload(ObservationRequest.targets),
        )
        .where(Job.status == "queued")
        .where(or_(Job.scope_id == scope_id, Job.scope_id.is_(None)))
        .join(Job.request)
        .order_by(ObservationRequest.priority.desc(), Job.created_at.asc())
        .limit(1)
    )
    result = await db.execute(query)
    job = result.scalar_one_or_none()
    if job is None:
        return None

    # Claim it
    job.scope_id = scope_id
    job.status = "running"
    job.started_at = datetime.now(UTC)
    if not job.queue_ref:
        job.queue_ref = _make_queue_ref(job)
    await db.commit()
    await db.refresh(job)

    req = job.request
    targets = [
        BundleTarget(
            target_name=t.target_name,
            ra=t.ra,
            dec=t.dec,
            filters=t.filters,
            exposure_seconds=t.exposure_seconds,
            count=t.count,
            binning=t.binning,
        )
        for t in req.targets
    ]
    return JobBundle(
        job_id=job.id,
        queue_ref=job.queue_ref,
        scope_id=scope_id,
        ekos_profile=scope_id,
        project_name=req.project_name,
        priority=req.priority,
        targets=targets,
    )


@router.post("/jobs/{job_id}/progress")
async def report_progress(
    job_id: str,
    body: ProgressIn,
    db: AsyncSession = Depends(get_db),
):
    """Update a job's status/progress. The runner calls this throughout a run."""
    result = await db.execute(
        select(Job).options(selectinload(Job.request)).where(Job.id == job_id)
    )
    job = result.scalar_one_or_none()
    if job is None:
        raise HTTPException(status_code=404, detail="Job not found")

    if body.status:
        if body.status == "weather_abort":
            # Requeue so the job is re-claimed when conditions clear.
            # Leave the request as 'approved' — no staff intervention needed.
            job.status = "queued"
            job.scope_id = None
            job.started_at = None
        else:
            job.status = body.status
            if body.status == "running" and not job.started_at:
                job.started_at = datetime.now(UTC)
            elif body.status == "completed":
                job.completed_at = datetime.now(UTC)
            elif body.status == "failed":
                job.completed_at = datetime.now(UTC)
                # Return the request to 'submitted' so staff can review and re-approve.
                job.request.status = "submitted"
    if body.error_message is not None:
        job.error_message = body.error_message

    await db.commit()
    return {"status": "ok"}
