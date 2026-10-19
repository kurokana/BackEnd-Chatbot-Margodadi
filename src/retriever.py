from dataclasses import dataclass
import json
import re
from typing import Any

from src.config import RAG_TOP_K
from src.db import get_connection
from src.document_policy import SEARCHABLE_DOCUMENT_SQL
from src.embedder import Embedder, cosine_similarity

try:
    from pgvector.psycopg import register_vector
    HAS_PGVECTOR_LIB = True
except ImportError:
    HAS_PGVECTOR_LIB = False


def _has_vector_type(conn) -> bool:
    if not HAS_PGVECTOR_LIB:
        return False
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT 1 FROM pg_type WHERE typname = 'vector'")
            return bool(cur.fetchone())
    except Exception:
        return False


@dataclass(frozen=True)
class SearchResult:
    content: str
    file_name: str
    file_type: str
    metadata: dict[str, Any]
    distance: float


class Retriever:
    STOPWORDS = {
        "ada",
        "apa",
        "apakah",
        "saja",
        "siapa",
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

    SHORT_INFORMATIVE_WORDS = {
        "umkm", "sku", "ktp", "kk", "rt", "rw", "bhp", "blt", "pkh", "sktm", "ntcr", "nib", "pbb"
    }

    def __init__(self) -> None:
        self.embedder = Embedder()

    def _normalized_question(self, question: str) -> str:
        normalized = question.lower()
        replacements = {
            "masyarakat": "penduduk",
            "warga": "penduduk",
        }

        for source, target in replacements.items():
            normalized = re.sub(rf"(?<!\w){source}(?!\w)", target, normalized)

        normalized = re.sub(r"\b([ds])\s*-?\s*(\d+)\b", r"\1-\2", normalized)
        return normalized

    def _query_terms(self, question: str) -> list[str]:
        normalized = self._normalized_question(question)
        words = re.findall(r"[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)?", normalized)
        terms = []
        for word in words:
            is_level_code = bool(re.fullmatch(r"[a-z]-\d+", word))
            if (
                (len(word) < 4 and not is_level_code and word not in self.SHORT_INFORMATIVE_WORDS)
                or word in self.STOPWORDS
            ):
                continue
            if word not in terms:
                terms.append(word)
        return terms[:8]

    def _query_phrases(self, question: str) -> list[str]:
        normalized = self._normalized_question(question)
        words = re.findall(r"[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)?", normalized)
        phrases = []

        for size in (3, 2):
            for index in range(len(words) - size + 1):
                chunk = words[index : index + size]
                has_informative_word = any(
                    (len(word) >= 4 or word in self.SHORT_INFORMATIVE_WORDS)
                    and word not in self.STOPWORDS
                    for word in chunk
                )
                if not has_informative_word:
                    continue

                phrase = " ".join(chunk)
                if phrase not in phrases:
                    phrases.append(phrase)

        return phrases[:12]

    @staticmethod
    def _exact_count(content: str, term: str) -> int:
        return len(re.findall(rf"(?<!\w){re.escape(term)}(?!\w)", content))

    def _hybrid_score(
        self,
        row: tuple[Any, ...],
        terms: list[str],
        phrases: list[str],
    ) -> float:
        content = row[1].lower()
        file_name = (row[2] or "").lower()
        exact_counts = [self._exact_count(content, term) for term in terms]
        exact_matches = sum(1 for count in exact_counts if count > 0)
        total_occurrences = sum(exact_counts)
        phrase_matches = sum(1 for phrase in phrases if phrase in content)

        exact_boost = min(0.60, exact_matches * 0.24)
        occurrence_boost = min(0.16, max(0, total_occurrences - exact_matches) * 0.03)
        phrase_boost = min(0.40, phrase_matches * 0.20)

        # Overview / authoritative directory boost for aggregate product queries
        title_boost = 0.0
        normalized_filename = file_name.replace("_", " ")
        if any(term in normalized_filename for term in terms):
            title_boost += 0.12
        if any(phrase in normalized_filename for phrase in phrases):
            title_boost += 0.20
        if ("produk" in terms or "unggulan" in terms or "potensi" in terms) and "direktori_potensi" in file_name:
            title_boost += 0.35

        keyword_boost = exact_boost + occurrence_boost + phrase_boost + title_boost
        return float(row[5]) - keyword_boost

    def search(self, question: str, top_k: int = RAG_TOP_K) -> list[SearchResult]:
        query_embedding = self.embedder.encode([question])[0]
        terms = self._query_terms(question)
        phrases = self._query_phrases(question)
        candidate_limit = max(top_k * 5, 30)

        with get_connection() as conn:
            has_vector = _has_vector_type(conn)

            if has_vector:
                register_vector(conn)
                with conn.cursor() as cur:
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
                        WHERE {SEARCHABLE_DOCUMENT_SQL}
                        ORDER BY c.embedding <=> %s::vector
                        LIMIT %s
                        """,
                        (query_embedding, query_embedding, candidate_limit),
                    )
                    rows = list(cur.fetchall())

                    keyword_queries = [*phrases, *terms]
                    if keyword_queries:
                        conditions = " OR ".join(["c.content ILIKE %s"] * len(keyword_queries))
                        params = [f"%{query}%" for query in keyword_queries]
                        rank_expr = " + ".join(
                            ["CASE WHEN c.content ILIKE %s THEN 1 ELSE 0 END"] * len(keyword_queries)
                        )
                        keyword_limit = max(top_k * 20, 80)
                        cur.execute(
                            f"""
                            SELECT
                                c.id,
                                c.content,
                                d.file_name,
                                d.file_type,
                                c.metadata,
                                c.embedding <=> %s::vector AS distance,
                                ({rank_expr}) AS keyword_rank
                            FROM document_chunks c
                            JOIN documents d ON d.id = c.document_id
                            WHERE ({SEARCHABLE_DOCUMENT_SQL}) AND ({conditions})
                            ORDER BY keyword_rank DESC, c.embedding <=> %s::vector
                            LIMIT %s
                            """,
                            (
                                query_embedding,
                                *params,
                                *params,
                                query_embedding,
                                keyword_limit,
                            ),
                        )
                        rows.extend(cur.fetchall())
            else:
                with conn.cursor() as cur:
                    cur.execute(
                        f"""
                        SELECT
                            c.id,
                            c.content,
                            d.file_name,
                            d.file_type,
                            c.metadata,
                            c.embedding
                        FROM document_chunks c
                        JOIN documents d ON d.id = c.document_id
                        WHERE {SEARCHABLE_DOCUMENT_SQL}
                        """
                    )
                    raw_rows = cur.fetchall()

                rows = []
                for r in raw_rows:
                    cid, content, fname, ftype, meta, emb_raw = r
                    if isinstance(emb_raw, str):
                        try:
                            emb = json.loads(emb_raw)
                        except Exception:
                            emb = []
                    elif isinstance(emb_raw, (list, tuple)):
                        emb = list(emb_raw)
                    else:
                        emb = []

                    if emb:
                        sim = cosine_similarity(query_embedding, emb)
                        dist = max(0.0, 1.0 - sim)
                    else:
                        dist = 1.0
                    rows.append((cid, content, fname, ftype, meta, dist))

        unique_rows = {}
        for row in rows:
            unique_rows[row[0]] = row

        ranked_rows = sorted(
            unique_rows.values(),
            key=lambda row: self._hybrid_score(row, terms, phrases),
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
