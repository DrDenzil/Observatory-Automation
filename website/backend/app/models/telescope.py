import uuid
from datetime import datetime, timezone
UTC = timezone.utc

from sqlalchemy import String, DateTime, Float, Integer, Text
from sqlalchemy.orm import Mapped, mapped_column

from app.database import Base
from app.models.request import JSONList


class TelescopeConfig(Base):
    """Static, admin-editable configuration for each telescope (ported from the
    old obssetup.php / obssetup table).

    This is distinct from the Scope model: Scope holds live runtime status
    (heartbeats), while TelescopeConfig holds the editable capabilities that
    drive the request form (available filters, declination limits, min binning).
    The two are linked via scope_id.
    """

    __tablename__ = "telescope_configs"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    # Display order / telescope number (obssetup.num).
    num: Mapped[int] = mapped_column(Integer, nullable=False, unique=True, index=True)
    short_name: Mapped[str] = mapped_column(String(100), nullable=False)
    telescope: Mapped[str] = mapped_column(String(255), nullable=False)

    aperture_mm: Mapped[float | None] = mapped_column(Float, nullable=True)
    focal_length_mm: Mapped[float | None] = mapped_column(Float, nullable=True)
    camera: Mapped[str | None] = mapped_column(String(255), nullable=True)
    pixel_width_um: Mapped[float | None] = mapped_column(Float, nullable=True)
    fov_w_arcmin: Mapped[float | None] = mapped_column(Float, nullable=True)
    fov_h_arcmin: Mapped[float | None] = mapped_column(Float, nullable=True)

    filters: Mapped[list[str]] = mapped_column(JSONList, nullable=False, default=list)
    dec_lower: Mapped[float | None] = mapped_column(Float, nullable=True)
    dec_upper: Mapped[float | None] = mapped_column(Float, nullable=True)
    min_binning: Mapped[int] = mapped_column(Integer, nullable=False, default=1)

    # manual | maintenance | automatic  (maps to old status 0/1/2)
    status: Mapped[str] = mapped_column(String(20), nullable=False, default="manual")
    status_reason: Mapped[str | None] = mapped_column(Text, nullable=True)

    # Links to the live Scope (runner) of the same machine, e.g. "scope03".
    scope_id: Mapped[str | None] = mapped_column(String(50), nullable=True, index=True)

    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))
    updated_at: Mapped[datetime] = mapped_column(
        DateTime, default=lambda: datetime.now(UTC), onupdate=lambda: datetime.now(UTC)
    )
