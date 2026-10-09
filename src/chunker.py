from dataclasses import dataclass
from typing import Any

from src.data_loader import SourceRecord


@dataclass(frozen=True)
class TextChunk:
    file_name: str
    file_type: str
    source_path: str
    content: str
    metadata: dict[str, Any]


def chunk_text(text: str, chunk_size: int = 1200, overlap: int = 150) -> list[str]:
    text = " ".join(text.split())
    if len(text) <= chunk_size:
        return [text]

    chunks: list[str] = []
    start = 0

    while start < len(text):
        end = min(start + chunk_size, len(text))
        if end < len(text):
            sentence_end = max(text.rfind(".", start, end), text.rfind(";", start, end))
            if sentence_end > start + chunk_size // 2:
                end = sentence_end + 1

        chunk = text[start:end].strip()
        if chunk:
            chunks.append(chunk)

        if end >= len(text):
            break

        start = max(end - overlap, start + 1)

    return chunks


def chunk_records(
    records: list[SourceRecord],
    chunk_size: int = 1200,
    overlap: int = 150,
) -> list[TextChunk]:
    chunks: list[TextChunk] = []

    for record in records:
        for chunk_index, content in enumerate(chunk_text(record.text, chunk_size, overlap)):
            metadata = dict(record.metadata)
            metadata["chunk_in_record"] = chunk_index

            chunks.append(
                TextChunk(
                    file_name=record.file_name,
                    file_type=record.file_type,
                    source_path=record.source_path,
                    content=content,
                    metadata=metadata,
                )
            )

    return chunks
