from collections.abc import Sequence
from typing import Any

from pgvector.psycopg import register_vector
from psycopg.types.json import Jsonb

from src.chunker import TextChunk
from src.db import get_connection


def reset_document(file_name: str, source_path: str) -> None:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                "DELETE FROM documents WHERE file_name = %s AND source_path = %s",
                (file_name, source_path),
            )


def create_document(
    file_name: str,
    file_type: str,
    source_path: str,
    status: str = "processing",
    error_message: str | None = None,
    metadata: dict[str, Any] | None = None,
) -> int:
    reset_document(file_name, source_path)
    document_metadata = metadata or {}

    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO documents
                    (file_name, file_type, source_path, metadata, status, error_message, indexed_at)
                VALUES
                    (%s, %s, %s, %s, %s, %s, CASE WHEN %s = 'indexed' THEN NOW() ELSE NULL END)
                RETURNING id
                """,
                (
                    file_name,
                    file_type,
                    source_path,
                    Jsonb(document_metadata),
                    status,
                    error_message,
                    status,
                ),
            )
            return int(cur.fetchone()[0])


def update_document_status(
    document_id: int,
    status: str,
    error_message: str | None = None,
) -> None:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE documents
                SET
                    status = %s,
                    error_message = %s,
                    indexed_at = CASE WHEN %s = 'indexed' THEN NOW() ELSE indexed_at END
                WHERE id = %s
                """,
                (status, error_message, status, document_id),
            )


def insert_chunks_for_document(
    document_id: int,
    chunks: Sequence[TextChunk],
    embeddings: Sequence[list[float]],
) -> None:
    if not chunks:
        raise ValueError("Tidak ada chunk untuk disimpan.")

    if len(chunks) != len(embeddings):
        raise ValueError("Jumlah chunks dan embeddings tidak sama.")

    with get_connection() as conn:
        register_vector(conn)
        with conn.cursor() as cur:
            rows = [
                (
                    document_id,
                    chunk_index,
                    chunk.content,
                    Jsonb(chunk.metadata),
                    embedding,
                )
                for chunk_index, (chunk, embedding) in enumerate(zip(chunks, embeddings))
            ]

            cur.execute("DELETE FROM document_chunks WHERE document_id = %s", (document_id,))
            cur.executemany(
                """
                INSERT INTO document_chunks
                    (document_id, chunk_index, content, metadata, embedding)
                VALUES (%s, %s, %s, %s, %s)
                """,
                rows,
            )
            cur.execute(
                """
                UPDATE documents
                SET status = 'indexed', error_message = NULL, indexed_at = NOW()
                WHERE id = %s
                """,
                (document_id,),
            )


def insert_document_chunks(chunks: Sequence[TextChunk], embeddings: Sequence[list[float]]) -> int:
    if not chunks:
        raise ValueError("Tidak ada chunk untuk disimpan.")

    if len(chunks) != len(embeddings):
        raise ValueError("Jumlah chunks dan embeddings tidak sama.")

    first = chunks[0]
    document_id = create_document(
        first.file_name,
        first.file_type,
        first.source_path,
        status="processing",
    )
    insert_chunks_for_document(document_id, chunks, embeddings)
    return document_id


def list_documents() -> list[dict[str, Any]]:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT
                    d.id,
                    d.file_name,
                    d.file_type,
                    d.source_path,
                    d.metadata,
                    d.status,
                    d.error_message,
                    d.indexed_at,
                    d.created_at,
                    COUNT(c.id) AS chunk_count
                FROM documents d
                LEFT JOIN document_chunks c ON c.document_id = d.id
                GROUP BY d.id
                ORDER BY d.created_at DESC
                """
            )
            rows = cur.fetchall()

    return [
        {
            "id": row[0],
            "file_name": row[1],
            "file_type": row[2],
            "source_path": row[3],
            "metadata": row[4],
            "status": row[5],
            "error_message": row[6],
            "indexed_at": row[7],
            "created_at": row[8],
            "chunk_count": row[9],
        }
        for row in rows
    ]


def delete_document(document_id: int) -> bool:
    with get_connection() as conn:
        with conn.cursor() as cur:
            cur.execute("DELETE FROM documents WHERE id = %s RETURNING id", (document_id,))
            return cur.fetchone() is not None
