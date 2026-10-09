import torch
from sentence_transformers import SentenceTransformer

from src.config import EMBEDDING_MODEL_PATH


class Embedder:
    def __init__(self) -> None:
        device = "cuda" if torch.cuda.is_available() else "cpu"
        self.model = SentenceTransformer(
            str(EMBEDDING_MODEL_PATH),
            device=device,
            local_files_only=True,
        )

    def encode(self, texts: list[str]) -> list[list[float]]:
        embeddings = self.model.encode(
            texts,
            normalize_embeddings=True,
            convert_to_numpy=True,
            show_progress_bar=True,
        )
        return embeddings.tolist()
