from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    database_url: str = "sqlite+aiosqlite:///./observatory.db"
    redis_url: str = ""
    secret_key: str = "dev-secret-key-change-in-production"
    access_token_expire_minutes: int = 480
    # Shared secret the telescope runners present in the X-Runner-Key header.
    runner_api_key: str = "dev-runner-key-change-in-production"
    # Seconds without a heartbeat before a scope is considered offline.
    scope_offline_after_seconds: int = 120
    cors_origins: str = "http://localhost:5173,http://localhost:3000,http://localhost:3456,http://localhost:4321"

    model_config = {"env_file": ".env"}


settings = Settings()
