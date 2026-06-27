"""Webcam proxy routes.

The Go runner serves MJPEG on its own port (8765). This module proxies that
stream through the FastAPI backend so the browser only needs to talk to one
origin (no CORS, no direct access to runner IPs from the browser).

Endpoints:
  GET /api/scopes/{scope_id}/webcam/stream   — MJPEG proxy (multipart)
  GET /api/scopes/{scope_id}/webcam/snapshot — single JPEG frame
  GET /api/scopes/{scope_id}/webcam/status   — JSON availability check
"""

import httpx
from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import StreamingResponse, Response
from jose import JWTError, jwt

from app.config import settings
from app.database import get_db
from app.models.scope import Scope
from app.models.user import User
from app.services.auth import require_role, ALGORITHM
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

_require_staff = require_role("staff", "admin")

router = APIRouter(prefix="/api/scopes", tags=["webcam"])


async def _require_token(token: str = Query(..., alias="token"), db: AsyncSession = Depends(get_db)) -> User:
    """Accept a JWT passed as ?token= query param (needed for <img> and MJPEG streams)."""
    try:
        payload = jwt.decode(token, settings.secret_key, algorithms=[ALGORITHM])
        user_id: str | None = payload.get("sub")
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid token")
    except JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
    result = await db.execute(select(User).where(User.id == user_id))
    user = result.scalar_one_or_none()
    if not user:
        raise HTTPException(status_code=401, detail="User not found")
    return user

WEBCAM_PORT = 8765
STREAM_PATH = "/webcam/stream"
SNAPSHOT_PATH = "/webcam/snapshot"
STATUS_PATH = "/webcam/status"
IR_PATH = "/arduino/ir"


async def _runner_url(scope_id: str, path: str, db: AsyncSession, check_webcam: bool = True) -> str:
    scope: Scope | None = await db.get(Scope, scope_id)
    if scope is None:
        raise HTTPException(status_code=404, detail="Scope not found")
    if check_webcam and not scope.webcam_available:
        raise HTTPException(status_code=503, detail="Webcam not available on this scope")
    if not scope.last_ip:
        raise HTTPException(status_code=503, detail="Runner IP unknown — wait for next heartbeat")
    return f"http://{scope.last_ip}:{WEBCAM_PORT}{path}"


@router.get("/{scope_id}/webcam/stream")
async def proxy_stream(scope_id: str, db: AsyncSession = Depends(get_db),
                       _: User = Depends(_require_token)):
    """Proxy the MJPEG stream from the runner to the browser."""
    url = await _runner_url(scope_id, STREAM_PATH, db)

    async def generate():
        async with httpx.AsyncClient(timeout=None) as client:
            async with client.stream("GET", url) as resp:
                async for chunk in resp.aiter_bytes(chunk_size=4096):
                    yield chunk

    return StreamingResponse(generate(), media_type="multipart/x-mixed-replace; boundary=mjpegframe")


@router.get("/{scope_id}/webcam/snapshot")
async def proxy_snapshot(scope_id: str, db: AsyncSession = Depends(get_db),
                         _: User = Depends(_require_token)):
    """Return a single JPEG frame from the runner."""
    url = await _runner_url(scope_id, SNAPSHOT_PATH, db)
    async with httpx.AsyncClient(timeout=10) as client:
        try:
            resp = await client.get(url)
        except httpx.ConnectError:
            raise HTTPException(status_code=503, detail="Cannot reach runner")
    if resp.status_code == 503:
        raise HTTPException(status_code=503, detail="Webcam device busy or unavailable")
    return Response(content=resp.content, media_type="image/jpeg",
                    headers={"Cache-Control": "no-cache"})


@router.get("/{scope_id}/webcam/status")
async def webcam_status(scope_id: str, db: AsyncSession = Depends(get_db),
                        _: User = Depends(_require_token)):
    """Return whether the webcam is available on this scope."""
    scope: Scope | None = await db.get(Scope, scope_id)
    if scope is None:
        raise HTTPException(status_code=404, detail="Scope not found")
    return {"available": scope.webcam_available, "scope_id": scope_id}


@router.post("/{scope_id}/arduino/ir")
async def set_ir(scope_id: str, level: int, db: AsyncSession = Depends(get_db),
                 _: User = Depends(_require_staff)):
    """Set the IR LED brightness on the scope's Arduino (level 0=off … 9=full)."""
    scope: Scope | None = await db.get(Scope, scope_id)
    if scope is None:
        raise HTTPException(status_code=404, detail="Scope not found")
    if not scope.arduino_available:
        raise HTTPException(status_code=503, detail="Arduino not available on this scope")
    url = await _runner_url(scope_id, f"{IR_PATH}?level={level}", db, check_webcam=False)
    async with httpx.AsyncClient(timeout=5) as client:
        try:
            resp = await client.post(url)
        except httpx.ConnectError:
            raise HTTPException(status_code=503, detail="Cannot reach runner")
    if resp.status_code != 200:
        raise HTTPException(status_code=502, detail=f"Runner error: {resp.text}")
    return resp.json()
