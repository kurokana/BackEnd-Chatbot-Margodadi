from src.llm import APILLM


def main():
    llm = APILLM()
    answer = llm.generate(
        "Jawab singkat dalam Bahasa Indonesia: apakah koneksi LLM API berhasil?",
        max_new_tokens=80,
    )
    print(answer)


if __name__ == "__main__":
    main()
