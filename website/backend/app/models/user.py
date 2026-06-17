import uuid
from datetime import datetime, UTC

from sqlalchemy import String, DateTime, Boolean, Integer
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class User(Base):
    __tablename__ = "users"

    id: Mapped[str] = mapped_column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    legacy_id: Mapped[int | None] = mapped_column(Integer, nullable=True, unique=True, index=True)
    email: Mapped[str] = mapped_column(String(255), unique=True, nullable=False, index=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    hashed_password: Mapped[str] = mapped_column(String(255), nullable=True)
    role: Mapped[str] = mapped_column(String(50), nullable=False, default="observer")
    user_type: Mapped[str] = mapped_column(String(50), nullable=False, default="student")
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    oauth_sub: Mapped[str | None] = mapped_column(String(255), nullable=True, unique=True, index=True)
    department: Mapped[str | None] = mapped_column(String(255), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC))
    updated_at: Mapped[datetime] = mapped_column(DateTime, default=lambda: datetime.now(UTC), onupdate=datetime.now(UTC))

    requests: Mapped[list["ObservationRequest"]] = relationship(back_populates="user", foreign_keys="ObservationRequest.user_id")
