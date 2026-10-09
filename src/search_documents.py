import argparse

from src.retriever import Retriever


def main() -> None:
    parser = argparse.ArgumentParser(description="Cari chunk relevan dari pgvector.")
    parser.add_argument("question", help="Pertanyaan yang ingin dicari konteksnya.")
    parser.add_argument("--top-k", type=int, default=5)
    args = parser.parse_args()

    retriever = Retriever()
    results = retriever.search(args.question, top_k=args.top_k)

    for index, result in enumerate(results, start=1):
        print("=" * 80)
        print(f"Hasil {index}")
        print(f"File     : {result.file_name}")
        print(f"Metadata : {result.metadata}")
        print(f"Distance : {result.distance:.4f}")
        print("Content  :")
        print(result.content)


if __name__ == "__main__":
    main()
