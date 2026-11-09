# Changelog

## 0.3.1 — 2026-11-10

0.3.1 is a maintenance release. It fixes the reliability problems reported after 0.3.0, so long conversations and back-to-back questions behave the way the screen says they do.

### Changed

- Very long conversations no longer fail every time with "Bad request (400)": the app realigns the conversation history automatically and the chat can continue.
- Sending another question while the previous reply is still streaming no longer freezes that earlier reply halfway.
- Replies no longer come back blank (an empty shell with no text).
- The "working" indicator no longer stays on after a reply has finished, and it lights up on time when work starts.
- The waiting list refreshes by itself, and the queue moves on as soon as a reply finishes.
- A question that arrives exactly while the background engine is shutting down is no longer ignored.
- The bubble shown the moment you press Send now looks like a real message bubble (border, icons, model line) instead of plain left-aligned text.
- Provider problems are explained in plain words (for example "API key rejected") instead of a bare "Request Error".
- Settings and playground no longer show old folder paths that no longer exist.
- Opening the app no longer waits about 45 seconds before the first page appears.
- Occasional random "session expired" rejections on ordinary clicks are fixed.
- A rare first-load error caused by creating the same built-in project twice is fixed.
