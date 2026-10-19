from __future__ import annotations

import json
import re
import sys
import time
from pathlib import Path
from typing import Any

if sys.stdout and hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if sys.stderr and hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

ROOT_DIR = Path(__file__).resolve().parent.parent
if str(ROOT_DIR) not in sys.path:
    sys.path.insert(0, str(ROOT_DIR))

from src.chunker import TextChunk, chunk_records
from src.data_loader import SourceRecord
from src.db import get_connection
from src.embedder import Embedder
from src.retriever import Retriever
from src.setup_database import main as init_schema
from src.vector_store import (
    create_document,
    insert_chunks_for_document,
)


def clean_filename(title: str) -> str:
    raw = re.sub(r"[^A-Za-z0-9._-]+", "_", title.strip())
    return raw[:100] or "doc"


def build_umkm_markdown(umkm: dict[str, Any], products: list[dict[str, Any]]) -> str:
    category_name = umkm.get("category_name") or "UMKM Mandiri Desa"
    name = umkm.get("name", "UMKM")
    reg_number = umkm.get("reg_number") or "-"
    sub_title = umkm.get("sub_title") or f"Usaha {category_name}"
    owner_name = umkm.get("owner_name") or "Warga Pekon Margodadi"
    wa_number = umkm.get("wa_number") or umkm.get("phone") or "-"
    phone = umkm.get("phone") or "-"
    address = umkm.get("address") or "Pekon Margodadi, Kec. Ambarawa, Kab. Pringsewu"
    map_title = umkm.get("map_title") or f"Lokasi {name}"
    map_address = umkm.get("map_address") or address
    legal_cert = umkm.get("legal_certification") or "NIB / Surat Keterangan Usaha Desa"
    prod_cap = umkm.get("production_capacity") or "Skala Mikro & Rumah Tangga"
    group_name = umkm.get("group_name") or "Paguyuban Usaha Desa"
    group_loc = umkm.get("group_location") or "Pekon Margodadi"
    desc = umkm.get("description") or f"Usaha {name} bergerak di bidang {category_name}."
    history = umkm.get("history") or "Unit usaha ekonomi masyarakat yang beroperasi di Pekon Margodadi."

    product_lines = []
    if products:
        for p in products:
            p_name = p.get("name", "Produk")
            p_price = p.get("price") or "Hubungi Penjual"
            p_desc = f" - {p.get('description')}" if p.get("description") else ""
            product_lines.append(f"- **{p_name}** (Harga: {p_price}){p_desc}")
    else:
        product_lines.append(f"- {sub_title}")

    prod_str = "\n".join(product_lines)

    return f"""# Direktori Potensi UMKM Pekon Margodadi: {name}
- Nomor Registrasi Pekon: {reg_number}
- Slogan / Fokus Usaha: {sub_title}
- Kategori Usaha: {category_name}
- Nama Pemilik / Pengelola: {owner_name}
- Kontak WhatsApp Resmi: {wa_number}
- Nomor Telepon: {phone}
- Alamat & Lokasi Produksi: {address}
- Penanda Titik Lokasi Peta: {map_title} ({map_address})
- Legalitas & Sertifikasi Resmi: {legal_cert}
- Kapasitas Produksi: {prod_cap}
- Kelompok Binaan / Paguyuban: {group_name} ({group_loc})

## Deskripsi & Profil Usaha:
{desc}

## Sejarah & Latar Belakang Usaha:
{history}

## Daftar Produk & Layanan Usaha:
{prod_str}

## Panduan Pembelian & Pemesanan:
Masyarakat, wisatawan, atau calon pembeli dapat memesan dan membeli produk dari {name} langsung ke alamat gerai/produksi di {address} atau menghubungi kontak WhatsApp resmi pemilik di {wa_number}. Seluruh produk UMKM ini merupakan bagian dari potensi ekonomi binaan Pemerintahan Pekon Margodadi."""


def index_all_data() -> None:
    print("=" * 60)
    print("🚀 MEMULAI PROSES RAG VECTOR INDEXING DATA ASLI PEKON MARGODADI")
    print("=" * 60)

    # 1. Pastikan tabel schema database vector sudah siap
    init_schema()

    embedder = Embedder()
    model_name = getattr(embedder.engine, "model", "default")
    dim = getattr(embedder.engine, "dimension", 768)
    print(f"✅ AI Embedder initialized: Model={model_name}, Dimension={dim}, Provider={embedder.provider_name}")

    with get_connection() as conn:
        with conn.cursor() as cur:
            # 2. Ambil dokumen dari tabel kb_documents
            cur.execute(
                """
                SELECT document_id, title, domain, source, validator, version, is_active
                FROM kb_documents
                WHERE is_active = true
                ORDER BY document_id ASC
                """
            )
            kb_docs = cur.fetchall()

            print(f"\n📂 Menemukan {len(kb_docs)} Dokumen Knowledge Base Resmi di database...")

            kb_tasks = []
            all_kb_texts = []

            for doc_row in kb_docs:
                doc_id, title, domain, source, validator, version, is_active = doc_row
                
                # Ambil chunks
                cur.execute(
                    """
                    SELECT chunk_index, content
                    FROM kb_chunks
                    WHERE document_id = %s
                    ORDER BY chunk_index ASC
                    """,
                    (doc_id,)
                )
                chunk_rows = cur.fetchall()
                if not chunk_rows:
                    continue

                safe_fname = f"{clean_filename(title)}.txt"
                source_path = source or f"laravel://kb_documents/{doc_id}"
                metadata = {
                    "title": title,
                    "domain": domain,
                    "source": source,
                    "validator": validator,
                    "version": version,
                    "is_active": is_active,
                    "laravel_document_id": doc_id,
                }

                # Buat dokumen di RAG
                rag_doc_id = create_document(
                    safe_fname,
                    "txt",
                    source_path,
                    status="processing",
                    metadata=metadata,
                )

                text_chunks = [
                    TextChunk(
                        file_name=safe_fname,
                        file_type="txt",
                        source_path=source_path,
                        content=c_content,
                        metadata={**metadata, "chunk_index": c_idx},
                    )
                    for c_idx, c_content in chunk_rows
                ]

                start_idx = len(all_kb_texts)
                all_kb_texts.extend([c.content for c in text_chunks])
                kb_tasks.append({
                    "doc_id": doc_id,
                    "title": title,
                    "rag_doc_id": rag_doc_id,
                    "text_chunks": text_chunks,
                    "start_idx": start_idx,
                    "count": len(text_chunks),
                })

            print(f"🧠 Melakukan encode vektor cerdas untuk {len(all_kb_texts)} chunks dokumen resmi pekon...")
            all_kb_embeddings = embedder.encode(all_kb_texts)

            total_kb_chunks = 0
            for task in kb_tasks:
                start = task["start_idx"]
                end = start + task["count"]
                doc_id = task["doc_id"]
                embeddings = all_kb_embeddings[start:end]

                insert_chunks_for_document(task["rag_doc_id"], task["text_chunks"], embeddings)

                # Sinkronkan juga vektor embedding ke tabel kb_chunks di Laravel
                for c_idx, emb in enumerate(embeddings):
                    cur.execute(
                        """
                        UPDATE kb_chunks
                        SET embedding = %s
                        WHERE document_id = %s AND chunk_index = %s
                        """,
                        (json.dumps(emb), doc_id, c_idx)
                    )

                total_kb_chunks += task["count"]
                print(f"  ✅ [KB-{doc_id:02d}] {task['title'][:45]}... ({task['count']} chunks diindeks)")

            conn.commit()

            # 3. Ambil seluruh data 71 UMKM beserta produk
            cur.execute(
                """
                SELECT 
                    u.umkm_id, u.name, u.reg_number, u.sub_title, u.owner_name,
                    u.phone, u.wa_number, u.address, u.description, u.history,
                    u.legal_certification, u.production_capacity, u.group_name,
                    u.group_location, u.map_title, u.map_address, u.is_active,
                    c.name as category_name
                FROM umkms u
                LEFT JOIN service_categories c ON c.category_id = u.category_id
                WHERE u.is_active = true
                ORDER BY u.umkm_id ASC
                """
            )
            umkm_rows = cur.fetchall()
            col_names = [desc[0] for desc in cur.description]
            umkms = [dict(zip(col_names, row)) for row in umkm_rows]

            print(f"\n🏪 Menemukan {len(umkms)} UMKM Aktif Pekon Margodadi untuk diindeks ke AI...")

            umkm_tasks = []
            all_umkm_texts = []

            for idx, umkm in enumerate(umkms, 1):
                umkm_id = umkm["umkm_id"]
                name = umkm["name"]

                # Ambil produk-produk UMKM
                cur.execute(
                    """
                    SELECT name, description, price
                    FROM umkm_products
                    WHERE umkm_id = %s AND is_active = true
                    ORDER BY product_id ASC
                    """,
                    (umkm_id,)
                )
                prod_rows = cur.fetchall()
                products = [
                    {"name": r[0], "description": r[1], "price": r[2]}
                    for r in prod_rows
                ]

                # Buat markdown profil
                md_profile = build_umkm_markdown(umkm, products)

                # Cari atau buat kb_documents untuk katalog UMKM
                cur.execute(
                    "SELECT document_id FROM kb_documents WHERE source = %s",
                    (f"UMKM-{umkm_id}",)
                )
                kb_row = cur.fetchone()
                if kb_row:
                    kb_doc_id = kb_row[0]
                    cur.execute(
                        """
                        UPDATE kb_documents
                        SET title = %s, domain = 'UMKM', is_active = true, updated_at = NOW()
                        WHERE document_id = %s
                        """,
                        (f"Katalog UMKM: {name}", kb_doc_id)
                    )
                else:
                    cur.execute(
                        """
                        INSERT INTO kb_documents (title, domain, source, validator, version, is_active, created_at, updated_at)
                        VALUES (%s, 'UMKM', %s, 'Aparatur Pekon Margodadi', 'v1.0', true, NOW(), NOW())
                        RETURNING document_id
                        """,
                        (f"Katalog UMKM: {name}", f"UMKM-{umkm_id}")
                    )
                    kb_doc_id = cur.fetchone()[0]

                safe_fname = f"UMKM_{umkm_id:03d}_{clean_filename(name)}.txt"
                source_path = f"umkm://{umkm_id}"
                metadata = {
                    "title": f"Katalog UMKM: {name}",
                    "domain": "UMKM",
                    "source": f"UMKM-{umkm_id}",
                    "validator": "Aparatur Pekon Margodadi",
                    "version": "v1.0",
                    "is_active": True,
                    "laravel_document_id": kb_doc_id,
                    "umkm_id": umkm_id,
                }

                rag_doc_id = create_document(
                    safe_fname,
                    "txt",
                    source_path,
                    status="processing",
                    metadata=metadata,
                )

                # Chunking record
                record = SourceRecord(
                    file_name=safe_fname,
                    file_type="txt",
                    source_path=source_path,
                    text=md_profile,
                    metadata={"block": 1},
                )
                raw_chunks = chunk_records([record])
                text_chunks = [
                    TextChunk(
                        file_name=safe_fname,
                        file_type="txt",
                        source_path=source_path,
                        content=c.content,
                        metadata={**metadata, **c.metadata},
                    )
                    for c in raw_chunks
                ]

                start_idx = len(all_umkm_texts)
                all_umkm_texts.extend([c.content for c in text_chunks])
                umkm_tasks.append({
                    "umkm_id": umkm_id,
                    "name": name,
                    "kb_doc_id": kb_doc_id,
                    "rag_doc_id": rag_doc_id,
                    "text_chunks": text_chunks,
                    "start_idx": start_idx,
                    "count": len(text_chunks),
                })

            print(f"🧠 Melakukan batch embedding kolektif untuk {len(all_umkm_texts)} chunks dari 71 UMKM...")
            all_umkm_embeddings = embedder.encode(all_umkm_texts)

            total_umkm_chunks = 0
            for task in umkm_tasks:
                start = task["start_idx"]
                end = start + task["count"]
                umkm_id = task["umkm_id"]
                kb_doc_id = task["kb_doc_id"]
                rag_doc_id = task["rag_doc_id"]
                text_chunks = task["text_chunks"]
                embeddings = all_umkm_embeddings[start:end]

                insert_chunks_for_document(rag_doc_id, text_chunks, embeddings)

                # Sinkronkan ke kb_chunks
                cur.execute("DELETE FROM kb_chunks WHERE document_id = %s", (kb_doc_id,))
                for c_idx, (chunk, emb) in enumerate(zip(text_chunks, embeddings)):
                    cur.execute(
                        """
                        INSERT INTO kb_chunks (document_id, chunk_index, content, embedding, metadata, created_at)
                        VALUES (%s, %s, %s, %s, %s, NOW())
                        """,
                        (kb_doc_id, c_idx, chunk.content, json.dumps(emb), json.dumps(chunk.metadata))
                    )

                # Update relasi di umkms
                cur.execute(
                    """
                    UPDATE umkms
                    SET kb_document_id = %s, rag_document_id = %s, ai_synced_at = NOW()
                    WHERE umkm_id = %s
                    """,
                    (kb_doc_id, rag_doc_id, umkm_id)
                )

                total_umkm_chunks += task["count"]

            conn.commit()

    print("\n" + "=" * 60)
    print("🎉 SINKRONISASI DAN INDEXING RAG VECTOR BERHASIL SELESAI!")
    print(f"  • Total Dokumen KB: {len(kb_docs)} dokumen ({total_kb_chunks} chunks)")
    print(f"  • Total UMKM: {len(umkms)} usaha ({total_umkm_chunks} chunks)")
    print(f"  • Total Keseluruhan Chunks: {total_kb_chunks + total_umkm_chunks}")
    print("=" * 60)

    # 4. Tes Pengujian Verifikasi Retriever
    print("\n🔍 MENJALANKAN UJI VALIDASI RETRIEVAL KNOWLEDGE BASE...")
    retriever = Retriever()

    test_queries = [
        "Siapa Kepala Pekon dan Ketua BHP Margodadi?",
        "Berapa jumlah penduduk Pekon Margodadi?",
        "Apa syarat membuat SKU di Margodadi?",
        "Sebutkan usaha atau UMKM yang ada di Dusun Margorejo!",
    ]

    for q in test_queries:
        print(f"\n❓ Query: '{q}'")
        results = retriever.search(q, top_k=2)
        for i, res in enumerate(results, 1):
            snippet = res.content.replace("\n", " ")[:140]
            print(f"   [{i}] (dist: {res.distance:.4f}) {res.metadata.get('title', res.file_name)}")
            print(f"       -> {snippet}...")


if __name__ == "__main__":
    try:
        index_all_data()
    except Exception as exc:
        print(f"\n❌ Terjadi kesalahan fatal: {exc}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        sys.exit(1)
