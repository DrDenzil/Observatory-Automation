from fastapi import APIRouter, HTTPException, FileResponse
from pathlib import Path
from datetime import date, timedelta
import re

router = APIRouter(prefix="/api/allsky", tags=["allsky"])

# Configuration
ALLSKY_BASE = Path("/www/allsky")
CAMERA_NIGHT = "camera1"
CAMERA_DAY = "camera7"

# Image filename pattern: AllSkyImage{number}.jpg
IMAGE_PATTERN = re.compile(r"AllSkyImage(\d+)\.jpg", re.IGNORECASE)


def get_latest_image_path(camera_dir: Path) -> Path | None:
    """Find the latest image by extracting number from filename."""
    if not camera_dir.exists():
        return None

    images = []
    for f in camera_dir.glob("AllSkyImage*.jpg"):
        match = IMAGE_PATTERN.match(f.name)
        if match:
            images.append((int(match.group(1)), f))

    if not images:
        return None

    # Return file with highest number (latest)
    return sorted(images, key=lambda x: x[0])[-1][1]


def get_timelapse_path(camera_dir: Path, target_date: date) -> Path | None:
    """Find timelapse for a given date."""
    timelapse = camera_dir / f"{target_date:%Y-%m-%d}.mp4"
    return timelapse if timelapse.exists() else None


@router.get("/night/latest")
async def get_latest_night():
    """Get latest night camera image and timelapse info."""
    camera_dir = ALLSKY_BASE / CAMERA_NIGHT

    image_path = get_latest_image_path(camera_dir)
    if not image_path:
        raise HTTPException(status_code=404, detail="No images found")

    today = date.today()
    timelapse_path = get_timelapse_path(camera_dir, today)

    # If today's timelapse doesn't exist, try yesterday
    if not timelapse_path:
        timelapse_path = get_timelapse_path(camera_dir, today - timedelta(days=1))

    return {
        "camera": "night",
        "image": image_path.name,
        "image_url": f"/api/allsky/night/image/{image_path.name}",
        "timelapse_available": timelapse_path is not None,
        "timelapse_date": timelapse_path.stem if timelapse_path else None,
        "timelapse_url": f"/api/allsky/night/timelapse/{timelapse_path.stem}.mp4" if timelapse_path else None,
    }


@router.get("/day/latest")
async def get_latest_day():
    """Get latest day camera image and timelapse info."""
    camera_dir = ALLSKY_BASE / CAMERA_DAY

    image_path = get_latest_image_path(camera_dir)
    if not image_path:
        raise HTTPException(status_code=404, detail="No images found")

    today = date.today()
    timelapse_path = get_timelapse_path(camera_dir, today)

    # If today's timelapse doesn't exist, try yesterday
    if not timelapse_path:
        timelapse_path = get_timelapse_path(camera_dir, today - timedelta(days=1))

    return {
        "camera": "day",
        "image": image_path.name,
        "image_url": f"/api/allsky/day/image/{image_path.name}",
        "timelapse_available": timelapse_path is not None,
        "timelapse_date": timelapse_path.stem if timelapse_path else None,
        "timelapse_url": f"/api/allsky/day/timelapse/{timelapse_path.stem}.mp4" if timelapse_path else None,
    }


@router.get("/night/image/{filename}")
async def get_night_image(filename: str):
    """Serve night camera image."""
    file_path = ALLSKY_BASE / CAMERA_NIGHT / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Image not found")

    # Security: prevent directory traversal
    if not str(file_path).startswith(str(ALLSKY_BASE / CAMERA_NIGHT)):
        raise HTTPException(status_code=403, detail="Access denied")

    return FileResponse(file_path, media_type="image/jpeg")


@router.get("/day/image/{filename}")
async def get_day_image(filename: str):
    """Serve day camera image."""
    file_path = ALLSKY_BASE / CAMERA_DAY / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Image not found")

    # Security: prevent directory traversal
    if not str(file_path).startswith(str(ALLSKY_BASE / CAMERA_DAY)):
        raise HTTPException(status_code=403, detail="Access denied")

    return FileResponse(file_path, media_type="image/jpeg")


@router.get("/night/timelapse/{filename}")
async def get_night_timelapse(filename: str):
    """Serve night camera timelapse video."""
    # Validate filename format: YYYY-MM-DD.mp4
    if not re.match(r"\d{4}-\d{2}-\d{2}\.mp4$", filename):
        raise HTTPException(status_code=400, detail="Invalid filename format")

    file_path = ALLSKY_BASE / CAMERA_NIGHT / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Timelapse not found")

    return FileResponse(file_path, media_type="video/mp4")


@router.get("/day/timelapse/{filename}")
async def get_day_timelapse(filename: str):
    """Serve day camera timelapse video."""
    # Validate filename format: YYYY-MM-DD.mp4
    if not re.match(r"\d{4}-\d{2}-\d{2}\.mp4$", filename):
        raise HTTPException(status_code=400, detail="Invalid filename format")

    file_path = ALLSKY_BASE / CAMERA_DAY / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Timelapse not found")

    return FileResponse(file_path, media_type="video/mp4")
