from datetime import datetime, timedelta, timezone
UTC = timezone.utc

from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.config import settings
from app.database import get_db
from app.models.job import Job
from app.models.request import ObservationRequest
from app.models.scope import Scope
from app.models.user import User
from app.schemas.job import JobDispatch, JobOut, JobUpdate
from app.services.auth import get_current_user, require_role

router = APIRouter(prefix="/api/jobs", tags=["jobs"])


def _to_out(job: Job) -> JobOut:
    req = job.request
    targets = req.targets if req else []
    target_summary = ", ".join(t.target_name for t in targets) if targets else None
    return JobOut(
        id=job.id,
        request_id=job.request_id,
        queue_ref=job.queue_ref,
        scope_id=job.scope_id,
        status=job.status,
        created_at=job.created_at,
        started_at=job.started_at,
        completed_at=job.completed_at,
        error_message=job.error_message,
        project_name=req.project_name if req else None,
        user_name=req.user.name if req and req.user else None,
        target_summary=target_summary,
    )


@router.get("", response_model=list[JobOut])
async def list_jobs(
    status_filter: str | None = Query(None, alias="status"),
    scope_id: str | None = Query(None),
    limit: int = Query(50, le=200),
    offset: int = Query(0, ge=0),
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    query = (
        select(Job)
        .options(
            selectinload(Job.request).selectinload(ObservationRequest.targets),
            selectinload(Job.request).selectinload(ObservationRequest.user),
        )
        .order_by(Job.created_at.desc())
        .limit(limit)
        .offset(offset)
    )
    if status_filter:
        query = query.where(Job.status == status_filter)
    if scope_id:
        query = query.where(Job.scope_id == scope_id)

    result = await db.execute(query)
    return [_to_out(j) for j in result.scalars()]


@router.get("/{job_id}", response_model=JobOut)
async def get_job(
    job_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    result = await db.execute(
        select(Job)
        .options(
            selectinload(Job.request).selectinload(ObservationRequest.targets),
            selectinload(Job.request).selectinload(ObservationRequest.user),
        )
        .where(Job.id == job_id)
    )
    job = result.scalar_one_or_none()
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")
    return _to_out(job)


@router.post("/{job_id}/dispatch", response_model=JobOut)
async def dispatch_job(
    job_id: str,
    body: JobDispatch,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    """Pin a queued job to a specific scope so that runner picks it up next poll."""
    result = await db.execute(
        select(Job)
        .options(
            selectinload(Job.request).selectinload(ObservationRequest.targets),
            selectinload(Job.request).selectinload(ObservationRequest.user),
        )
        .where(Job.id == job_id)
    )
    job = result.scalar_one_or_none()
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")
    if job.status != "queued":
        raise HTTPException(status_code=409, detail=f"Job is not queued (status: {job.status})")

    scope = await db.get(Scope, body.scope_id)
    if not scope:
        raise HTTPException(status_code=404, detail="Scope not found")

    now = datetime.now(UTC)
    hb = scope.last_heartbeat
    if hb and hb.tzinfo is None:
        hb = hb.replace(tzinfo=UTC)
    if not hb or (now - hb) > timedelta(seconds=settings.scope_offline_after_seconds):
        raise HTTPException(status_code=409, detail="Scope is offline")
    if scope.state != "idle":
        raise HTTPException(status_code=409, detail=f"Scope is not idle (state: {scope.state})")

    job.scope_id = body.scope_id
    await db.commit()
    await db.refresh(job)
    return _to_out(job)


@router.patch("/{job_id}", response_model=JobOut)
async def update_job(
    job_id: str,
    body: JobUpdate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    result = await db.execute(
        select(Job)
        .options(
            selectinload(Job.request).selectinload(ObservationRequest.targets),
            selectinload(Job.request).selectinload(ObservationRequest.user),
        )
        .where(Job.id == job_id)
    )
    job = result.scalar_one_or_none()
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")

    if body.status:
        job.status = body.status
        if body.status == "running" and not job.started_at:
            job.started_at = datetime.now(UTC)
        elif body.status in ("completed", "failed"):
            job.completed_at = datetime.now(UTC)
    if body.scope_id is not None:
        job.scope_id = body.scope_id
    if body.error_message is not None:
        job.error_message = body.error_message

    await db.commit()
    await db.refresh(job)
    return _to_out(job)
