# Changelog

## 0.3.3 — 2026-11-11

0.3.3 adds twelve model providers, lets the assistant read PDF/Word/plain-text attachments, adds Japanese, Korean and Simplified Chinese interface languages, and fixes provider switching, duplicated agent segments and unescaped prompt text.

### Added

- Twelve new model providers with icons: OpenAI, Anthropic, Gemini, Groq, Cohere, Mistral, DeepInfra, Moonshot, Z.ai, GitHub Models, OpenCode and Hermes Agent.
- Provider model pipelines: cached model lists are re-downloaded and normalised (pricing, context length, coding index, reasoning flag) and sorted by tier before display.
- Attachments can be documents: PDF, DOCX and plain-text files are detected, stored and extracted, so the assistant reads their text instead of being limited to images.
- Three new interface languages: Japanese, Korean and Simplified Chinese.

### Changed

- Switching to a custom provider (Kilo, DeepSeek, Hermes Agent) now really applies the provider and model instead of failing quietly with "Unknown provider".
- While an answer is still streaming, every thinking/result segment carries its own start time, status and length, so a segment in progress is no longer indistinguishable from a finished one.
- Tool activity lines keep the tool name for later progress updates instead of showing a nameless "in_progress".
- Prompt text is escaped both when it is sent and when a chat is reloaded: HTML/JS typed into the prompt is displayed as text and never executed.
- Outbound HTTP goes through one client that always identifies itself as HKAI/<version>.

### Removed

- The legacy state-watch modules, the old status-bar fetch chain and four duplicate cURL helpers are gone; each had already been replaced by a single current implementation.
