Set-StrictMode -Version Latest

$python = Join-Path $PSScriptRoot ".venv\Scripts\python.exe"

if (-not (Test-Path $python)) {
    Write-Error "Virtual environment tidak ditemukan. Pastikan .venv berada di folder ChatBot."
    exit 1
}

Set-Location $PSScriptRoot
& $python -m uvicorn src.api:app --reload --host 127.0.0.1 --port 8001
