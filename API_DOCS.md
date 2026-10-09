# API Docs - Skripsi RAG Gateway

Base URL lokal:

```text
http://127.0.0.1:8000
```

Struktur folder project sekarang:

```text
D:\Projek Skripsi\
  ChatBot\    backend RAG + API
  FrontEnd\   aplikasi frontend
```

Jalankan backend dari folder utama:

```powershell
cd "D:\Projek Skripsi"
.\start-chatbot.ps1
```

Atau langsung dari folder backend:

```powershell
cd "D:\Projek Skripsi\ChatBot"
.\start-api.ps1
```

Swagger/OpenAPI:

```text
http://127.0.0.1:8000/docs
```

Root endpoint:

```text
http://127.0.0.1:8000/
```

Frontend berada di folder/proyek terpisah `FrontEnd` dan cukup memanggil API backend di `http://127.0.0.1:8000`.

## Konfigurasi LLM API

Backend memakai embedding lokal `bge-m3`, tetapi jawaban `/chat` dikirim ke LLM API. Konfigurasi ada di `.env`:

```env
LLM_API_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
LLM_API_CHAT_PATH=/chat/completions
LLM_API_MODEL=gemini-flash-lite-latest
LLM_API_FALLBACK_MODELS=gemini-3.1-flash-lite,gemini-3.8-flash,gemini-3.7-flash,gemini-3.6-flash
LLM_API_KEY=isi_api_key_di_sini
LLM_API_TIMEOUT=60
LLM_API_TEMPERATURE=0.2
```

Client LLM saat ini memakai format Chat Completions yang kompatibel dengan OpenAI. Jika provider API berbeda, sesuaikan `LLM_API_BASE_URL`, `LLM_API_CHAT_PATH`, dan `LLM_API_MODEL`.

## Status Dokumen

Setiap dokumen memiliki status indexing:

```text
processing  File sedang diproses menjadi chunk dan embedding.
indexed     File berhasil diproses dan sudah bisa dipakai untuk search/chat.
failed      File gagal diproses. Lihat error_message.
```

Untuk versi saat ini, upload masih diproses secara sinkron. Artinya request upload akan menunggu sampai indexing selesai atau gagal.

## GET /health

Cek apakah API hidup.

Response:

```json
{
  "status": "ok"
}
```

## GET /documents

Mengambil daftar dokumen yang sudah masuk ke database.

Response:

```json
[
  {
    "id": 1,
    "file_name": "EKONOMI KREATIF.xlsx",
    "file_type": "xlsx",
    "source_path": "D:\\Projek Skripsi\\ChatBot\\Data\\EKONOMI KREATIF.xlsx",
    "status": "indexed",
    "error_message": null,
    "indexed_at": "2026-10-07T16:02:47.664306+00:00",
    "created_at": "2026-10-07T16:02:47.664306+00:00",
    "chunk_count": 10
  }
]
```

## POST /documents/upload

Upload file baru dan langsung index ke pgvector.

Format yang didukung:

```text
.pdf
.xlsx
.xls
.docx
```

Request:

```text
Content-Type: multipart/form-data
Field: file
```

Contoh JavaScript:

```javascript
const formData = new FormData();
formData.append("file", fileInput.files[0]);

const response = await fetch("http://127.0.0.1:8000/documents/upload", {
  method: "POST",
  body: formData,
});

const result = await response.json();
```

Response berhasil:

```json
{
  "document_id": 3,
  "file_name": "data_umkm.docx",
  "file_type": "docx",
  "chunks": 12,
  "status": "indexed"
}
```

Response gagal format:

```json
{
  "detail": "Format file belum didukung. Gunakan PDF, Excel, atau DOCX."
}
```

## DELETE /documents/{document_id}

Menghapus dokumen dan semua chunk/embedding miliknya.

Contoh:

```text
DELETE /documents/3
```

Response:

```json
{
  "document_id": 3,
  "deleted": true
}
```

## POST /search

Mencari chunk paling relevan tanpa memanggil LLM. Endpoint ini berguna untuk debugging retrieval.

Request:

```json
{
  "question": "Siapa pemilik usaha Aa Craft?",
  "top_k": 3
}
```

Response:

```json
{
  "question": "Siapa pemilik usaha Aa Craft?",
  "sources": [
    {
      "file_name": "EKONOMI KREATIF.xlsx",
      "file_type": "xlsx",
      "metadata": {
        "row": 5,
        "sheet": "Sheet1",
        "chunk_in_record": 0
      },
      "distance": 0.39946821931193843,
      "content": "Data sheet Sheet1, baris 5. No: 1. Nama Usaha: Aa Craft..."
    }
  ]
}
```

## POST /chat

Mencari konteks dari pgvector, lalu meminta LLM API menjawab berdasarkan konteks tersebut.

Request:

```json
{
  "question": "Siapa pemilik usaha Aa Craft?",
  "top_k": 1
}
```

Response:

```json
{
  "question": "Siapa pemilik usaha Aa Craft?",
  "answer": "Nama pemilik usaha Aa Craft adalah Tuni Suharti.",
  "sources": [
    {
      "file_name": "EKONOMI KREATIF.xlsx",
      "file_type": "xlsx",
      "metadata": {
        "row": 5,
        "sheet": "Sheet1",
        "chunk_in_record": 0
      },
      "distance": 0.39946821931193843,
      "content": "Data sheet Sheet1, baris 5. No: 1. Nama Usaha: Aa Craft..."
    }
  ]
}
```

Catatan: endpoint `/chat` membutuhkan koneksi internet ke provider LLM API yang diatur di `.env`.
