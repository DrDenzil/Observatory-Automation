from datetime import datetime

from pydantic import BaseModel, field_validator

ALLOWED_FILTERS = {"L", "R", "G", "B", "Ha", "OIII", "SII", "C", "U", "V", "I"}
ALLOWED_STATUS = {"manual", "maintenance", "automatic"}


class TelescopeOut(BaseModel):
    id: str
    num: int
    short_name: str
    telescope: str
    aperture_mm: float | None
    focal_length_mm: float | None
    camera: str | None
    pixel_width_um: float | None
    fov_w_arcmin: float | None
    fov_h_arcmin: float | None
    filters: list[str]
    dec_lower: float | None
    dec_upper: float | None
    min_binning: int
    status: str
    status_reason: str | None
    scope_id: str | None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


def _validate_filters(v: list[str]) -> list[str]:
    invalid = set(v) - ALLOWED_FILTERS
    if invalid:
        raise ValueError(f"Invalid filters: {sorted(invalid)}")
    return v


def _validate_status(v: str) -> str:
    if v not in ALLOWED_STATUS:
        raise ValueError(f"status must be one of {sorted(ALLOWED_STATUS)}")
    return v


def _validate_dec(v: float | None) -> float | None:
    if v is not None and not -90 <= v <= 90:
        raise ValueError("declination must be between -90 and 90 degrees")
    return v


class TelescopeCreate(BaseModel):
    num: int
    short_name: str
    telescope: str
    aperture_mm: float | None = None
    focal_length_mm: float | None = None
    camera: str | None = None
    pixel_width_um: float | None = None
    fov_w_arcmin: float | None = None
    fov_h_arcmin: float | None = None
    filters: list[str] = []
    dec_lower: float | None = None
    dec_upper: float | None = None
    min_binning: int = 1
    status: str = "manual"
    status_reason: str | None = None
    scope_id: str | None = None

    @field_validator("short_name", "telescope")
    @classmethod
    def not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("must not be empty")
        return v.strip()

    @field_validator("min_binning")
    @classmethod
    def binning_min(cls, v: int) -> int:
        if v < 1:
            raise ValueError("min_binning must be at least 1")
        return v

    @field_validator("filters")
    @classmethod
    def filters_valid(cls, v: list[str]) -> list[str]:
        return _validate_filters(v)

    @field_validator("status")
    @classmethod
    def status_valid(cls, v: str) -> str:
        return _validate_status(v)

    @field_validator("dec_lower", "dec_upper")
    @classmethod
    def dec_valid(cls, v: float | None) -> float | None:
        return _validate_dec(v)


class TelescopeUpdate(BaseModel):
    num: int | None = None
    short_name: str | None = None
    telescope: str | None = None
    aperture_mm: float | None = None
    focal_length_mm: float | None = None
    camera: str | None = None
    pixel_width_um: float | None = None
    fov_w_arcmin: float | None = None
    fov_h_arcmin: float | None = None
    filters: list[str] | None = None
    dec_lower: float | None = None
    dec_upper: float | None = None
    min_binning: int | None = None
    status: str | None = None
    status_reason: str | None = None
    scope_id: str | None = None

    @field_validator("min_binning")
    @classmethod
    def binning_min(cls, v: int | None) -> int | None:
        if v is not None and v < 1:
            raise ValueError("min_binning must be at least 1")
        return v

    @field_validator("filters")
    @classmethod
    def filters_valid(cls, v: list[str] | None) -> list[str] | None:
        return _validate_filters(v) if v is not None else v

    @field_validator("status")
    @classmethod
    def status_valid(cls, v: str | None) -> str | None:
        return _validate_status(v) if v is not None else v

    @field_validator("dec_lower", "dec_upper")
    @classmethod
    def dec_valid(cls, v: float | None) -> float | None:
        return _validate_dec(v)
