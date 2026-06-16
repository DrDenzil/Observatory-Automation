import uuid
from datetime import datetime, UTC

from sqlalchemy import String, DateTime, Float, Integer, Text, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class Image(Base):
    __tablename__ = "images"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    dbid: Mapped[int] = mapped_column(Integer, unique=True, nullable=False, index=True)
    job_id: Mapped[str | None] = mapped_column(String(36), ForeignKey("jobs.id"), nullable=True)
    target_name: Mapped[str] = mapped_column(String(255), nullable=False, index=True)
    filter_name: Mapped[str | None] = mapped_column(String(50), nullable=True)
    exposure_seconds: Mapped[float | None] = mapped_column(Float, nullable=True)
    fits_path: Mapped[str] = mapped_column(String(1000), nullable=False)
    thumbnail_path: Mapped[str | None] = mapped_column(String(1000), nullable=True)
    ra: Mapped[float | None] = mapped_column(Float, nullable=True)
    dec: Mapped[float | None] = mapped_column(Float, nullable=True)
    observer_id: Mapped[str | None] = mapped_column(String(255), nullable=True, index=True)
    project_name: Mapped[str | None] = mapped_column(String(255), nullable=True, index=True)
    telescope: Mapped[str | None] = mapped_column(String(100), nullable=True)
    metadata_json: Mapped[str | None] = mapped_column(Text, nullable=True)
    captured_at: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))

    job: Mapped["Job | None"] = relationship(back_populates="images")
