"""Staff-facing scope status. Reads the heartbeat-updated scopes table."""

from datetime import datetime, timedelta, timezone
UTC = timezone.utc

from fastapi import APIRouter, Depends
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.config import settings
from app.database import get_db
from app.models.scope import Scope
from app.models.user import User
from app.schemas.runner import ScopeOut
from app.services.auth import require_role

router = APIRouter(prefix="/api/scopes", tags=["scopes"])


def _to_out(scope: Scope, now: datetime) -> ScopeOut:
    online = (
        scope.last_heartbeat is not None
        and (now - _aware(scope.last_heartbeat))
        < timedelta(seconds=settings.scope_offline_after_seconds)
    )
    return ScopeOut(
        id=scope.id,
        name=scope.name,
        state=scope.state if online else "offline",
        current_job_id=scope.current_job_id,
        progress_step=scope.progress_step,
        progress_message=scope.progress_message,
        kstars_running=scope.kstars_running and online,
        indi_running=scope.indi_running and online,
        network_connected=scope.network_connected and online,
        weather_safe=scope.weather_safe if online else None,
        weather_message=scope.weather_message if online else None,
        # Emit tz-aware UTC so browsers don't misparse naive timestamps as local.
        last_heartbeat=_aware(scope.last_heartbeat) if scope.last_heartbeat else None,
        online=online,
    )


def _aware(dt: datetime) -> datetime:
    """SQLite stores naive datetimes; treat them as UTC for comparison."""
    return dt if dt.tzinfo else dt.replace(tzinfo=UTC)


@router.get("", response_model=list[ScopeOut])
async def list_scopes(
    db: AsyncSession = Depends(get_db),
    user: User = Depends(require_role("staff", "admin")),
):
    now = datetime.now(UTC)
    result = await db.execute(select(Scope).order_by(Scope.id))
    return [_to_out(s, now) for s in result.scalars()]
