from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy import select, or_
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.models.user import User
from app.schemas.user import UserAdminCreate, UserOut, UserUpdate
from app.services.auth import hash_password, require_role

router = APIRouter(prefix="/api/users", tags=["users"])


@router.get("", response_model=list[UserOut])
async def list_users(
    search: str | None = Query(None, min_length=1),
    role: str | None = None,
    user_type: str | None = None,
    is_active: bool | None = None,
    limit: int = Query(100, le=500),
    offset: int = Query(0, ge=0),
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """List users with optional filtering. Admin only."""
    query = select(User).order_by(User.created_at.desc()).limit(limit).offset(offset)

    if search:
        query = query.where(
            or_(
                User.email.ilike(f"%{search}%"),
                User.name.ilike(f"%{search}%"),
            )
        )
    if role:
        query = query.where(User.role == role)
    if user_type:
        query = query.where(User.user_type == user_type)
    if is_active is not None:
        query = query.where(User.is_active == is_active)

    result = await db.execute(query)
    return [UserOut.model_validate(u) for u in result.scalars()]


@router.get("/{user_id}", response_model=UserOut)
async def get_user(
    user_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """Get a single user. Admin only."""
    target = await db.get(User, user_id)
    if not target:
        raise HTTPException(status_code=404, detail="User not found")
    return UserOut.model_validate(target)


@router.post("", response_model=UserOut, status_code=status.HTTP_201_CREATED)
async def create_user(
    body: UserAdminCreate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """Create a new user manually. Admin only. Used for external partners or bulk imports."""
    existing = await db.execute(select(User).where(User.email == body.email))
    if existing.scalar_one_or_none():
        raise HTTPException(status_code=409, detail="Email already exists")

    if body.legacy_id:
        legacy_check = await db.execute(select(User).where(User.legacy_id == body.legacy_id))
        if legacy_check.scalar_one_or_none():
            raise HTTPException(status_code=409, detail="Legacy ID already exists")

    new_user = User(
        email=body.email,
        name=body.name,
        role=body.role,
        user_type=body.user_type,
        legacy_id=body.legacy_id,
        department=body.department,
        hashed_password="",  # No password for manually-created users; OAuth or admin reset
    )
    db.add(new_user)
    await db.commit()
    await db.refresh(new_user)
    return UserOut.model_validate(new_user)


@router.patch("/{user_id}", response_model=UserOut)
async def update_user(
    user_id: str,
    body: UserUpdate,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """Update a user. Admin only."""
    target = await db.get(User, user_id)
    if not target:
        raise HTTPException(status_code=404, detail="User not found")

    if body.name is not None:
        target.name = body.name
    if body.role is not None:
        target.role = body.role
    if body.user_type is not None:
        target.user_type = body.user_type
    if body.is_active is not None:
        target.is_active = body.is_active
    if body.department is not None:
        target.department = body.department

    await db.commit()
    await db.refresh(target)
    return UserOut.model_validate(target)


@router.delete("/{user_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_user(
    user_id: str,
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """Soft-delete a user (mark as inactive). Admin only."""
    target = await db.get(User, user_id)
    if not target:
        raise HTTPException(status_code=404, detail="User not found")

    if target.id == user.id:
        raise HTTPException(status_code=409, detail="Cannot delete yourself")

    target.is_active = False
    await db.commit()
