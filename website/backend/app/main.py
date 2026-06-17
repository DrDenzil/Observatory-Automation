from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.database import engine, Base, async_session
from app.models import User
from app.models.telescope import TelescopeConfig
from app.models.catalogue import SimbadCache  # noqa: F401 — ensures table is created
from app.services.auth import hash_password
from app.routes import auth, requests, jobs, runner, scopes, telescopes, users, users_import
from app.routes import catalogue


@asynccontextmanager
async def lifespan(app: FastAPI):
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    async with async_session() as db:
        from sqlalchemy import select
        result = await db.execute(select(User).where(User.email == "denis@herts.ac.uk"))
        if not result.scalar_one_or_none():
            db.add(User(email="denis@herts.ac.uk", name="Denis", hashed_password=hash_password("admin"), role="admin", user_type="staff", legacy_id=1, is_active=True))
            db.add(User(email="staff@herts.ac.uk", name="Sam", hashed_password=hash_password("staff"), role="staff", user_type="staff", is_active=True))
            db.add(User(email="observer@herts.ac.uk", name="Test Observer", hashed_password=hash_password("observer"), role="observer", user_type="student", is_active=True))
            await db.commit()

        tel_exists = await db.execute(select(TelescopeConfig).limit(1))
        if not tel_exists.scalar_one_or_none():
            db.add(TelescopeConfig(
                num=3, short_name="CKT", telescope="Meade LX200GPS 16-inch",
                aperture_mm=406, focal_length_mm=4064, camera="SBIG STX-16803",
                pixel_width_um=9, fov_w_arcmin=37.8, fov_h_arcmin=37.8,
                filters=["L", "R", "G", "B", "Ha", "OIII", "SII"],
                dec_lower=-30, dec_upper=90, min_binning=1,
                status="automatic", status_reason="Automation working routinely",
                scope_id="scope03",
            ))
            db.add(TelescopeConfig(
                num=5, short_name="PIRATE", telescope="14-inch Celestron",
                aperture_mm=356, focal_length_mm=3910, camera="Simulator",
                pixel_width_um=5.4, fov_w_arcmin=22.0, fov_h_arcmin=22.0,
                filters=["L", "R", "G", "B"],
                dec_lower=-20, dec_upper=85, min_binning=1,
                status="maintenance", status_reason="Testing new focuser",
                scope_id="scope05",
            ))
            await db.commit()

    yield

    await engine.dispose()


app = FastAPI(
    title="Bayfordbury Observatory",
    description="Observatory automation and observation management",
    version="1.0.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins.split(","),
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(auth.router)
app.include_router(requests.router)
app.include_router(jobs.router)
app.include_router(runner.router)
app.include_router(scopes.router)
app.include_router(telescopes.router)
app.include_router(catalogue.router)
app.include_router(users.router)
app.include_router(users_import.router)


@app.get("/api/health")
async def health():
    return {"status": "ok"}
