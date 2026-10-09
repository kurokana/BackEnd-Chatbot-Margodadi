from pathlib import Path
from dotenv import load_dotenv
import os

ROOT_DIR = Path(__file__).resolve().parents[1]
load_dotenv(ROOT_DIR / ".env")

DATABASE_URL = os.getenv("DATABASE_URL", "")
EMBEDDING_MODEL_PATH = ROOT_DIR / os.getenv("EMBEDDING_MODEL_PATH", "./models/bge-m3")
RAG_TOP_K = int(os.getenv("RAG_TOP_K", "3"))
EMBEDDING_DIMENSION = 1024
UPLOAD_DIR = ROOT_DIR / os.getenv("UPLOAD_DIR", "./uploads")

LLM_API_BASE_URL = os.getenv("LLM_API_BASE_URL", "https://api.openai.com/v1")
LLM_API_CHAT_PATH = os.getenv("LLM_API_CHAT_PATH", "/chat/completions")
LLM_API_KEY = os.getenv("LLM_API_KEY", "")
LLM_API_MODEL = os.getenv("LLM_API_MODEL", "gpt-4o-mini")
LLM_API_FALLBACK_MODELS = [
    model.strip()
    for model in os.getenv("LLM_API_FALLBACK_MODELS", "").split(",")
    if model.strip()
]
LLM_API_TIMEOUT = float(os.getenv("LLM_API_TIMEOUT", "60"))
LLM_API_TEMPERATURE = float(os.getenv("LLM_API_TEMPERATURE", "0.2"))
