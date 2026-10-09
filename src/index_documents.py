from src.config import ROOT_DIR
from src.indexer import DocumentIndexer


def main() -> None:
    data_dir = ROOT_DIR / "Data"
    indexer = DocumentIndexer()
    record_count, chunk_count, document_count = indexer.index_data_dir(data_dir)

    print(f"Records terbaca: {record_count}")
    print(f"Chunks dibuat  : {chunk_count}")
    print(f"Dokumen        : {document_count}")
    print("\nIndexing selesai.")


if __name__ == "__main__":
    main()
