CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS documents (
    id BIGSERIAL PRIMARY KEY,
    file_name TEXT NOT NULL,
    file_type TEXT NOT NULL,
    source_path TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    status TEXT NOT NULL DEFAULT 'indexed',
    error_message TEXT,
    indexed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::jsonb;

ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS status TEXT NOT NULL DEFAULT 'indexed';

ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS error_message TEXT;

ALTER TABLE documents
    ADD COLUMN IF NOT EXISTS indexed_at TIMESTAMPTZ;

UPDATE documents
SET indexed_at = created_at
WHERE status = 'indexed' AND indexed_at IS NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'documents_status_check'
    ) THEN
        ALTER TABLE documents
            ADD CONSTRAINT documents_status_check
            CHECK (status IN ('processing', 'indexed', 'failed'));
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS document_chunks (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    embedding vector(1024) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (document_id, chunk_index)
);

CREATE INDEX IF NOT EXISTS document_chunks_document_id_idx
    ON document_chunks (document_id);

CREATE INDEX IF NOT EXISTS documents_status_idx
    ON documents (status);

CREATE INDEX IF NOT EXISTS documents_metadata_gin_idx
    ON documents USING gin (metadata);

CREATE INDEX IF NOT EXISTS document_chunks_metadata_gin_idx
    ON document_chunks USING gin (metadata);

CREATE INDEX IF NOT EXISTS document_chunks_embedding_hnsw_idx
    ON document_chunks USING hnsw (embedding vector_cosine_ops);
