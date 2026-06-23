"""Live weather data from the Bayfordbury weather station.

Connects directly to the TCP socket at 147.197.130.103:7332 and applies the
same safety thresholds as weather_safety.py. Returns structured JSON for the
staff dashboard weather widget.
"""

import asyncio
import math
from datetime import datetime, timezone

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

UTC = timezone.utc

router = APIRouter(prefix="/api/weather", tags=["weather"])

STATION_HOST = "147.197.130.103"
STATION_PORT = 7332
TEMPLATE = "$ws|$wg|$to|$ho|$rr|$fr|$sl"

# Bayfordbury Observatory
OBS_LAT = 51.774849
OBS_LON = 0.095656

# Safety thresholds (must match weather_safety.py)
WIND_SPEED_THRESHOLD = 50
HUMIDITY_THRESHOLD = 95
RAIN_RATE_THRESHOLD = 0.5
TEMP_LOW = -10
TEMP_HIGH = 40
SUN_SAFE_ALTITUDE = -10


class WeatherOut(BaseModel):
    safe: bool
    wind_kph: float
    wind_gust_kph: float
    temp_c: float
    humidity_pct: float
    rain_rate_mmh: float
    sun_altitude: float
    message: str
    checked_at: datetime


def _sun_altitude() -> float:
    """Simplified sun altitude calculation for Bayfordbury."""
    dt = datetime.now(UTC)
    a = (14 - dt.month) // 12
    y = dt.year + 4800 - a
    m = dt.month + 12 * a - 3
    jdn = dt.day + ((153 * m + 2) // 5) + 365 * y + y // 4 - y // 100 + y // 400 - 32045
    frac = (dt.hour + dt.minute / 60 + dt.second / 3600) / 24.0
    jd = jdn + frac - 0.5
    t = (jd - 2451545.0) / 36525.0
    l0 = (280.46646 + 36000.76983 * t) % 360
    m_a = (357.52911 + 35999.05029 * t) % 360
    c = (1.914602 - 0.004817 * t) * math.sin(math.radians(m_a)) + \
        (0.019993 - 0.000101 * t) * math.sin(math.radians(2 * m_a))
    sun_lon = l0 + c
    obliquity = math.radians(23.439291 - 0.0130042 * t)
    ra = math.atan2(math.cos(obliquity) * math.sin(math.radians(sun_lon)), math.cos(math.radians(sun_lon)))
    dec = math.asin(math.sin(obliquity) * math.sin(math.radians(sun_lon)))
    gmst = 280.46061837 + 360.98564736629 * (jd - 2451545.0) + 0.000387933 * t * t
    lst = (gmst + OBS_LON) % 360
    ha = math.radians(lst - math.degrees(ra))
    lat_rad = math.radians(OBS_LAT)
    alt = math.asin(math.sin(lat_rad) * math.sin(dec) + math.cos(lat_rad) * math.cos(dec) * math.cos(ha))
    return math.degrees(alt)


async def _fetch_raw() -> dict:
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(STATION_HOST, STATION_PORT), timeout=5
        )
        writer.write((TEMPLATE + "\n").encode())
        await writer.drain()
        data = await asyncio.wait_for(reader.read(4096), timeout=5)
        writer.close()
        await writer.wait_closed()
    except Exception as exc:
        msg = str(exc) or type(exc).__name__
        raise HTTPException(status_code=503, detail=f"Weather station unreachable: {msg}")

    line = data.decode().strip()
    if not line:
        raise HTTPException(status_code=503, detail="Weather station returned empty response")

    parts = line.split("|")
    try:
        return {
            "wind_speed": float(parts[0]) if parts[0] else 0.0,
            "wind_gust":  float(parts[1]) if parts[1] else 0.0,
            "temp":       float(parts[2]) if parts[2] else 0.0,
            "humidity":   float(parts[3]) if parts[3] else 0.0,
            "rain_rate":  float(parts[4]) if parts[4] else 0.0,
        }
    except (IndexError, ValueError) as exc:
        raise HTTPException(status_code=503, detail=f"Weather station parse error: {exc}")


@router.get("", response_model=WeatherOut)
async def get_weather():
    """Return live weather readings and safety status from the station."""
    raw = await _fetch_raw()
    sun_alt = _sun_altitude()

    reasons: list[str] = []
    safe = True

    if sun_alt > SUN_SAFE_ALTITUDE:
        safe = False
        reasons.append(f"Sun too high ({sun_alt:.1f}°)")
    if raw["wind_speed"] > WIND_SPEED_THRESHOLD:
        safe = False
        reasons.append(f"Wind {raw['wind_speed']:.0f} km/h")
    if raw["humidity"] > HUMIDITY_THRESHOLD:
        safe = False
        reasons.append(f"Humidity {raw['humidity']:.0f}%")
    if raw["rain_rate"] > RAIN_RATE_THRESHOLD:
        safe = False
        reasons.append(f"Rain {raw['rain_rate']:.1f} mm/h")
    if raw["temp"] < TEMP_LOW or raw["temp"] > TEMP_HIGH:
        safe = False
        reasons.append(f"Temp {raw['temp']:.1f}°C")

    message = ("SAFE: All clear" if safe else "UNSAFE: " + "; ".join(reasons))

    return WeatherOut(
        safe=safe,
        wind_kph=raw["wind_speed"],
        wind_gust_kph=raw["wind_gust"],
        temp_c=raw["temp"],
        humidity_pct=raw["humidity"],
        rain_rate_mmh=raw["rain_rate"],
        sun_altitude=round(sun_alt, 1),
        message=message,
        checked_at=datetime.now(UTC),
    )
