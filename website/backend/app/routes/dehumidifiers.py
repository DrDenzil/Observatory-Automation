"""Dehumidifier monitoring and control routes.

Scrapes http://observatory-server.herts.ac.uk/bms/control.php for live readings
and proxies control commands to /bms/api/command.php.

Endpoints:
  GET  /api/dehumidifiers          — live status for all domes (any logged-in user)
  POST /api/dehumidifiers/{dome}/on  — turn on (staff/admin only)
  POST /api/dehumidifiers/{dome}/off — turn off (staff/admin only)
  POST /api/dehumidifiers/all/on   — all on (staff/admin only)
  POST /api/dehumidifiers/all/off  — all off (staff/admin only)
"""
import re
from datetime import datetime, timezone

import httpx
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel

from app.models.user import User
from app.services.auth import get_current_user, require_role

UTC = timezone.utc
BMS_BASE = "http://observatory-server.herts.ac.uk/bms"
NUM_DOMES = 8

router = APIRouter(prefix="/api/dehumidifiers", tags=["dehumidifiers"])

_require_staff = require_role("staff", "admin")


class DomeStatus(BaseModel):
    dome: int
    enabled: bool
    humidity_pct: float
    air_temp_c: float
    mount_temp_c: float
    dew_point_c: float
    running: bool


class DehumidifierStatus(BaseModel):
    domes: list[DomeStatus]
    checked_at: datetime


async def _scrape_status() -> list[DomeStatus]:
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.get(f"{BMS_BASE}/control.php")
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"BMS unreachable: {exc}")

    cells = re.findall(r"<td>(.*?)</td>", resp.text)
    # Strip degree symbol and whitespace
    cells = [re.sub(r"&deg;", "", c).strip() for c in cells]

    def _num(s: str) -> float:
        return float(re.sub(r"[^\d.\-]", "", s) or "0")

    COLS = 18  # columns per dome row in control.php table
    domes = []
    for i in range(0, COLS * NUM_DOMES, COLS):
        row = cells[i : i + COLS]
        if len(row) < COLS:
            break
        try:
            domes.append(
                DomeStatus(
                    dome=int(row[0]),
                    enabled=row[1] == "1",
                    humidity_pct=_num(row[8]),
                    air_temp_c=_num(row[9]),
                    mount_temp_c=_num(row[11]),
                    dew_point_c=_num(row[12]),
                    running=row[17] == "1",
                )
            )
        except (ValueError, IndexError):
            continue
    return domes


async def _send_command(command: str, dome: int | None = None) -> None:
    params: dict[str, str] = {"command": command}
    if dome is not None:
        params["dome"] = str(dome)
    try:
        async with httpx.AsyncClient(timeout=10) as client:
            resp = await client.get(f"{BMS_BASE}/api/command.php", params=params)
    except Exception as exc:
        raise HTTPException(status_code=503, detail=f"BMS unreachable: {exc}")
    if resp.status_code not in (200, 204):
        raise HTTPException(status_code=502, detail=f"BMS error: {resp.text}")


@router.get("", response_model=DehumidifierStatus)
async def get_status(_: User = Depends(get_current_user)):
    """Return live readings for all dehumidifier domes."""
    domes = await _scrape_status()
    return DehumidifierStatus(domes=domes, checked_at=datetime.now(UTC))


@router.post("/all/on")
async def all_on(_: User = Depends(_require_staff)):
    """Turn on all dehumidifiers."""
    for dome in range(1, NUM_DOMES + 1):
        await _send_command("on", dome)
    return {"ok": True, "command": "all_on"}


@router.post("/all/off")
async def all_off(_: User = Depends(_require_staff)):
    """Turn off all dehumidifiers."""
    for dome in range(1, NUM_DOMES + 1):
        await _send_command("off", dome)
    return {"ok": True, "command": "all_off"}


@router.post("/{dome}/on")
async def dome_on(dome: int, _: User = Depends(_require_staff)):
    """Turn on a single dome's dehumidifier."""
    if dome < 1 or dome > NUM_DOMES:
        raise HTTPException(status_code=400, detail=f"Dome must be 1–{NUM_DOMES}")
    await _send_command("on", dome)
    return {"ok": True, "dome": dome, "command": "on"}


@router.post("/{dome}/off")
async def dome_off(dome: int, _: User = Depends(_require_staff)):
    """Turn off a single dome's dehumidifier."""
    if dome < 1 or dome > NUM_DOMES:
        raise HTTPException(status_code=400, detail=f"Dome must be 1–{NUM_DOMES}")
    await _send_command("off", dome)
    return {"ok": True, "dome": dome, "command": "off"}
