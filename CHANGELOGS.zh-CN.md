# 更新日志

## 0.3.3 — 2026-11-11

0.3.3 是功能版：新增十二个模型供应商、助理可直接读 PDF／Word／纯文本附件、界面新增日文／韩文／简体中文，并修正供应商切换、分段重复和 prompt 文字未转义。

### 新增

- 十二个模型供应商和图标：OpenAI、Anthropic、Gemini、Groq、Cohere、Mistral、DeepInfra、Moonshot、Z.ai、GitHub Models、OpenCode、Hermes Agent。
- 供应商模型清单管线：缓存清单会重新抓取并规范化（价格、上下文长度、编码指数、推理标记），显示前按层级排序。
- 附件支持文档：PDF、DOCX、纯文本文件会被识别、存储并抽取文字，助理直接读内容（不再只限图片）。
- 界面新增三种语言：日文、韩文、简体中文。

### 变更

- 切换自定义供应商（Kilo、DeepSeek、Hermes Agent）真正生效：不会再静静报「Unknown provider」而没切到 provider／model。
- 回答串流期间，thinking／result 每一段各自带开始时间、状态和长度——进行中的段和已完成的段一眼分得出。
- 工具活动行会沿用工具名：之后的进度更新不会再显示没名的「in_progress」。
- prompt 文字送出和重载对话时都会转义：打进去的 HTML／JS 只会当文字显示，不会被执行。
- 对外 HTTP 一律经同一个客户端，并自动标示 HKAI/<版本>。

### 移除

- 移除旧 state-watch 模块、旧状态栏 fetch 链和四个重复 cURL 助手；每项都已由单一现行实现取代。
