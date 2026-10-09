from collections import defaultdict
from pathlib import Path
from typing import Any

from src.chunker import TextChunk, chunk_records
from src.data_loader import SourceRecord
from src.data_loader import load_data_dir, load_file, supported_file_type
from src.embedder import Embedder
from src.vector_store import (
    create_document,
    insert_chunks_for_document,
    insert_document_chunks,
    reset_document,
    update_document_status,
)


class DocumentIndexer:
    def __init__(self, embedder: Embedder | None = None) -> None:
        self.embedder = embedder or Embedder()

    def _merge_metadata(
        self,
        chunks: list[TextChunk],
        document_metadata: dict[str, Any] | None = None,
    ) -> list[TextChunk]:
        if not document_metadata:
            return chunks

        return [
            TextChunk(
                file_name=chunk.file_name,
                file_type=chunk.file_type,
                source_path=chunk.source_path,
                content=chunk.content,
                metadata={**document_metadata, **chunk.metadata},
            )
            for chunk in chunks
        ]

    def index_chunks(self, chunks: list[TextChunk]) -> int:
        if not chunks:
            raise ValueError("File tidak menghasilkan teks yang bisa di-index.")

        first = chunks[0]
        reset_document(first.file_name, first.source_path)
        embeddings = self.embedder.encode([chunk.content for chunk in chunks])
        return insert_document_chunks(chunks, embeddings)

    def index_file(
        self,
        path: Path,
        document_metadata: dict[str, Any] | None = None,
    ) -> tuple[int, int]:
        file_type = supported_file_type(path)
        document_id = create_document(
            path.name,
            file_type,
            str(path),
            status="processing",
            metadata=document_metadata,
        )

        try:
            records = load_file(path)
            chunks = self._merge_metadata(chunk_records(records), document_metadata)
            if not chunks:
                raise ValueError("File tidak menghasilkan teks yang bisa di-index.")

            embeddings = self.embedder.encode([chunk.content for chunk in chunks])
            insert_chunks_for_document(document_id, chunks, embeddings)
            return document_id, len(chunks)
        except Exception as exc:
            update_document_status(document_id, "failed", str(exc))
            raise

    def index_text(
        self,
        file_name: str,
        source_path: str,
        text: str,
        document_metadata: dict[str, Any] | None = None,
    ) -> tuple[int, int]:
        document_id = create_document(
            file_name,
            "txt",
            source_path,
            status="processing",
            metadata=document_metadata,
        )

        try:
            record = SourceRecord(
                file_name=file_name,
                file_type="txt",
                source_path=source_path,
                text=text,
                metadata={"block": 1},
            )
            chunks = self._merge_metadata(chunk_records([record]), document_metadata)
            if not chunks:
                raise ValueError("Teks tidak menghasilkan chunk yang bisa di-index.")

            embeddings = self.embedder.encode([chunk.content for chunk in chunks])
            insert_chunks_for_document(document_id, chunks, embeddings)
            return document_id, len(chunks)
        except Exception as exc:
            update_document_status(document_id, "failed", str(exc))
            raise

    def index_data_dir(self, data_dir: Path) -> tuple[int, int, int]:
        records = load_data_dir(data_dir)
        chunks = chunk_records(records)

        grouped: dict[tuple[str, str], list[TextChunk]] = defaultdict(list)
        for chunk in chunks:
            grouped[(chunk.file_name, chunk.source_path)].append(chunk)

        for document_chunks in grouped.values():
            self.index_chunks(document_chunks)

        return len(records), len(chunks), len(grouped)
