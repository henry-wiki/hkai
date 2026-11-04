# Privacy Policy — HKAI

Last updated: 2026-11-04

HKAI is a local desktop application. It runs on your own computer and stores your data on your own disk. This policy explains what stays local, what leaves your computer, and what choices you have.

## 1. What stays on your computer

The following are stored only on your machine, inside the application folder you chose:

- Conversations, messages, attachments and history (SQLite database and message files under `data/`)
- Project settings, working folders and logs (`data/`)
- Provider API keys and machine-specific settings (`.env`, `data/runtime.json`)

We do not operate a server that receives this data, and we have no access to it.

## 2. What leaves your computer

Only what you explicitly send to a model provider:

- The prompt text you type
- Files and images you attach
- Project context that the agent reads in order to answer (for example source files it opens)

These are transmitted over HTTPS directly to the model provider you configured (for example OpenRouter, Kilo or DeepSeek). That provider processes the data under its own privacy policy and terms. HKAI does not proxy, copy or retain that traffic.

If you open the built-in benchmark pages, the application fetches public data from SWE-bench, LMArena, Artificial Analysis and LLM Stats.

## 3. No telemetry

HKAI contains no analytics, no crash reporting, no usage statistics, no advertising identifiers and no update pings. Front-end libraries are served locally; no CDN requests are made.

## 4. API keys

Provider keys are stored as plain text in the `.env` file inside the application folder. They are used only to authenticate requests to the provider you selected. Protect the folder with your operating system's file permissions, and do not share the folder with the `.env` file included.

## 5. Network exposure

The bundled web server listens on `127.0.0.1` only, so the interface is not reachable from other devices on your network.

## 6. Agent scope and approvals

The agent may read files inside the project folder you selected and run commands on your computer. Every file modification requires your explicit approval first. You are responsible for which folders you open and which commands you approve.

## 7. Retention and deletion

Data is kept on your disk until you delete it:

- Archived message folders older than one year are removed automatically, including their images
- Daily logs are kept for 30 days
- To remove everything, close the application and delete its folder. The only leftovers are the WebView2 cache and shell logs in `%LOCALAPPDATA%\hkai.local.shell`

## 8. Children

HKAI is a developer and productivity tool and is not directed at children.

## 9. Changes

If this policy changes, the updated version will be published in this repository with a new "Last updated" date.

## 10. Contact

Questions or requests: open an issue at https://github.com/henry-wiki/hkai/issues
