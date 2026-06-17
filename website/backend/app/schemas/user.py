import uuid
from datetime import datetime

from pydantic import BaseModel, EmailStr


class UserCreate(BaseModel):
    email: str
    name: str
    password: str | None = None
    role: str = "observer"
    user_type: str = "student"
    legacy_id: int | None = None


class UserOut(BaseModel):
    id: str
    legacy_id: int | None
    email: str
    name: str
    role: str
    user_type: str
    is_active: bool
    department: str | None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class UserUpdate(BaseModel):
    name: str | None = None
    role: str | None = None
    user_type: str | None = None
    is_active: bool | None = None
    department: str | None = None


class UserAdminCreate(BaseModel):
    email: str
    name: str
    role: str = "observer"
    user_type: str = "student"
    legacy_id: int | None = None
    department: str | None = None


class Token(BaseModel):
    access_token: str
    token_type: str = "bearer"


class TokenData(BaseModel):
    user_id: str | None = None


class LoginRequest(BaseModel):
    email: str
    password: str
