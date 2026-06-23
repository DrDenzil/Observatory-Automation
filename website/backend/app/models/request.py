import json
import uuid
from datetime import datetime, timezone
UTC = timezone.utc

from sqlalchemy import String, DateTime, Float, Integer, Text, ForeignKey, TypeDecorator
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class JSONList(TypeDecorator):
    impl = Text
    cache_ok = True

    def process_bind_param(self, value, dialect):
        return json.dumps(value) if value is not None else "[]"

    def process_result_value(self, value, dialect):
        return json.loads(value) if value else []


class ObservationRequest(Base):
    __tablename__ = "observation_requests"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id: Mapped[str] = mapped_column(String(36), ForeignKey("users.id"), nullable=False)
    project_name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[str | None] = mapped_column(Text, nullable=True)
    status: Mapped[str] = mapped_column(String(50), nullable=False, default="draft", index=True)
    priority: Mapped[int] = mapped_column(Integer, nullable=False, default=1)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))
    submitted_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    approved_by: Mapped[str | None] = mapped_column(String(36), ForeignKey("users.id"), nullable=True)
    approved_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    rejected_reason: Mapped[str | None] = mapped_column(Text, nullable=True)
    telescope_id: Mapped[str | None] = mapped_column(String(36), ForeignKey("telescope_configs.id"), nullable=True)

    user: Mapped["User"] = relationship(back_populates="requests", foreign_keys=[user_id])
    approver: Mapped["User | None"] = relationship(foreign_keys=[approved_by])
    targets: Mapped[list["RequestTarget"]] = relationship(back_populates="request", cascade="all, delete-orphan")
    jobs: Mapped[list["Job"]] = relationship(back_populates="request")
    telescope: Mapped["TelescopeConfig | None"] = relationship(foreign_keys=[telescope_id])


class RequestTarget(Base):
    __tablename__ = "request_targets"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    request_id: Mapped[str] = mapped_column(String(36), ForeignKey("observation_requests.id"), nullable=False)
    target_name: Mapped[str] = mapped_column(String(255), nullable=False)
    ra: Mapped[float] = mapped_column(Float, nullable=False)
    dec: Mapped[float] = mapped_column(Float, nullable=False)
    filters: Mapped[list[str]] = mapped_column(JSONList, nullable=False, default=list)
    exposure_seconds: Mapped[float] = mapped_column(Float, nullable=False, default=5.0)
    count: Mapped[int] = mapped_column(Integer, nullable=False, default=1)
    binning: Mapped[int] = mapped_column(Integer, nullable=False, default=1)

    request: Mapped["ObservationRequest"] = relationship(back_populates="targets")
