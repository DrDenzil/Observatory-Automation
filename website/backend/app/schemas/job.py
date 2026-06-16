from datetime import datetime

from pydantic import BaseModel


class JobOut(BaseModel):
    id: str
    request_id: str
    queue_ref: str | None
    scope_id: str | None
    status: str
    created_at: datetime
    started_at: datetime | None
    completed_at: datetime | None
    error_message: str | None
    project_name: str | None = None
    user_name: str | None = None
    target_summary: str | None = None

    model_config = {"from_attributes": True}


class JobUpdate(BaseModel):
    status: str | None = None
    scope_id: str | None = None
    error_message: str | None = None


class JobDispatch(BaseModel):
    scope_id: str
