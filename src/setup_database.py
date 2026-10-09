from pathlib import Path

from src.db import get_connection


def main() -> None:
    schema_path = Path(__file__).with_name("schema.sql")
    schema_sql = schema_path.read_text(encoding="utf-8")

    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(schema_sql)

    print("Database schema berhasil disiapkan.")


if __name__ == "__main__":
    main()
