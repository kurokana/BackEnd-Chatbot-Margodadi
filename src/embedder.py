from __future__ import annotations

from abc import ABC, abstractmethod
import json
import math
import time
from typing import Any
import urllib.error
import urllib.request

from src.config import (
    EMBEDDING_API_BASE_URL,
    EMBEDDING_API_KEY,
    EMBEDDING_API_MODEL,
    EMBEDDING_API_PATH,
    EMBEDDING_API_TIMEOUT,
    EMBEDDING_BATCH_SIZE,
    EMBEDDING_DIMENSION,
    EMBEDDING_MODEL_PATH,
    EMBEDDING_PROVIDER,
)

# Coba import httpx jika tersedia
try:
    import httpx
    HAS_HTTPX = True
except ImportError:
    HAS_HTTPX = False


def normalize_vector(vec: list[float]) -> list[float]:
    """L2 Normalization untuk memastikan panjang vektor adalah 1.0."""
    norm = math.sqrt(sum(val * val for val in vec))
    if norm == 0 or math.isclose(norm, 1.0, rel_tol=1e-5):
        return vec
    return [val / norm for val in vec]


def cosine_similarity(vec_a: list[float], vec_b: list[float]) -> float:
    """Menghitung cosine similarity antara dua vektor float."""
    if len(vec_a) != len(vec_b):
        raise ValueError(
            f"Dimensi vektor tidak cocok: {len(vec_a)} vs {len(vec_b)}"
        )
    norm_a = math.sqrt(sum(x * x for x in vec_a))
    norm_b = math.sqrt(sum(y * y for y in vec_b))
    if norm_a == 0 or norm_b == 0:
        return 0.0
    dot = sum(a * b for a, b in zip(vec_a, vec_b))
    return dot / (norm_a * norm_b)


class BaseEmbedder(ABC):
    """Kelas dasar abstrak untuk semua embedding provider."""

    @abstractmethod
    def encode(self, texts: list[str]) -> list[list[float]]:
        """Menghasilkan embedding vector untuk daftar teks."""
        pass


class APIEmbedder(BaseEmbedder):
    """
    Wadah Embedding AI berbasis API (Cloud).
    Mendukung OpenAI, Google Gemini (OpenAI-compatible / REST),
    serta provider kompatibel lainnya (Ollama, OpenRouter, Mistral, dll).
    """

    def __init__(
        self,
        api_key: str | None = None,
        base_url: str | None = None,
        path: str | None = None,
        model: str | None = None,
        dimension: int | None = None,
        timeout: float | None = None,
        batch_size: int | None = None,
    ) -> None:
        self.api_key = (api_key or EMBEDDING_API_KEY).strip()
        if not self.api_key:
            raise RuntimeError(
                "EMBEDDING_API_KEY belum diatur di file .env.\n"
                "Silakan isi EMBEDDING_API_KEY (atau LLM_API_KEY) di file .env "
                "untuk menggunakan embedding AI."
            )

        self.base_url = (base_url or EMBEDDING_API_BASE_URL).rstrip("/")
        self.path = "/" + (path or EMBEDDING_API_PATH).strip("/")
        self.model = (model or EMBEDDING_API_MODEL).strip()
        self.dimension = dimension or EMBEDDING_DIMENSION
        self.timeout = timeout if timeout is not None else EMBEDDING_API_TIMEOUT
        self.batch_size = max(1, batch_size or EMBEDDING_BATCH_SIZE)

    def _send_request_httpx(self, url: str, headers: dict[str, str], payload: dict[str, Any]) -> dict[str, Any]:
        """Kirim request menggunakan library httpx dengan retry cerdas saat rate limit (429)."""
        max_retries = 6
        with httpx.Client(timeout=self.timeout) as client:
            for attempt in range(max_retries):
                try:
                    response = client.post(url, headers=headers, json=payload)
                except (httpx.TimeoutException, httpx.RequestError) as exc:
                    if attempt < max_retries - 1:
                        time.sleep(3)
                        continue
                    raise RuntimeError(f"Koneksi Embedding API gagal: {exc}") from exc

                if response.status_code in (401, 403):
                    raise RuntimeError(
                        f"Autentikasi Embedding API gagal ({response.status_code}): "
                        f"API key salah atau tidak memiliki akses. Detail: {response.text[:300]}"
                    )

                if response.status_code == 429:
                    if attempt < max_retries - 1:
                        wait_seconds = (attempt + 1) * 7
                        print(f"⏳ Rate limit API (429) tercapai. Menunggu {wait_seconds} detik sebelum mencoba lagi (percobaan {attempt + 1}/{max_retries})...")
                        time.sleep(wait_seconds)
                        continue
                    raise RuntimeError(
                        f"Embedding API mencapai batas kuota / rate limit (429): "
                        f"{response.text[:300]}"
                    )

                try:
                    response.raise_for_status()
                except httpx.HTTPStatusError as exc:
                    raise RuntimeError(
                        f"Embedding API error ({exc.response.status_code}): {exc.response.text[:500]}"
                    ) from exc

                return response.json()

    def _send_request_urllib(self, url: str, headers: dict[str, str], payload: dict[str, Any]) -> dict[str, Any]:
        """Kirim request menggunakan standard library urllib dengan retry cerdas saat 429."""
        data_bytes = json.dumps(payload).encode("utf-8")
        max_retries = 6
        for attempt in range(max_retries):
            req = urllib.request.Request(url, data=data_bytes, headers=headers, method="POST")
            try:
                with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                    response_text = resp.read().decode("utf-8")
                    return json.loads(response_text)
            except urllib.error.HTTPError as exc:
                detail = exc.read().decode("utf-8", errors="ignore")[:400]
                if exc.code in (401, 403):
                    raise RuntimeError(
                        f"Autentikasi Embedding API gagal ({exc.code}): "
                        f"API key salah atau tidak memiliki akses. Detail: {detail}"
                    ) from exc
                if exc.code == 429:
                    if attempt < max_retries - 1:
                        wait_seconds = (attempt + 1) * 7
                        print(f"⏳ Rate limit API (429) tercapai. Menunggu {wait_seconds} detik sebelum mencoba lagi...")
                        time.sleep(wait_seconds)
                        continue
                    raise RuntimeError(
                        f"Embedding API mencapai batas kuota / rate limit (429): {detail}"
                    ) from exc
                raise RuntimeError(f"Embedding API error ({exc.code}): {detail}") from exc
            except urllib.error.URLError as exc:
                if attempt < max_retries - 1:
                    time.sleep(3)
                    continue
                raise RuntimeError(f"Koneksi ke Embedding API gagal: {exc.reason}") from exc

    def _send_request(self, url: str, headers: dict[str, str], payload: dict[str, Any]) -> dict[str, Any]:
        if HAS_HTTPX:
            return self._send_request_httpx(url, headers, payload)
        return self._send_request_urllib(url, headers, payload)

    def _encode_batch(self, batch_texts: list[str]) -> list[list[float]]:
        """Encode satu batch teks melalui API endpoint."""
        # Cek apakah endpoint Google Gemini native REST (non-openai)
        is_gemini_native = (
            "generativelanguage.googleapis.com" in self.base_url
            and "/openai" not in self.base_url
        )

        if is_gemini_native:
            # Format Native Google AI Gemini REST API
            model_name = self.model.removeprefix("models/")
            url = f"{self.base_url}/models/{model_name}:batchEmbedContents?key={self.api_key}"
            headers = {"Content-Type": "application/json"}
            payload = {
                "requests": [
                    {
                        "model": f"models/{model_name}",
                        "content": {"parts": [{"text": text}]},
                    }
                    for text in batch_texts
                ]
            }
            data = self._send_request(url, headers, payload)

            raw_embeddings = [
                item.get("values", [])
                for item in data.get("embeddings", [])
            ]
            if len(raw_embeddings) != len(batch_texts):
                raise RuntimeError(
                    f"Jumlah embedding hasil API ({len(raw_embeddings)}) "
                    f"tidak cocok dengan jumlah input ({len(batch_texts)})."
                )
            return [normalize_vector(vec) for vec in raw_embeddings]

        # Format Standar OpenAI-Compatible (Termasuk Google Gemini via /v1beta/openai)
        url = f"{self.base_url}{self.path}"
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        payload = {
            "model": self.model,
            "input": batch_texts,
        }

        # Tambahkan dimensions jika didukung model (misal text-embedding-3-small, gemini-embedding-001)
        if ("text-embedding-3" in self.model or "gemini-embedding" in self.model) and self.dimension:
            payload["dimensions"] = self.dimension

        data = self._send_request(url, headers, payload)

        items = data.get("data", [])
        if not items:
            raise RuntimeError(f"Format response API tidak memiliki field 'data': {data}")

        # Urutkan berdasarkan index agar urutan embedding sesuai dengan urutan input
        items_sorted = sorted(items, key=lambda x: x.get("index", 0))
        embeddings = [normalize_vector(item["embedding"]) for item in items_sorted]

        if len(embeddings) != len(batch_texts):
            raise RuntimeError(
                f"Jumlah embedding ({len(embeddings)}) tidak sama dengan "
                f"jumlah input ({len(batch_texts)})."
            )

        return embeddings

    def encode(self, texts: list[str]) -> list[list[float]]:
        """
        Encode daftar teks menjadi daftar vektor float.
        Otomatis membagi ke dalam batch kecil agar hemat memori & aman dari rate limit.
        """
        if isinstance(texts, str):
            texts = [texts]

        if not texts:
            return []

        all_embeddings: list[list[float]] = []

        for i in range(0, len(texts), self.batch_size):
            batch = texts[i : i + self.batch_size]
            batch_embeddings = self._encode_batch(batch)
            all_embeddings.extend(batch_embeddings)
            if i + self.batch_size < len(texts):
                time.sleep(1.2)

        return all_embeddings


class LocalEmbedder(BaseEmbedder):
    """
    Wadah Embedding lokal menggunakan SentenceTransformer & PyTorch.
    Digunakan saat tidak menggunakan API atau berjalan offline.
    """

    def __init__(self, model_path: str | None = None) -> None:
        self.model_path = str(model_path or EMBEDDING_MODEL_PATH)
        self._model = None

    def _load_model(self):
        if self._model is not None:
            return self._model

        try:
            import torch  # type: ignore # pyright: ignore[reportMissingImports]
            from sentence_transformers import SentenceTransformer  # type: ignore # pyright: ignore[reportMissingImports]
        except ImportError as exc:
            raise RuntimeError(
                "Library 'sentence-transformers' dan 'torch' diperlukan "
                "untuk mode lokal. Silakan gunakan EMBEDDING_PROVIDER=api "
                "atau jalankan 'pip install sentence-transformers torch'."
            ) from exc

        device = "cuda" if torch.cuda.is_available() else "cpu"
        self._model = SentenceTransformer(
            self.model_path,
            device=device,
            local_files_only=True,
        )
        return self._model

    def encode(self, texts: list[str]) -> list[list[float]]:
        if isinstance(texts, str):
            texts = [texts]

        if not texts:
            return []

        model = self._load_model()
        embeddings = model.encode(
            texts,
            normalize_embeddings=True,
            convert_to_numpy=True,
            show_progress_bar=len(texts) > 20,
        )
        return embeddings.tolist()


class Embedder(BaseEmbedder):
    """
    Facade Terpadu Embedder Margodadi.
    Secara otomatis memilih antara APIEmbedder dan LocalEmbedder
    berdasarkan konfigurasi environment (EMBEDDING_PROVIDER).
    """

    def __init__(self, provider: str | None = None) -> None:
        selected_provider = (provider or EMBEDDING_PROVIDER).lower().strip()

        if selected_provider == "local":
            self.provider_name = "local"
            self.engine: BaseEmbedder = LocalEmbedder()
        else:
            self.provider_name = "api"
            self.engine = APIEmbedder()

    def encode(self, texts: list[str]) -> list[list[float]]:
        return self.engine.encode(texts)

    @staticmethod
    def compute_similarity(vec_a: list[float], vec_b: list[float]) -> float:
        """Menghitung skor cosine similarity antara dua vektor embedding."""
        return cosine_similarity(vec_a, vec_b)
