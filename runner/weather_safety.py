#!/usr/bin/env python3
"""
Bayfordbury Weather Safety Script for INDI Weather Safety Proxy
Outputs JSON format expected by indi_weather_safety_proxy

Safety checks:
- Weather station data (rain, wind, humidity, temperature)
- Station's safe_level signal
- Sun altitude (prevents opening during daylight)

Usage:
    ./weather_safety.py                    # Output JSON for INDI
    ./weather_safety.py --check            # Simple safe/unsafe check
    SUN_SAFE=0 ./weather_safety.py        # Disable sun check (for solar telescope)
"""

import socket
import json
import argparse
import math
import os
from datetime import datetime, UTC

DEFAULT_HOST = "147.197.130.103"
DEFAULT_PORT = 7332

# Bayfordbury Observatory coordinates
OBS_LAT = 51.774849  # degrees
OBS_LON = 0.095656   # degrees

# Safety thresholds
SUN_SAFE_ALTITUDE = -10  # Sun must be below this altitude (degrees) to open
RAIN_RATE_THRESHOLD = 0.5  # mm/h
WIND_SPEED_THRESHOLD = 50  # km/h
WIND_WARNING_THRESHOLD = 40  # km/h
HUMIDITY_THRESHOLD = 95  # %
HUMIDITY_WARNING = 85  # %
TEMP_LOW = -10  # °C
TEMP_HIGH = 40  # °C
TEMP_WARNING_LOW = 0  # °C

TEMPLATE = "$ws|$wg|$to|$ho|$rr|$fr|$sl"


def calculate_sun_altitude(lat, lon, dt=None):
    """
    Calculate sun altitude at given location and time.
    Uses simplified astronomical calculation.
    
    Returns: sun altitude in degrees (positive = above horizon)
    """
    if dt is None:
        dt = datetime.now(UTC)
    
    # Julian date
    jd = (dt.toordinal() - 1721424.5) + \
         (dt.hour + dt.minute/60 + dt.second/3600) / 24.0
    
    # Julian century
    t = (jd - 2451545.0) / 36525.0
    
    # Solar coordinates
    l0 = (280.46646 + 36000.76983 * t) % 360
    m = (357.52911 + 35999.05029 * t) % 360
    e = 0.016708634 - 0.000042037 * t
    
    # Equation of center
    c = (1.914602 - 0.004817 * t) * math.sin(math.radians(m)) + \
        (0.019993 - 0.000101 * t) * math.sin(math.radians(2 * m))
    
    # Sun's true longitude
    sun_lon = l0 + c
    
    # Obliquity of ecliptic
    obliquity = 23.439291 - 0.0130042 * t
    obliquity_rad = math.radians(obliquity)
    
    # Right ascension and declination
    ra = math.atan2(
        math.cos(obliquity_rad) * math.sin(math.radians(sun_lon)),
        math.cos(math.radians(sun_lon))
    )
    dec = math.asin(math.sin(obliquity_rad) * math.sin(math.radians(sun_lon)))
    
    # Hour angle
    gmst = 280.46061837 + 360.98564736629 * (jd - 2451545.0) + \
           0.000387933 * t * t
    lst = (gmst + lon) % 360
    ha = math.radians(lst - math.degrees(ra))
    
    # Altitude
    lat_rad = math.radians(lat)
    alt = math.asin(
        math.sin(lat_rad) * math.sin(dec) + 
        math.cos(lat_rad) * math.cos(dec) * math.cos(ha)
    )
    
    return math.degrees(alt)


def fetch_weather_raw(host=DEFAULT_HOST, port=DEFAULT_PORT, timeout=5):
    """Fetch raw weather data from station"""
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        sock.connect((host, port))
        
        sock.sendall((TEMPLATE + "\n").encode('utf-8'))
        data = sock.recv(4096).decode('utf-8').strip()
        sock.close()
        
        if not data:
            return None
        
        values = data.split('|')
        
        return {
            "wind_speed": float(values[0]) if values[0] else 0,
            "wind_gust": float(values[1]) if values[1] else 0,
            "temperature": float(values[2]) if values[2] else 0,
            "humidity": float(values[3]) if values[3] else 0,
            "rain_rate": float(values[4]) if values[4] else 0,
            "rain_flag": values[5].lower() == 'true' if len(values) > 5 else False,
            "safe_level": int(values[6]) if len(values) > 6 else 1,
        }
    except Exception as e:
        return {"error": str(e)}


def check_safety(weather, sun_alt=None):
    """Determine if it's safe to open observatory"""
    if weather.get("error"):
        return 0, f"Data error: {weather['error']}"
    
    reasons = []
    open_ok = 1
    
    # Check sun altitude (unless disabled via SUN_SAFE=0)
    sun_enabled = os.environ.get('SUN_SAFE', '1') != '0'
    if sun_enabled and sun_alt is not None:
        if sun_alt > SUN_SAFE_ALTITUDE:
            open_ok = 0
            if sun_alt > 0:
                reasons.append(f"Sun above horizon ({sun_alt:.1f}°)")
            else:
                reasons.append(f"Sun too high ({sun_alt:.1f}°)")
    
    # Check station safe_level
    safe_level = weather.get("safe_level", 1)
    if safe_level > 0:
        open_ok = 0
        reasons.append(f"Station safe level: {safe_level}")
    
    # Check rain flag
    if weather.get("rain_flag"):
        open_ok = 0
        reasons.append("Rain detected")
    
    # Check rain rate
    rain_rate = weather.get("rain_rate", 0)
    if rain_rate > RAIN_RATE_THRESHOLD:
        open_ok = 0
        reasons.append(f"Rain rate: {rain_rate} mm/h")
    
    # Check wind speed
    wind_speed = weather.get("wind_speed", 0)
    if wind_speed > WIND_SPEED_THRESHOLD:
        open_ok = 0
        reasons.append(f"Wind too high: {wind_speed} km/h")
    elif wind_speed > WIND_WARNING_THRESHOLD:
        reasons.append(f"Wind warning: {wind_speed} km/h")
    
    # Check humidity
    humidity = weather.get("humidity", 0)
    if humidity > HUMIDITY_THRESHOLD:
        open_ok = 0
        reasons.append(f"Humidity too high: {humidity}%")
    elif humidity > HUMIDITY_WARNING:
        reasons.append(f"Humidity warning: {humidity}%")
    
    # Check temperature
    temp = weather.get("temperature", 15)
    if temp < TEMP_LOW:
        open_ok = 0
        reasons.append(f"Temperature too low: {temp}°C")
    elif temp < TEMP_WARNING_LOW:
        reasons.append(f"Frost warning: {temp}°C")
    elif temp > TEMP_HIGH:
        open_ok = 0
        reasons.append(f"Temperature too high: {temp}°C")
    
    if open_ok and not reasons:
        reasons.append("All clear")
    
    return open_ok, "; ".join(reasons)


def output_json(weather):
    """Output JSON for INDI Weather Safety Proxy"""
    sun_alt = calculate_sun_altitude(OBS_LAT, OBS_LON)
    open_ok, reasons = check_safety(weather, sun_alt)
    
    sun_enabled = os.environ.get('SUN_SAFE', '1') != '0'
    
    result = {
        "timestamp_utc": datetime.now(UTC).strftime("%Y-%m-%dT%H:%M:%S"),
        "roof_status": {
            "open_ok": open_ok,
            "reasons": reasons
        },
        "raw_data": weather,
        "sun_altitude": round(sun_alt, 2),
        "sun_check": "enabled" if sun_enabled else "disabled"
    }
    
    print(json.dumps(result))


def output_simple():
    """Simple output for quick checks"""
    weather = fetch_weather_raw()
    sun_alt = calculate_sun_altitude(OBS_LAT, OBS_LON)
    open_ok, reasons = check_safety(weather, sun_alt)
    
    sun_enabled = os.environ.get('SUN_SAFE', '1') != '0'
    print(f"{'SAFE' if open_ok else 'UNSAFE'}: {reasons}")
    print(f"Sun altitude: {sun_alt:.1f}° (check {'enabled' if sun_enabled else 'disabled'})")


def main():
    parser = argparse.ArgumentParser(description='Bayfordbury Weather Safety')
    parser.add_argument('--host', default=DEFAULT_HOST, help='Weather station host')
    parser.add_argument('--port', type=int, default=DEFAULT_PORT, help='Weather station port')
    parser.add_argument('--check', action='store_true', help='Simple safe/unsafe output')
    args = parser.parse_args()
    
    weather = fetch_weather_raw(args.host, args.port)
    
    if args.check:
        output_simple()
    else:
        output_json(weather)


if __name__ == "__main__":
    main()
