# 更新記錄

## 0.3.3 — 2026-11-11

0.3.3 係功能版：新增十二個模型供應商、助理可直接讀 PDF／Word／純文字附件、介面新增日文／韓文／簡體中文，並修正供應商切換、分段重複同 prompt 文字未轉義。

### 新增

- 十二個模型供應商連圖示：OpenAI、Anthropic、Gemini、Groq、Cohere、Mistral、DeepInfra、Moonshot、Z.ai、GitHub Models、OpenCode、Hermes Agent。
- 供應商模型清單管線：快取清單會重新抓取同正規化（價格、上下文長度、編碼指數、推理標記），顯示前按層級排序。
- 附件支援文件：PDF、DOCX、純文字檔會被識別、儲存同抽取文字，助理直接讀內容（唔再只限圖片）。
- 介面新增三種語言：日文、韓文、簡體中文。

### 變更

- 切換自訂供應商（Kilo、DeepSeek、Hermes Agent）真正生效：唔會再靜靜報「Unknown provider」而冇切到 provider／model。
- 回答串流期間，thinking／result 每一段各自帶開始時間、狀態同長度——進行中嘅段同已完成嘅段一眼分得出。
- 工具活動行會沿用工具名：之後嘅進度更新唔會再顯示冇名嘅「in_progress」。
- prompt 文字送出同重載對話時都會轉義：打落去嘅 HTML／JS 只會當文字顯示，唔會被執行。
- 對外 HTTP 一律經同一個客戶端，並自動標示 HKAI/<版本>。

### 移除

- 移除舊 state-watch 模組、舊狀態列 fetch 鏈同四個重複 cURL 助手；每項都已由單一現行實作取代。
