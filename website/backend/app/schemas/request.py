import uuid
from datetime import datetime

from pydantic import BaseModel, field_validator


class TargetCreate(BaseModel):
    target_name: str
    ra: float
    dec: float
    filters: list[str] = ["L"]
    exposure_seconds: float = 5.0
    count: int = 1
    binning: int = 1

    @field_validator("target_name")
    @classmethod
    def target_name_not_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError("Target name is required")
        return v.strip()

    @field_validator("ra")
    @classmethod
    def ra_range(cls, v: float) -> float:
        if not 0 <= v <= 360:
            raise ValueError("RA must be between 0 and 360 degrees")
        return v

    @field_validator("dec")
    @classmethod
    def dec_range(cls, v: float) -> float:
        if not -90 <= v <= 90:
            raise ValueError("Dec must be between -90 and 90 degrees")
        return v

    @field_validator("exposure_seconds")
    @classmethod
    def exposure_range(cls, v: float) -> float:
        if not 0.1 <= v <= 3600:
            raise ValueError("Exposure must be between 0.1 and 3600 seconds")
        return v

    @field_validator("count")
    @classmethod
    def count_range(cls, v: int) -> int:
        if not 1 <= v <= 1000:
            raise ValueError("Count must be between 1 and 1000")
        return v

    @field_validator("filters")
    @classmethod
    def filters_valid(cls, v: list[str]) -> list[str]:
        allowed = {"L", "R", "G", "B", "Ha", "OIII", "SII"}
        if not v:
            raise ValueError("At least one filter is required")
        invalid = set(v) - allowed
        if invalid:
            raise ValueError(f"Invalid filters: {invalid}")
        return v


class TargetOut(BaseModel):
    id: uuid.UUID
    target_name: str
    ra: float
    dec: float
    filters: list[str]
    exposure_seconds: float
    count: int
    binning: int

    model_config = {"from_attributes": True}


class RequestCreate(BaseModel):
    project_name: str
    description: str | None = None
    priority: int = 1
    telescope_id: str
    targets: list[TargetCreate]


class RequestOut(BaseModel):
    id: uuid.UUID
    user_id: uuid.UUID
    project_name: str
    description: str | None
    status: str
    priority: int
    created_at: datetime
    submitted_at: datetime | None
    approved_at: datetime | None
    rejected_reason: str | None
    telescope_id: str | None
    telescope_name: str | None = None
    targets: list[TargetOut]
    user_name: str | None = None
    approver_name: str | None = None

    model_config = {"from_attributes": True}


class RequestReject(BaseModel):
    reason: str
