# Changelog

## 0.2.0 — 2026-11-04

0.2.0 is an architectural release, not a feature patch. The message pipeline was rebuilt around a single Python bridge with two server-sent-event channels (state and workspace); the agent layer was reduced to Goose over ACP; the record gained tool-call history; and the product became self-contained — bundled Apache/PHP, locally vendored front-end libraries, and an embedded i18n dictionary. The aider engine, the health module and all CDN dependencies were removed.

### Added

- **Stream-based state layer**: `state_stream` and `workspace_stream` SSE endpoints, with a new `sc_state_watch` domain that projects server snapshots into a store, a three-level DOM mirror and single-writer CSS state. Replaces per-module polling loops.
- **Tool-call record**: `msg_response.N.tools.jsonl` is parsed into the database and rendered as a `seg--tool` section, one item per command, each with its own copy button for the raw command.
- **Draft stage for prompts**: `data/messages/draft/{project}/{chat}` with a single multipart upload (13 images), a `preparing → promote` extraction chain, and a new `image_extractor` service running in its own working directory.
- **Bridge service** (`services/bridge`): lifecycle ledger with heartbeat, presence/stream connection registry, and per-message attachment streaming over `/bridge_sse_for_message`.
- **Portable shell**: `execute/HKAI.exe` starts a bundled Apache 2.4.54 + PHP-CGI 8.4.26 (mod_fcgid), configured through `bin/custom/conf/00-paths.conf`; no system PHP or virtual host needed.
- **Offline front end**: jQuery, Bootstrap, Font Awesome, marked, DOMPurify, highlight.js, Mermaid, SweetAlert2, CodeMirror and toastr are vendored under `web/sc_contents/sc_resources/vendor`.
- **Embedded i18n**: the dictionary is rendered into the page by PHP, eliminating the request that previously raced the first paint; 71 hard-coded UI strings were converted to keys.
- **Setup wizard**: independent `web/setup` supporting fresh install and upgrade (staged copy, database import), with an fcgid upload-limit check.
- **Diagnostics**: scoped `sc_console` logging and a process viewer driven by heartbeat files.

### Changed

- **Server lifecycle**: a single entry point (`start_server`); the health stream only observes; a manual stop is recorded in `data/bridge/server_stopped_by_user` so the server is not restarted automatically.
- **Queue semantics**: one running message per chat, with automatic relay to the next queued prompt when the previous one finishes.
- **Worker management**: `goose_daemon` split into manager and worker layers; dispatch now checks the worker heartbeat, so a job is never handed to a worker that is already idling out.
- **Message polling**: `message_poll` is reduced to a single completion fetch; live content arrives over the per-message SSE stream.
- **Git status**: moved from the state stream to the workspace stream.
- **Prompt parsing**: unified through `resolve_project_slug`, `resolve_chat_sid` and `is_valid_chat_sid`.
- **Database**: schema advanced to `dbv-1.1`; upgrading renames the previous database (including `-wal`/`-shm`) to a timestamped backup before a new one is created.
- **Environment detection**: performed in-process, removing the `%SystemDrive%` ghost folder; `.env` remains the only place for machine-specific paths.

### Removed

- **aider engine**: `services/aider_daemon`, `aider_entry`, the litellm hooks, `externals/aider`, and the `aider_pylibs_dir` key.
- **`sc_ai_health`** plugin and the `/health` endpoint.
- **`sc_ai_setup`** plugin — setup now lives in `web/setup`.
- **`server_status`** endpoint and the settings-page startup polling.
- **Front-end CDN dependencies** (jsDelivr, cdnjs) and the `php -S` development server mode.
