from datetime import datetime, timezone
UTC = timezone.utc

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy import select, func
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.database import get_db
from app.models.request import ObservationRequest, RequestTarget
from app.models.job import Job
from app.models.user import User
from app.models.telescope import TelescopeConfig
from app.schemas.request import RequestCreate, RequestOut, RequestReject, RequestUpdate
from app.services.auth import get_current_user, require_role

router = APIRouter(prefix="/api/requests", tags=["requests"])


def _to_out(req: ObservationRequest) -> RequestOut:
    telescope = req.telescope
    telescope_name = None
    if telescope:
        telescope_name = f"#{telescope.num} {telescope.short_name}"
    return RequestOut(
        id=req.id,
        user_id=req.user_id,
        project_name=req.project_name,
        description=req.description,
        status=req.status,
        priority=req.priority,
        created_at=req.created_at,
        submitted_at=req.submitted_at,
        approved_at=req.approved_at,
        rejected_reason=req.rejected_reason,
        telescope_id=req.telescope_id,
        telescope_name=telescope_name,
        targets=req.targets,
        user_name=req.user.name if req.user else None,
        approver_name=req.approver.name if req.approver else None,
    )


@router.post("", response_model=RequestOut, status_code=status.HTTP_201_CREATED)
async def create_request(
    body: RequestCreate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    tel = await db.get(TelescopeConfig, body.telescope_id)
    if not tel:
        raise HTTPException(status_code=422, detail="Telescope not found")

    status_val = "submitted" if body.submit else "draft"
    submitted_at = datetime.now(UTC) if body.submit else None

    req = ObservationRequest(
        user_id=user.id,
        project_name=body.project_name,
        description=body.description,
        priority=body.priority,
        telescope_id=body.telescope_id,
        status=status_val,
        submitted_at=submitted_at,
    )
    for t in body.targets:
        req.targets.append(RequestTarget(
            target_name=t.target_name,
            ra=t.ra,
            dec=t.dec,
            filters=t.filters,
            exposure_seconds=t.exposure_seconds,
            count=t.count,
            binning=t.binning,
        ))
    db.add(req)
    await db.commit()

    result = await db.execute(
        select(ObservationRequest)
        .options(
            selectinload(ObservationRequest.targets),
            selectinload(ObservationRequest.user),
            selectinload(ObservationRequest.approver),
            selectinload(ObservationRequest.telescope),
        )
        .where(ObservationRequest.id == req.id)
    )
    return _to_out(result.scalar_one())


@router.get("", response_model=list[RequestOut])
async def list_requests(
    status_filter: str | None = Query(None, alias="status"),
    limit: int = Query(50, le=200),
    offset: int = Query(0, ge=0),
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    query = (
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .order_by(ObservationRequest.created_at.desc())
        .limit(limit)
        .offset(offset)
    )
    if user.role == "observer":
        query = query.where(ObservationRequest.user_id == user.id)
    if status_filter:
        query = query.where(ObservationRequest.status == status_filter)

    result = await db.execute(query)
    return [_to_out(r) for r in result.scalars()]


@router.get("/{request_id}", response_model=RequestOut)
async def get_request(
    request_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    result = await db.execute(
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .where(ObservationRequest.id == request_id)
    )
    req = result.scalar_one_or_none()
    if not req:
        raise HTTPException(status_code=404, detail="Request not found")
    if user.role == "observer" and req.user_id != user.id:
        raise HTTPException(status_code=403, detail="Not your request")
    return _to_out(req)


@router.patch("/{request_id}", response_model=RequestOut)
async def update_request(
    request_id: str,
    body: RequestUpdate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    """Update a draft request. Only observers can update their own drafts."""
    result = await db.execute(
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .where(ObservationRequest.id == request_id)
    )
    req = result.scalar_one_or_none()
    if not req:
        raise HTTPException(status_code=404, detail="Request not found")
    if req.user_id != user.id:
        raise HTTPException(status_code=403, detail="Not your request")
    if req.status != "draft":
        raise HTTPException(status_code=400, detail=f"Cannot edit request in '{req.status}' status")

    if body.project_name is not None:
        req.project_name = body.project_name
    if body.description is not None:
        req.description = body.description
    if body.priority is not None:
        req.priority = body.priority
    if body.telescope_id is not None:
        tel = await db.get(TelescopeConfig, body.telescope_id)
        if not tel:
            raise HTTPException(status_code=422, detail="Telescope not found")
        req.telescope_id = body.telescope_id

    if body.targets is not None:
        req.targets.clear()
        for t in body.targets:
            req.targets.append(RequestTarget(
                target_name=t.target_name,
                ra=t.ra,
                dec=t.dec,
                filters=t.filters,
                exposure_seconds=t.exposure_seconds,
                count=t.count,
                binning=t.binning,
            ))

    await db.commit()
    await db.refresh(req)
    return _to_out(req)


@router.post("/{request_id}/submit", response_model=RequestOut)
async def submit_request(
    request_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    """Submit a draft request (changes status from draft → submitted)."""
    result = await db.execute(
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .where(ObservationRequest.id == request_id)
    )
    req = result.scalar_one_or_none()
    if not req:
        raise HTTPException(status_code=404, detail="Request not found")
    if req.user_id != user.id:
        raise HTTPException(status_code=403, detail="Not your request")
    if req.status != "draft":
        raise HTTPException(status_code=400, detail=f"Cannot submit request in '{req.status}' status")
    if not req.targets:
        raise HTTPException(status_code=400, detail="Request must have at least one target")

    req.status = "submitted"
    req.submitted_at = datetime.now(UTC)
    await db.commit()
    await db.refresh(req)
    return _to_out(req)


@router.post("/{request_id}/approve", response_model=RequestOut)
async def approve_request(
    request_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    result = await db.execute(
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .where(ObservationRequest.id == request_id)
    )
    req = result.scalar_one_or_none()
    if not req:
        raise HTTPException(status_code=404, detail="Request not found")
    if req.status != "submitted":
        raise HTTPException(status_code=400, detail=f"Cannot approve request in '{req.status}' status")

    req.status = "approved"
    req.approved_by = user.id
    req.approved_at = datetime.now(UTC)

    job = Job(
        request_id=req.id,
        status="queued",
        scope_id=req.telescope.scope_id if req.telescope else None,
    )
    db.add(job)

    await db.commit()
    await db.refresh(req)
    return _to_out(req)


@router.post("/{request_id}/reject", response_model=RequestOut)
async def reject_request(
    request_id: str,
    body: RequestReject,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    result = await db.execute(
        select(ObservationRequest)
        .options(selectinload(ObservationRequest.targets), selectinload(ObservationRequest.user), selectinload(ObservationRequest.approver), selectinload(ObservationRequest.telescope))
        .where(ObservationRequest.id == request_id)
    )
    req = result.scalar_one_or_none()
    if not req:
        raise HTTPException(status_code=404, detail="Request not found")
    if req.status != "submitted":
        raise HTTPException(status_code=400, detail=f"Cannot reject request in '{req.status}' status")

    req.status = "rejected"
    req.approved_by = user.id
    req.approved_at = datetime.now(UTC)
    req.rejected_reason = body.reason
    await db.commit()
    await db.refresh(req)
    return _to_out(req)
