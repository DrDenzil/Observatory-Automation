import re

import httpx
from fastapi import APIRouter, Depends, Query
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.data.catalogue import search_local, _normalise
from app.models.catalogue import SimbadCache

router = APIRouter(prefix="/api/catalogue", tags=["catalogue"])

SESAME_URL = "https://cdsweb.u-strasbg.fr/cgi-bin/nph-sesame/-oI/S?{name}"


async def _query_simbad(name: str) -> dict | None:
    url = SESAME_URL.format(name=name.replace(" ", "+"))
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            r = await client.get(url)
        if r.status_code != 200:
            return None
        for line in r.text.splitlines():
            if line.startswith("%J"):
                # "%J 83.822 -5.391 = ..."
                parts = line.split()
                if len(parts) >= 3:
                    ra = float(parts[1])
                    dec = float(parts[2])
                    return {"name": name, "common_name": None, "ra_deg": ra, "dec_deg": dec, "type": None, "source": "simbad"}
    except Exception:
        pass
    return None


@router.get("/resolve")
async def resolve(
    name: str = Query(..., min_length=2, max_length=100),
    db: AsyncSession = Depends(get_db),
):
    """
    Search for an object by name. Returns up to 5 matches with RA/Dec.
    Searches local catalogue first; falls back to SIMBAD for exact lookups.
    """
    name = name.strip()

    # 1. Local catalogue (fast, covers Messier/NGC/named stars)
    local = search_local(name, limit=5)
    if local:
        return local

    # 2. SIMBAD cache
    key = _normalise(name)
    cached = (await db.execute(
        select(SimbadCache).where(SimbadCache.query == key)
    )).scalar_one_or_none()
    if cached:
        return [{"name": cached.name, "common_name": None, "ra_deg": cached.ra_deg,
                 "dec_deg": cached.dec_deg, "type": cached.object_type, "source": "simbad"}]

    # 3. Live SIMBAD query
    result = await _query_simbad(name)
    if result:
        db.add(SimbadCache(
            query=key,
            name=result["name"],
            ra_deg=result["ra_deg"],
            dec_deg=result["dec_deg"],
            object_type=result.get("type"),
        ))
        await db.commit()
        return [result]

    return []
