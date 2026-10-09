from dataclasses import dataclass
import re
from typing import Any

from pgvector.psycopg import register_vector

from src.config import RAG_TOP_K
from src.db import get_connection
from src.embedder import Embedder


@dataclass(frozen=True)
class SearchResult:
    content: str
    file_name: str
    file_type: str
    metadata: dict[str, Any]
    distance: float


class Retriever:
    def __init__(self) -> None:
        self.embedder = Embedder()

    def _query_terms(self, question: str) -> list[str]:
        stopwords = {
            "ada",
            "apa",
            "saja",
            "yang",
            "dan",
            "atau",
            "pada",
            "di",
            "ke",
            "dari",
            "itu",
            "ini",
            "berapa",
            "jumlah",
            "pekon",
            "desa",
            "kelurahan",
            "margodadi",
        }
        words = re.findall(r"[a-zA-Z0-9]+", question.lower())
        terms = []
        for word in words:
            if len(word) < 4 or word in stopwords:
                continue
            if word not in terms:
                terms.append(word)
        return terms[:8]

    def _hybrid_score(self, row: tuple[Any, ...], terms: list[str]) -> float:
        content = row[1].lower()
        matches = sum(1 for term in terms if term in content)
        phrase = " ".join(terms)
        phrase_boost = 0.10 if len(terms) > 1 and phrase in content else 0.0
        keyword_boost = min(0.32, matches * 0.08) + phrase_boost
        return float(row[5]) - keyword_boost

    def search(self, question: str, top_k: int = RAG_TOP_K) -> list[SearchResult]:
        query_embedding = self.embedder.encode([question])[0]
        terms = self._query_terms(question)
        candidate_limit = max(top_k * 5, 30)

        with get_connection() as conn:
            register_vector(conn)
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT
                        c.id,
                        c.content,
                        d.file_name,
                        d.file_type,
                        c.metadata,
                        c.embedding <=> %s::vector AS distance
                    FROM document_chunks c
                    JOIN documents d ON d.id = c.document_id
                    ORDER BY c.embedding <=> %s::vector
                    LIMIT %s
                    """,
                    (query_embedding, query_embedding, candidate_limit),
                )
                rows = list(cur.fetchall())

                if terms:
                    conditions = " OR ".join(["c.content ILIKE %s"] * len(terms))
                    params = [f"%{term}%" for term in terms]
                    cur.execute(
                        f"""
                        SELECT
                            c.id,
                            c.content,
                            d.file_name,
                            d.file_type,
                            c.metadata,
                            c.embedding <=> %s::vector AS distance
                        FROM document_chunks c
                        JOIN documents d ON d.id = c.document_id
                        WHERE {conditions}
                        ORDER BY c.embedding <=> %s::vector
                        LIMIT %s
                        """,
                        (query_embedding, *params, query_embedding, candidate_limit),
                    )
                    rows.extend(cur.fetchall())

        unique_rows = {}
        for row in rows:
            unique_rows[row[0]] = row

        ranked_rows = sorted(
            unique_rows.values(),
            key=lambda row: self._hybrid_score(row, terms),
        )[:top_k]

        return [
            SearchResult(
                content=row[1],
                file_name=row[2],
                file_type=row[3],
                metadata=row[4],
                distance=float(row[5]),
            )
            for row in ranked_rows
        ]
