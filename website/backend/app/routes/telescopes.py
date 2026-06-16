"""Telescope configuration (admin-editable).

Read access for any authenticated user (the request form and exposure calculator
consume this). Write access (create/update/delete) is admin-only.
"""

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.models.telescope import TelescopeConfig
from app.models.user import User
from app.schemas.telescope import TelescopeCreate, TelescopeOut, TelescopeUpdate
from app.services.auth import get_current_user, require_role

router = APIRouter(prefix="/api/telescopes", tags=["telescopes"])


@router.get("", response_model=list[TelescopeOut])
async def list_telescopes(
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    result = await db.execute(select(TelescopeConfig).order_by(TelescopeConfig.num))
    return list(result.scalars())


@router.get("/{telescope_id}", response_model=TelescopeOut)
async def get_telescope(
    telescope_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(get_current_user),
):
    tel = await db.get(TelescopeConfig, telescope_id)
    if tel is None:
        raise HTTPException(status_code=404, detail="Telescope not found")
    return tel


@router.post("", response_model=TelescopeOut, status_code=201)
async def create_telescope(
    body: TelescopeCreate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    existing = await db.execute(
        select(TelescopeConfig).where(TelescopeConfig.num == body.num)
    )
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=409, detail=f"Telescope number {body.num} already exists")

    tel = TelescopeConfig(**body.model_dump())
    db.add(tel)
    await db.commit()
    await db.refresh(tel)
    return tel


@router.patch("/{telescope_id}", response_model=TelescopeOut)
async def update_telescope(
    telescope_id: str,
    body: TelescopeUpdate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    tel = await db.get(TelescopeConfig, telescope_id)
    if tel is None:
        raise HTTPException(status_code=404, detail="Telescope not found")

    updates = body.model_dump(exclude_unset=True)
    if "num" in updates and updates["num"] != tel.num:
        clash = await db.execute(
            select(TelescopeConfig).where(TelescopeConfig.num == updates["num"])
        )
        if clash.scalar_one_or_none():
            raise HTTPException(status_code=409, detail=f"Telescope number {updates['num']} already exists")

    for key, value in updates.items():
        setattr(tel, key, value)
    await db.commit()
    await db.refresh(tel)
    return tel


@router.delete("/{telescope_id}", status_code=204)
async def delete_telescope(
    telescope_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    tel = await db.get(TelescopeConfig, telescope_id)
    if tel is None:
        raise HTTPException(status_code=404, detail="Telescope not found")
    await db.delete(tel)
    await db.commit()
