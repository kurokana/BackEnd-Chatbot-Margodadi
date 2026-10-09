import torch
from sentence_transformers import SentenceTransformer, util

MODEL_PATH = "./models/bge-m3"


def main():
    print("=" * 55)
    print("TEST BGE-M3")
    print("=" * 55)

    # Pilih GPU jika tersedia
    device = "cuda" if torch.cuda.is_available() else "cpu"

    print(f"Device : {device}")

    if torch.cuda.is_available():
        print(f"GPU    : {torch.cuda.get_device_name(0)}")

    print("\n[1/3] Memuat model BGE-M3...")

    model = SentenceTransformer(
        MODEL_PATH,
        device=device,
        local_files_only=True,
    )

    print("BGE-M3 berhasil dimuat.")

    # Contoh query
    query = "Apa syarat membuat surat domisili?"

    # Contoh dokumen
    documents = [
        "Persyaratan pembuatan surat domisili adalah KTP dan Kartu Keluarga.",
        "Pelayanan kantor Pekon dilaksanakan dari hari Senin sampai Jumat.",
        "Pemerintah Pekon mengadakan kegiatan gotong royong setiap bulan.",
    ]

    print("\n[2/3] Membuat embedding...")

    query_embedding = model.encode(
        query,
        normalize_embeddings=True,
        convert_to_tensor=True,
    )

    document_embeddings = model.encode(
        documents,
        normalize_embeddings=True,
        convert_to_tensor=True,
    )

    print("Embedding berhasil dibuat.")

    print("\nDimensi query embedding:")
    print(query_embedding.shape)

    print("\nDimensi document embedding:")
    print(document_embeddings.shape)

    print("\n[3/3] Menghitung cosine similarity...")

    similarities = util.cos_sim(
        query_embedding,
        document_embeddings
    )[0]

    print("\nHASIL SIMILARITY")
    print("=" * 55)

    for i, (document, score) in enumerate(
        zip(documents, similarities),
        start=1
    ):
        print(f"\nDokumen {i}")
        print(f"Teks  : {document}")
        print(f"Score : {score.item():.4f}")


if __name__ == "__main__":
    main()