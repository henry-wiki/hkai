# Changelog

## 0.3.0 — 2026-11-06

0.3.0 makes the runtime self-contained and the install folder read-only: Python 3.13 and the OCR stack ship inside the product, every writable file (database, settings, provider keys, logs, version marker) lives under one data root, and the whole interface is fully localised (Traditional Chinese and English).

### Added

- Bundled Python 3.13 runtime (`execute/python313`) with pip and the service dependencies (FastAPI, Uvicorn, pydantic, psutil, python-dotenv).
- Bundled OCR stack: OpenCV, NumPy, Pillow and RapidOCR, so image text extraction works without a system Python.
- Runtime configuration is written to `<data>/config/httpd-runtime.conf` instead of the application folder.
- Provider API keys are stored in `<data>/config/api_keys.json` — one read/write entry used by both the settings page and the engine, and always shown masked (`**************` plus the last 4 characters) in the interface.
- Full interface localisation: every user-facing string in PHP and JavaScript is resolved from the language dictionary; Traditional Chinese and English ship with the same key set.
- The setup wizard now shows the install location, the version/BUILD stamp and the target file of every install step.

### Changed

- Data root resolution: `HKAI_DATA_DIR` → site `data/` (when an existing database is present) → `%LOCALAPPDATA%\seacorner\hkai\data`.
- Apache logs and PID file now live in `<data>/logs/` instead of `execute/bin/apache-*/logs/`.
- The launcher and runtime checks prefer the bundled Python; `.env` `python_executable` still overrides it.
- Local AppData detection falls back through environment variables and the running account, so FastCGI workers resolve the same path as the launcher.
- The `.env` shipped with the release contains only relative machine paths and is never written by the application; all mutable state moved into the data root (database marker `<data>/localdb/DB.VERSION`, settings `<data>/config/settings.json`, keys `<data>/config/api_keys.json`, logs, playground).
- Every path in the product now resolves through the same data-root entry point, so a read-only install folder cannot silently degrade into a second, divergent data location.

### Removed

- The requirement to install Python 3.13 manually.
- Writes into the application folder at runtime (read-only installs are now supported).
- The `.env.example` template — the release ships a ready `.env`, and the wizard no longer creates or migrates environment files.
