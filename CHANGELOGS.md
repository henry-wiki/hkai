# Changelog

## 0.2.0 — 2026-11-04

### Added

- `execute/HKAI.exe` serves the console itself: bundled Apache 2.4.54 and PHP-CGI 8.4.26 (mod_fcgid); `php -S` is no longer used.
- Apache and PHP live in versioned folders (`bin/apache-2.4.54`, `bin/php-8.4.26`) and are wired through `bin/custom/conf/00-paths.conf`, written by `HKAI.exe` on every start.
- Every front-end library is vendored under `web/sc_contents/sc_resources/vendor`; the console works offline.
- The i18n dictionary is embedded in the page by PHP, so labels no longer flash as raw keys.
- Upgrading over an existing site renames the old database (together with `-wal`/`-shm`) to a timestamped backup before a new one is created.

### Changed

- Prompt queue runs one message per chat and relays to the next automatically.
- The polling indicator on project/chat cards is reconciled from the server snapshot on connect, on reconnect, and after a list re-render.
- Worker dispatch skips workers that are already idling out (heartbeat check), so a queued message is never left behind.

### Removed

- The aider engine and all related modules, settings and `.env` keys.
- Front-end CDN dependencies (jsDelivr and cdnjs).

## 0.1.0 — 2026-10-09

First public release.

### Added

- Web console: chats, projects and settings.
- Background agent: a resident daemon plus one work unit per message.
- Multi-provider model catalogue and key management (keys stored only in local `.env`).
- Streamed replies split into thinking and response segments, each with its own timer.
- Project-centric chats and working directories (separate history per project).
- Swappable engines (goose/aider) behind one front-end contract.
- Permission card before file changes: the agent asks before touching files.
- Diagnostics and status: process viewer, health state and run log.
- Local data: SQLite for chats and projects; message records kept as files.

### Notes

- Bring your own model provider key; no key is bundled.
- The agent engine executable is optional: the interface is usable before it is installed.
