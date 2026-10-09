import httpx

from src.config import (
    LLM_API_BASE_URL,
    LLM_API_CHAT_PATH,
    LLM_API_FALLBACK_MODELS,
    LLM_API_KEY,
    LLM_API_MODEL,
    LLM_API_TEMPERATURE,
    LLM_API_TIMEOUT,
)


class APILLM:
    def __init__(self) -> None:
        if not LLM_API_KEY:
            raise RuntimeError("LLM_API_KEY belum diatur di file .env.")
        if not LLM_API_MODEL:
            raise RuntimeError("LLM_API_MODEL belum diatur di file .env.")

        self.base_url = LLM_API_BASE_URL.rstrip("/")
        self.chat_path = "/" + LLM_API_CHAT_PATH.strip("/")
        self.model = LLM_API_MODEL
        self.fallback_models = LLM_API_FALLBACK_MODELS
        self.timeout = LLM_API_TIMEOUT

    def generate(self, prompt: str, max_new_tokens: int = 256) -> str:
        messages = [
            {
                "role": "system",
                "content": (
                    "Anda adalah asisten informasi desa. Jawab dalam Bahasa Indonesia "
                    "secara jelas, singkat, dan natural dalam kalimat lengkap. "
                    "Jangan menjawab hanya dengan potongan kata atau nama jika bisa "
                    "dibuat menjadi kalimat utuh. Gunakan hanya konteks yang diberikan. "
                    "Jika konteks berasal dari basis pengetahuan Pekon Margodadi, "
                    "perlakukan informasi tersebut sebagai data Pekon Margodadi. "
                    "Jika satuan di konteks berbeda dari pertanyaan, jawab dengan "
                    "satuan yang tersedia dan jelaskan perbedaannya."
                ),
            },
            {"role": "user", "content": prompt},
        ]

        headers = {
            "Authorization": f"Bearer {LLM_API_KEY}",
            "Content-Type": "application/json",
        }

        url = f"{self.base_url}{self.chat_path}"
        models = [self.model, *self.fallback_models]
        errors: list[str] = []

        with httpx.Client(timeout=self.timeout) as client:
            for model in models:
                payload = {
                    "model": model,
                    "messages": messages,
                    "max_tokens": max_new_tokens,
                    "temperature": LLM_API_TEMPERATURE,
                }
                try:
                    response = client.post(url, headers=headers, json=payload)
                except httpx.TimeoutException:
                    if model != models[-1]:
                        errors.append(f"{model}: timeout")
                        continue
                    raise RuntimeError(
                        f"LLM API timeout saat memakai model {model}. "
                        f"Fallback sebelumnya: {', '.join(errors)}."
                    ) from None

                if response.status_code in {429, 503} and model != models[-1]:
                    errors.append(f"{model}: {response.status_code}")
                    continue

                try:
                    response.raise_for_status()
                except httpx.HTTPStatusError as exc:
                    detail = exc.response.text[:500]
                    fallback_note = f" Fallback sebelumnya: {', '.join(errors)}." if errors else ""
                    raise RuntimeError(
                        f"LLM API gagal ({exc.response.status_code}): {detail}{fallback_note}"
                    ) from exc

                data = response.json()
                break

        try:
            answer = data["choices"][0]["message"]["content"]
        except (KeyError, IndexError, TypeError) as exc:
            raise RuntimeError(f"Format response LLM API tidak dikenali: {data}") from exc

        return answer.strip()
