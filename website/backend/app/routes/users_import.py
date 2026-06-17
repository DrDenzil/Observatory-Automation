"""Bulk user import from legacy system (CSV)."""

import csv
import io
from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, status
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.models.user import User
from app.services.auth import require_role

router = APIRouter(prefix="/api/admin/import", tags=["admin"])


@router.post("/users-csv")
async def import_users_csv(
    file: UploadFile = File(...),
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("admin")),
):
    """
    Bulk import users from CSV.
    Expected columns: user_id, name, email, account_level, user_type
    account_level maps to role: Administrator → admin, Student → observer
    """
    if not file.filename.endswith('.csv'):
        raise HTTPException(status_code=400, detail="File must be CSV")

    try:
        contents = await file.read()
        text = contents.decode('utf-8')
        reader = csv.DictReader(io.StringIO(text))

        if not reader.fieldnames or not all(f in reader.fieldnames for f in ['user_id', 'name', 'email']):
            raise HTTPException(
                status_code=400,
                detail="CSV must have columns: user_id, name, email (and optionally account_level, user_type)"
            )

        imported = 0
        skipped = 0
        errors = []

        for i, row in enumerate(reader, start=2):  # start=2 because row 1 is header
            try:
                legacy_id = int(row['user_id'])
                name = row['name'].strip()
                email = row['email'].strip().lower()

                if not name or not email:
                    errors.append(f"Row {i}: missing name or email")
                    skipped += 1
                    continue

                # Check if already exists
                existing = await db.execute(
                    select(User).where((User.email == email) | (User.legacy_id == legacy_id))
                )
                if existing.scalar_one_or_none():
                    skipped += 1
                    continue

                # Map role
                account_level = row.get('account_level', 'Student').lower()
                role = 'admin' if 'administrator' in account_level else 'observer'
                user_type = row.get('user_type', 'student').lower()

                new_user = User(
                    legacy_id=legacy_id,
                    email=email,
                    name=name,
                    role=role,
                    user_type=user_type,
                    is_active=True,
                    hashed_password="",  # No password; use OAuth or admin reset
                )
                db.add(new_user)
                imported += 1

            except (ValueError, KeyError) as e:
                errors.append(f"Row {i}: {str(e)}")
                skipped += 1

        await db.commit()

        return {
            "imported": imported,
            "skipped": skipped,
            "errors": errors[:10],  # Return first 10 errors
        }

    except UnicodeDecodeError:
        raise HTTPException(status_code=400, detail="File must be valid UTF-8 CSV")
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"CSV processing error: {str(e)}")
