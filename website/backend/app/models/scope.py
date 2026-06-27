from datetime import datetime, timezone
UTC = timezone.utc

from sqlalchemy import String, DateTime, Boolean
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base


class Scope(Base):
    """A telescope machine running the Go runner agent.

    Rows are created/updated by the runner's heartbeat. The web dashboard
    reads them to show live status across all scopes.
    """

    __tablename__ = "scopes"

    # scope_id, e.g. "scope03" — supplied by the runner, stable per machine
    id: Mapped[str] = mapped_column(String(50), primary_key=True)
    name: Mapped[str | None] = mapped_column(String(255), nullable=True)

    # idle | fetching | processing | executing | uploading | failed | offline
    state: Mapped[str] = mapped_column(String(50), nullable=False, default="offline")
    current_job_id: Mapped[str | None] = mapped_column(String(36), nullable=True)
    progress_step: Mapped[str | None] = mapped_column(String(100), nullable=True)
    progress_message: Mapped[str | None] = mapped_column(String(500), nullable=True)

    kstars_running: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    indi_running: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    network_connected: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)

    weather_safe: Mapped[bool | None] = mapped_column(Boolean, nullable=True)
    weather_message: Mapped[str | None] = mapped_column(String(500), nullable=True)
    automation_enabled: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)

    webcam_available: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    arduino_available: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)
    last_ip: Mapped[str | None] = mapped_column(String(45), nullable=True)  # IPv4 or IPv6

    last_heartbeat: Mapped[datetime | None] = mapped_column(DateTime, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))
