import psycopg

from src.config import DATABASE_URL


def get_connection() -> psycopg.Connection:
    if not DATABASE_URL:
        raise RuntimeError("DATABASE_URL belum diatur. Isi file .env terlebih dahulu.")

    return psycopg.connect(DATABASE_URL, connect_timeout=5)
