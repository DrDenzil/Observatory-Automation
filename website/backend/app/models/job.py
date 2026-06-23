import uuid
from datetime import datetime, timezone
UTC = timezone.utc

from sqlalchemy import String, DateTime, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class Job(Base):
    __tablename__ = "jobs"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    request_id: Mapped[str] = mapped_column(String(36), ForeignKey("observation_requests.id"), nullable=False)
    queue_ref: Mapped[str | None] = mapped_column(String(255), nullable=True, index=True)
    scope_id: Mapped[str | None] = mapped_column(String(50), nullable=True, index=True)
    status: Mapped[str] = mapped_column(String(50), nullable=False, default="queued", index=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))
    started_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    error_message: Mapped[str | None] = mapped_column(String(1000), nullable=True)

    request: Mapped["ObservationRequest"] = relationship(back_populates="jobs")
    images: Mapped[list["Image"]] = relationship(back_populates="job")
