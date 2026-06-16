"""
Idempotent database seed script.
Creates schema and default users if they don't already exist.

Usage:
    python seed.py
    python seed.py --reset   # drops and recreates all tables first

Environment variables (or .env file):
    DATABASE_URL  — defaults to sqlite+aiosqlite:///./observatory.db
    ADMIN_EMAIL   — defaults to admin@observatory.local
    ADMIN_PASSWORD — defaults to changeme (change in production!)
"""

import argparse
import asyncio
import os
import sys

from sqlalchemy import select

from app.database import Base, async_session, engine
from app.models.user import User
from app.services.auth import hash_password

DEFAULT_USERS = [
    {
        "email": os.getenv("ADMIN_EMAIL", "admin@observatory.local"),
        "name": "Administrator",
        "password": os.getenv("ADMIN_PASSWORD", "changeme"),
        "role": "admin",
    },
    {
        "email": os.getenv("STAFF_EMAIL", "staff@observatory.local"),
        "name": "Staff Member",
        "password": os.getenv("STAFF_PASSWORD", "changeme"),
        "role": "staff",
    },
    {
        "email": os.getenv("OBSERVER_EMAIL", "observer@observatory.local"),
        "name": "Observer",
        "password": os.getenv("OBSERVER_PASSWORD", "changeme"),
        "role": "observer",
    },
]


async def create_schema(reset: bool = False) -> None:
    async with engine.begin() as conn:
        if reset:
            await conn.run_sync(Base.metadata.drop_all)
            print("Dropped all tables.")
        await conn.run_sync(Base.metadata.create_all)
    print("Schema ready.")


async def seed_users() -> None:
    async with async_session() as session:
        for spec in DEFAULT_USERS:
            result = await session.execute(select(User).where(User.email == spec["email"]))
            existing = result.scalar_one_or_none()
            if existing:
                print(f"  skip  {spec['email']} (already exists, role={existing.role})")
                continue
            user = User(
                email=spec["email"],
                name=spec["name"],
                hashed_password=hash_password(spec["password"]),
                role=spec["role"],
            )
            session.add(user)
            print(f"  create {spec['email']} (role={spec['role']})")
        await session.commit()


async def main(reset: bool = False) -> None:
    # Import all models so metadata is populated
    import app.models.job  # noqa: F401
    import app.models.request  # noqa: F401
    import app.models.image  # noqa: F401

    print("=== Observatory Seed ===")
    await create_schema(reset=reset)
    print("Seeding users...")
    await seed_users()
    print("Done.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Seed the observatory database.")
    parser.add_argument("--reset", action="store_true", help="Drop and recreate all tables first.")
    args = parser.parse_args()

    if args.reset and "--reset" in sys.argv:
        confirm = input("This will DELETE all data. Type 'yes' to confirm: ")
        if confirm.strip().lower() != "yes":
            print("Aborted.")
            sys.exit(0)

    asyncio.run(main(reset=args.reset))
