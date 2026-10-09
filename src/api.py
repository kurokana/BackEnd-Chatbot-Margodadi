from functools import lru_cache
from pathlib import Path
import re
from typing import Any

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from src.config import UPLOAD_DIR
from src.indexer import DocumentIndexer
from src.rag import RAGService
from src.vector_store import delete_document, list_documents


class QueryRequest(BaseModel):
    question: str = Field(min_length=1)
    top_k: int = Field(default=3, ge=1, le=20)


class TextDocumentRequest(BaseModel):
    title: str = Field(min_length=1, max_length=255)
    content: str = Field(min_length=1)
    domain: str | None = None
    source: str | None = None
    validator: str | None = None
    version: str | None = None
    is_active: bool = True
    laravel_document_id: int | None = None


class SourceResponse(BaseModel):
    file_name: str
    file_type: str
    metadata: dict[str, Any]
    distance: float
    content: str


class SearchResponse(BaseModel):
    question: str
    sources: list[SourceResponse]


class ChatResponse(SearchResponse):
    answer: str


class UploadResponse(BaseModel):
    document_id: int
    file_name: str
    file_type: str
    chunks: int
    status: str
    metadata: dict[str, Any]


class DocumentResponse(BaseModel):
    id: int
    file_name: str
    file_type: str
    source_path: str | None
    metadata: dict[str, Any]
    status: str
    error_message: str | None
    indexed_at: str | None
    created_at: str
    chunk_count: int


class DeleteResponse(BaseModel):
    document_id: int
    deleted: bool


@lru_cache(maxsize=1)
def get_rag_service() -> RAGService:
    return RAGService()


def _format_source(result) -> SourceResponse:
    return SourceResponse(
        file_name=result.file_name,
        file_type=result.file_type,
        metadata=result.metadata,
        distance=result.distance,
        content=result.content,
    )


def _safe_filename(filename: str) -> str:
    raw_name = Path(filename).name.strip()
    safe_name = re.sub(r"[^A-Za-z0-9._-]+", "_", raw_name)
    return safe_name or "uploaded_file"


def _document_metadata(
    title: str | None = None,
    domain: str | None = None,
    source: str | None = None,
    validator: str | None = None,
    version: str | None = None,
    is_active: bool = True,
    laravel_document_id: int | None = None,
) -> dict[str, Any]:
    metadata: dict[str, Any] = {
        "title": title,
        "domain": domain,
        "source": source,
        "validator": validator,
        "version": version,
        "is_active": is_active,
        "laravel_document_id": laravel_document_id,
    }
    return {key: value for key, value in metadata.items() if value not in (None, "")}


app = FastAPI(title="Skripsi RAG Gateway", version="0.1.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/", include_in_schema=False)
def api_root() -> dict[str, str]:
    return {
        "name": "Skripsi RAG Gateway",
        "status": "ok",
        "docs": "/docs",
    }


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.get("/documents", response_model=list[DocumentResponse])
def documents() -> list[DocumentResponse]:
    return [
        DocumentResponse(
            id=int(document["id"]),
            file_name=document["file_name"],
            file_type=document["file_type"],
            source_path=document["source_path"],
            metadata=document["metadata"] or {},
            status=document["status"],
            error_message=document["error_message"],
            indexed_at=(
                document["indexed_at"].isoformat()
                if document["indexed_at"] is not None
                else None
            ),
            created_at=document["created_at"].isoformat(),
            chunk_count=int(document["chunk_count"]),
        )
        for document in list_documents()
    ]


@app.post("/documents/upload", response_model=UploadResponse)
async def upload_document(
    file: UploadFile = File(...),
    title: str | None = Form(None),
    domain: str | None = Form(None),
    source: str | None = Form(None),
    validator: str | None = Form(None),
    version: str | None = Form(None),
    is_active: bool = Form(True),
    laravel_document_id: int | None = Form(None),
) -> UploadResponse:
    if not file.filename:
        raise HTTPException(status_code=400, detail="Nama file kosong.")

    suffix = Path(file.filename).suffix.lower()
    allowed_suffixes = {".pdf", ".xlsx", ".xls", ".docx", ".txt"}
    if suffix not in allowed_suffixes:
        raise HTTPException(
            status_code=415,
            detail="Format file belum didukung. Gunakan PDF, Excel, DOCX, atau TXT.",
        )

    UPLOAD_DIR.mkdir(parents=True, exist_ok=True)
    safe_name = _safe_filename(file.filename)
    target_path = UPLOAD_DIR / safe_name
    target_path.write_bytes(await file.read())

    service = get_rag_service()
    indexer = DocumentIndexer(embedder=service.retriever.embedder)
    metadata = _document_metadata(
        title=title,
        domain=domain,
        source=source,
        validator=validator,
        version=version,
        is_active=is_active,
        laravel_document_id=laravel_document_id,
    )

    try:
        document_id, chunk_count = indexer.index_file(target_path, metadata)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    return UploadResponse(
        document_id=document_id,
        file_name=safe_name,
        file_type=suffix.lstrip("."),
        chunks=chunk_count,
        status="indexed",
        metadata=metadata,
    )


@app.post("/documents/text", response_model=UploadResponse)
def upload_text_document(request: TextDocumentRequest) -> UploadResponse:
    service = get_rag_service()
    indexer = DocumentIndexer(embedder=service.retriever.embedder)
    safe_name = _safe_filename(f"{request.title}.txt")
    source_path = (
        f"laravel://kb_documents/{request.laravel_document_id}"
        if request.laravel_document_id
        else f"text://{safe_name}"
    )
    metadata = _document_metadata(
        title=request.title,
        domain=request.domain,
        source=request.source,
        validator=request.validator,
        version=request.version,
        is_active=request.is_active,
        laravel_document_id=request.laravel_document_id,
    )

    try:
        document_id, chunk_count = indexer.index_text(
            safe_name,
            source_path,
            request.content,
            metadata,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    return UploadResponse(
        document_id=document_id,
        file_name=safe_name,
        file_type="txt",
        chunks=chunk_count,
        status="indexed",
        metadata=metadata,
    )


@app.delete("/documents/{document_id}", response_model=DeleteResponse)
def remove_document(document_id: int) -> DeleteResponse:
    return DeleteResponse(document_id=document_id, deleted=delete_document(document_id))


@app.post("/search", response_model=SearchResponse)
def search(request: QueryRequest) -> SearchResponse:
    service = get_rag_service()
    results = service.search(request.question, request.top_k)
    return SearchResponse(
        question=request.question,
        sources=[_format_source(result) for result in results],
    )


@app.post("/chat", response_model=ChatResponse)
def chat(request: QueryRequest) -> ChatResponse:
    service = get_rag_service()
    try:
        answer, results = service.chat(request.question, request.top_k)
    except RuntimeError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc

    return ChatResponse(
        question=request.question,
        answer=answer,
        sources=[_format_source(result) for result in results],
    )
