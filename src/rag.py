from src.config import RAG_TOP_K
from src.llm import APILLM
from src.retriever import Retriever, SearchResult


def build_prompt(question: str, contexts: list[SearchResult]) -> str:
    context_text = "\n\n".join(
        f"[Sumber {index}]\n"
        f"File: {result.file_name}\n"
        f"Metadata: {result.metadata}\n"
        f"Isi: {result.content}"
        for index, result in enumerate(contexts, start=1)
    )

    return (
        "Anda menjawab untuk basis data Pekon Margodadi.\n"
        "Semua konteks yang diberikan berasal dari dokumen yang sudah diunggah "
        "ke basis pengetahuan Pekon Margodadi, kecuali konteks menyatakan hal lain.\n"
        "Jawab pertanyaan hanya berdasarkan konteks.\n"
        "Gunakan kalimat lengkap. Untuk pertanyaan identitas atau fakta tunggal, "
        "jawab dengan kalimat natural, misalnya: \"Kepala Desa Pekon Margodadi "
        "adalah Pak/Bu ...\" jika nama tersedia di konteks.\n"
        "Untuk jabatan kepala desa/lurah, gunakan sapaan \"Pak\" sebelum nama "
        "kecuali konteks jelas menyebut sapaan lain.\n"
        "Jika pertanyaan menyebut Pekon Margodadi dan konteks relevan berasal "
        "dari dokumen basis pengetahuan ini, sebutkan Pekon Margodadi dalam jawaban.\n"
        "Jika pertanyaan memakai istilah warga/orang tetapi konteks menyediakan "
        "satuan keluarga/rumah tangga, jawab menggunakan satuan yang tersedia dan "
        "jelaskan singkat perbedaan satuannya.\n"
        "Jangan meminta daftar keluarga mentah jika konteks sudah memuat jumlah agregat.\n"
        "Jika informasi benar-benar tidak ada di konteks, jawab: "
        "\"Maaf, informasi tersebut tidak tersedia dalam data.\"\n\n"
        f"Konteks:\n{context_text}\n\n"
        f"Pertanyaan:\n{question}"
    )


class RAGService:
    def __init__(self) -> None:
        self.retriever = Retriever()
        self.llm: APILLM | None = None

    def search(self, question: str, top_k: int = RAG_TOP_K) -> list[SearchResult]:
        return self.retriever.search(question, top_k)

    def chat(self, question: str, top_k: int = RAG_TOP_K) -> tuple[str, list[SearchResult]]:
        contexts = self.search(question, top_k)
        prompt = build_prompt(question, contexts)

        if self.llm is None:
            self.llm = APILLM()

        answer = self.llm.generate(prompt)
        return answer, contexts
