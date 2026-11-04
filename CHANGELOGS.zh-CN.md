# 更新记录

## 0.2.0 — 2026-11-04

0.2.0 是架构级改版，不是功能补丁。消息管线重建成「单一 Python bridge＋两条 SSE 通道（state／workspace）」；代理层收窄为 Goose（ACP）；消息记录新增工具调用历史；产品变为自足——内置 Apache／PHP、前端 library 全部本地、i18n 字典嵌入页面。aider 引擎、健康模块与所有 CDN 依赖已移除。

### 新增

- **流式状态层**：新增 `state_stream` 与 `workspace_stream` 两条 SSE，配合新的 `sc_state_watch` 域——把服务器快照投影到 store、三层 DOM 与单一写入点的 CSS 状态；取代各模块自行轮询的做法。
- **工具调用记录**：`msg_response.N.tools.jsonl` 解析入 DB，前端以 `seg--tool` 段呈现，每条命令一格，每格可复制原始命令。
- **Prompt draft 层**：`data/messages/draft/{project}/{chat}`，支持单次 multipart 上传（13 张图）、`preparing → promote` 抽取链，以及独立工作目录的 `image_extractor` 服务。
- **Bridge 服务**（`services/bridge`）：生命周期台帐＋心跳、presence／流连接登记、每消息附件流（`/bridge_sse_for_message`）。
- **可携外壳**：`execute/HKAI.exe` 启动内置 Apache 2.4.54＋PHP-CGI 8.4.26（mod_fcgid），由 `bin/custom/conf/00-paths.conf` 接驳；无需系统 PHP 或 vhost。
- **离线前端**：jQuery、Bootstrap、Font Awesome、marked、DOMPurify、highlight.js、Mermaid、SweetAlert2、CodeMirror、toastr 全部收入 `web/sc_contents/sc_resources/vendor`。
- **i18n 嵌入**：字典由 PHP 出页时写入页面，消除开页时显示原始 key 的竞态；71 处硬编码字符串已转为 key。
- **安装向导**：独立 `web/setup`，支持全新安装与升级（暂存副本、数据库导入），并检查 fcgid 上传上限。
- **诊断**：`sc_console` 分 scope 记录＋由心跳文件驱动的进程查看。

### 变更

- **服务器生命周期**：单一入口（`start_server`）；health 流只监视不启动；手动停止写入 `data/bridge/server_stopped_by_user`，避免自动重启。
- **队列语义**：每个对话同时只跑一条，完成后自动接续下一条排队消息。
- **Worker 管理**：`goose_daemon` 拆成 manager／worker 两层；派工前先查心跳，不再把工单交给正在空闲退出的 worker。
- **消息轮询**：`message_poll` 收窄为完成时一次抓取；实时内容由每条消息的 SSE 流提供。
- **Git 状态**：由 state 流搬到 workspace 流。
- **Prompt 解析**：统一经 `resolve_project_slug`、`resolve_chat_sid`、`is_valid_chat_sid`。
- **数据库**：schema 升到 `dbv-1.1`；升级时旧库（连 `-wal`／`-shm`）改名为时间戳备份后才建立新库。
- **环境侦测**：改为 in-process，移除 `%SystemDrive%` 幽灵文件夹；机器路径只留 `.env`。

### 移除

- **aider 引擎**：`services/aider_daemon`、`aider_entry`、litellm hooks、`externals/aider`，以及 `.env` 的 `aider_pylibs_dir`。
- **`sc_ai_health`** 插件与 `/health` 端点。
- **`sc_ai_setup`** 插件——安装改由 `web/setup` 负责。
- **`server_status`** 端点与设置页的启动轮询。
- **前端 CDN 依赖**（jsDelivr、cdnjs）与 `php -S` 开发服务器模式。
