# Changelog

## 0.3.0 — 2026-11-06

0.3.0 makes the runtime self-contained and the install folder read-only: Python 3.13 and the OCR stack ship inside the product, every writable file (database, settings, provider keys, logs, version marker) lives under one data root, and the whole interface is fully localised (Traditional Chinese and English).

### Added

- One data-root entry point for every writable path: database, settings, provider keys, logs, playground and temporary files all resolve through the same resolver (PHP `sc_data_path`, Python `bootstrap.path_resolve`), so a read-only install can never drift into a second data location.
- `<data>/config/api_keys.json` is the single provider-key store: the settings page writes it and the engine reads it.
- `<data>/config/settings.json` stores interface settings (language, timezone) instead of writing them into `.env`.
- Bundled Python 3.13 runtime (`execute/python313`) and OCR stack (OpenCV, NumPy, Pillow, RapidOCR) - no system Python required.
- Full interface localisation: every user-facing string (PHP views, error messages, JavaScript prompts) is resolved from the language dictionary; Traditional Chinese and English ship with the same key set.
- Setup wizard shows the install location, the version/BUILD stamp and the target file of every install step.
- Startup preflight: every page load checks recomputable values (e.g. the Home project path) and repairs stale ones in place; each check is idempotent and never blocks the page.
- Provider errors are shown in the reply itself: exceptions are mapped to `E_*` codes (`E_AUTH`, `E_RATE_LIMIT`, ...) and a plain-language line tells the user what to fix (no provider name hard-coded).
- `.deploy` folder: deploy manifest (`.includes` / `.excludes` / `.mkdirs`) plus `github\\assets` templates and a written spec, so deployment is reproducible instead of manual.

### Changed

- Channel suffix follows the current hostnames (`.dev.internal` -> `-dev`, `.staging.internal` -> `-staging`; production keeps the base `data`), so PHP and Python resolve the same data root.
- Bridge startup confirmation waits at most 6 seconds instead of 45.
- The Home project path always resolves to `<data_dir>/playground`; stale paths stored in the database are corrected automatically.
- The CORS origin whitelist is rewritten in full on every page load / server start instead of being appended to.
- Provider model updates are written to `<data>/providers/model_store` directly (the override layer is still read first).
- Setup wizard: removed the dead install steps and the two obsolete checks (mod_rewrite, FcgidMaxRequestLen); python/goose/env are now pure check items.
- SSE reconnect: each connection resets the local view baseline and back-fills on the next tick, so reconnects no longer leave the polling state stuck.
- Prompt validation follows the selected model's capability (reasoning effort, context length); project detection no longer rewrites the URL.
- The bridge works inside `<data>/tmp`, so the source tree stays write-free at runtime.
- All background services share one logger implementation (`bootstrap/sc_logger.py`) with rotation and a consistent log location under `<data>/logs`.

### Removed

- Runtime writes into the application folder (read-only installs are supported).
- `.env` writes at runtime, and the `.env.example` / `.env.package` templates - the release ships one ready `.env` with relative paths only.
- The manual Python 3.13 install requirement.
- The `SKIP_MODEL_CHECK` test flag - model existence is always verified.
- Duplicate per-service logger copies and the per-service settings/env readers (single shared implementation now).
