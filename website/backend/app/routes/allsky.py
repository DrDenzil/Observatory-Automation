from fastapi import APIRouter, HTTPException
from fastapi.responses import FileResponse
from pathlib import Path
from datetime import date, timedelta
import re
import os

router = APIRouter(prefix="/api/allsky", tags=["allsky"])

# Configuration — configurable for Docker/different environments
ALLSKY_BASE = Path(os.getenv("ALLSKY_BASE", "/www/allsky"))

CAMERAS = {
    "bayfordbury_night": {"dir": "camera1", "name": "Bayfordbury Night"},
    "bayfordbury_day": {"dir": "camera2", "name": "Bayfordbury Day"},
    "hemel": {"dir": "camera3", "name": "Hemel"},
}

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


@router.get("/cameras")
async def list_cameras():
    """List all available all-sky cameras."""
    return {
        "cameras": [
            {"id": cam_id, "name": cam_info["name"]}
            for cam_id, cam_info in CAMERAS.items()
        ]
    }


@router.get("/{camera_id}/latest")
async def get_latest(camera_id: str):
    """Get latest camera image and timelapse info."""
    if camera_id not in CAMERAS:
        raise HTTPException(status_code=404, detail="Camera not found")

    camera_info = CAMERAS[camera_id]
    camera_dir = ALLSKY_BASE / camera_info["dir"]

    image_path = get_latest_image_path(camera_dir)
    if not image_path:
        raise HTTPException(status_code=404, detail="No images found")

    today = date.today()
    timelapse_path = get_timelapse_path(camera_dir, today)

    # If today's timelapse doesn't exist, try yesterday
    if not timelapse_path:
        timelapse_path = get_timelapse_path(camera_dir, today - timedelta(days=1))

    return {
        "camera_id": camera_id,
        "camera_name": camera_info["name"],
        "image": image_path.name,
        "image_url": f"/api/allsky/{camera_id}/image/{image_path.name}",
        "timelapse_available": timelapse_path is not None,
        "timelapse_date": timelapse_path.stem if timelapse_path else None,
        "timelapse_url": f"/api/allsky/{camera_id}/timelapse/{timelapse_path.stem}.mp4" if timelapse_path else None,
    }


@router.get("/{camera_id}/image/{filename}")
async def get_image(camera_id: str, filename: str):
    """Serve camera image."""
    if camera_id not in CAMERAS:
        raise HTTPException(status_code=404, detail="Camera not found")

    camera_info = CAMERAS[camera_id]
    file_path = ALLSKY_BASE / camera_info["dir"] / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Image not found")

    # Security: prevent directory traversal
    if not str(file_path).startswith(str(ALLSKY_BASE / camera_info["dir"])):
        raise HTTPException(status_code=403, detail="Access denied")

    return FileResponse(file_path, media_type="image/jpeg")


@router.get("/{camera_id}/timelapse/{filename}")
async def get_timelapse(camera_id: str, filename: str):
    """Serve camera timelapse video."""
    if camera_id not in CAMERAS:
        raise HTTPException(status_code=404, detail="Camera not found")

    # Validate filename format: YYYY-MM-DD.mp4
    if not re.match(r"\d{4}-\d{2}-\d{2}\.mp4$", filename):
        raise HTTPException(status_code=400, detail="Invalid filename format")

    camera_info = CAMERAS[camera_id]
    file_path = ALLSKY_BASE / camera_info["dir"] / filename

    if not file_path.exists() or not file_path.is_file():
        raise HTTPException(status_code=404, detail="Timelapse not found")

    return FileResponse(file_path, media_type="video/mp4")
