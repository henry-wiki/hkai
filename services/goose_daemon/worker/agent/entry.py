#!/usr/bin/env python3
"""
entry.py

goose 執行入口（entry_class singleton——get_instance() 取得）。

用法（暖 worker）：
    preload()      開機預載一次（runner:103）
    execute_job()  每張工單一次（job_executor:120）

流程：語境恢復 → session 同步（checkpoints——有變／首次＝全量重建）→ goose 環境組裝（provider／model 由 model_name 解析）→ ACP 執行 → 收尾：
      goose acp 子程序（stdio 協定——agent/acp_client.py 驅動；權限請求經檔案契約互動）；
      thinking／text 分流寫 response 段檔＋FSM 事件；raw 行原樣寫 job_stdout。

輸出契約：response 段檔／job_stdout／FSM 事件（agent＝goose 執行檔；無內嵌 litellm／aider）。

session 續話：每對話一條 goose session（名＝hkai-chat-{chat_id}）——
ACP 每工單以同名 session 續話。

資料根：每專案一個 GOOSE_PATH_ROOT——<storage>/localdb/goose/<project_slug>；
session／config／state 全歸該專案資料夾（專案間零干涉）。
config 之下＝.goosehints（系統指示）＋custom_providers/<前綴>.json（自訂 provider 檔）——皆於每問 spawn 前刷新。

goose provider 支援（官方清單 2026-10；★＝本檔白名單已開，☆＝goose 原生支援但本檔未開）
  原生
    ★ openrouter            OPENROUTER_API_KEY（HOST／PARAMETERS 可選）
    ★ anthropic             ANTHROPIC_API_KEY（HOST 可選）
    ★ openai                OPENAI_API_KEY（HOST 可指向 OpenAI-compatible／vLLM）
    ★ google                GOOGLE_API_KEY
    ★ xai                   XAI_API_KEY
    ★ litellm               LITELLM_HOST（API_KEY 可選）
    ★ ollama                OLLAMA_HOST（本機，無 key）
    ☆ vertex                GCP_PROJECT_ID ＋ GCP_LOCATION
    ☆ azure-openai          AZURE_OPENAI_ENDPOINT ＋ AZURE_OPENAI_DEPLOYMENT_NAME
    ☆ azure-foundry         AZURE_FOUNDRY_ENDPOINT（API_KEY 可選）
    ☆ bedrock               AWS_ACCESS_KEY_ID ＋ AWS_SECRET_ACCESS_KEY ＋ AWS_REGION
    ☆ sagemaker             SAGEMAKER_ENDPOINT_NAME
    ☆ databricks            DATABRICKS_HOST ＋ DATABRICKS_TOKEN
    ☆ snowflake             SNOWFLAKE_HOST ＋ SNOWFLAKE_TOKEN
    ☆ groq                  GROQ_API_KEY
    ☆ mistral               MISTRAL_API_KEY
    ☆ cerebras              CEREBRAS_API_KEY
    ☆ perplexity            PERPLEXITY_API_KEY
    ☆ meta                  META_MODEL_API_KEY
    ☆ venice                VENICE_API_KEY
    ☆ gondola               GONDOLA_API_KEY
  本機
    ☆ ollama-cloud          OLLAMA_CLOUD_API_KEY
    ☆ lmstudio              無（localhost:1234）
    ☆ docker                OPENAI_HOST ＋ OPENAI_BASE_PATH
    ☆ ramalama              OLLAMA_HOST
    ☆ atomic                無（localhost:1337）
  免 key（OAuth／CLI）
    ☆ copilot               GitHub device flow
    ☆ chatgpt-codex         瀏覽器 OAuth（ChatGPT Plus／Pro）
    ☆ cursor-agent          cursor-agent CLI 已登入
   ACP
    ☆ claude-acp            npm i -g @agentclientprotocol/claude-agent-acp
    ☆ codex-acp             npm i -g @agentclientprotocol/codex-acp
  OpenAI-compatible 聚合商（一個 key 打多個上游）
    ☆ aimlapi               AIMLAPI_API_KEY
    ☆ avian                 AVIAN_API_KEY
    ☆ empiriolabs           EMPIRIOLABS_API_KEY
    ☆ friendli              FRIENDLI_API_KEY
    ☆ futurmix              FUTURMIX_API_KEY
    ☆ iflytek-spark         SPARK_API_PASSWORD
    ☆ astron                ASTRON_API_KEY
    ☆ ionet                 IONET_API_KEY
    ☆ nearai                NEARAI_API_KEY
    ☆ novita                NOVITA_API_KEY
    ☆ ovhcloud              OVHCLOUD_API_KEY
    ☆ routstr               ROUTSTR_API_KEY
    ☆ saygm                 SAYGM_API_KEY
    ☆ saladcloud            SALAD_CLOUD_API_KEY
    ☆ scaleway              SCW_SECRET_KEY
    ☆ tanzu                 TANZU_AI_API_KEY ＋ TANZU_AI_ENDPOINT
    ☆ tetrate               TETRATE_API_KEY
    ☆ trustedrouter         TRUSTEDROUTER_API_KEY
  自訂（本檔已開）
    ★ custom_providers/<前綴>.json（任何 OpenAI-compatible endpoint；api_key_env 由 CUSTOM_PROVIDER_TABLE 決定）

支援程度（goose 側限制）
  - 必須 tool calling；o1-mini／o1-preview 唔支援
  - reasoning（reasoning_content）：DeepSeek-R1、Kimi、Gemini、Claude（開 thinking）
  - Claude prompt caching：anthropic／bedrock／databricks／openrouter／litellm 自動
  - 加原生 provider＝paths.PROVIDER_TABLE 加一行（preflight 自動跟隨）
"""


from ...context import (
    conversation_sync,
    message_store,
)
from ...runtime import sc_logger
from . import session, paths, exceptions, prepare, preflight


class entry_class:
    """
    執行入口（singleton——整個程序只有一個實例）。

    職責單一：建立 goose 執行環境（provider 環境＋資料根＋執行檔＋session）並執行單次查詢；不含重試／狀態機。
    """

    _instance: "entry_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "entry_class":
        """取得 entry_class 單例。"""
        return cls()

    def preload(self) -> None:
        """
        預載檢查（工人開機一次）：goose 係外部執行檔——先驗執行檔真係可用，
        唔可用即拋錯（工人唔應該起；否則每張工單註定失敗）。
        """
        # 判定：執行檔缺席 → 拋錯（工人即刻死，daemon 收屍）
        if paths.resolve_executable() == "":
            sc_logger.log("preload: goose executable not found (config/PATH)")
            raise RuntimeError("goose executable not found (config/PATH)")

        # 真跑 --version 驗證（損壞／權限／架構錯——平價探測，一次）
        executable_ok, executable_detail = paths.verify_executable()

        # 判定：驗證失敗 → 拋錯（帶原因，daemon log 可見）
        if executable_ok is False:
            sc_logger.log("preload: goose executable invalid: " + executable_detail)
            raise RuntimeError("goose executable invalid: " + executable_detail)

        sc_logger.log("preload: goose executable = " + paths.resolve_executable() + " (version " + executable_detail + ")")

    def execute_job(self) -> bool:
        """
        單次查詢（暖 worker 每張工單一次）：組環境 → spawn goose → 串流翻譯 → 收尾。

        回 True＝正常完成；False＝出錯（呼叫端據此解僱工人）。
        msg_conf 已由 job_executor.initialize() 載入——呢度唔再重讀。
        """
        # 語境（msg_conf——job_executor.initialize() 已載入）
        chat_id      = message_store().get_chat_id()
        project_path = message_store().get_project_path()
        model_name   = message_store().get_model_name()

        # 預檢（model 白名單／API key／執行檔／專案目錄——純檢查，唔喺執行 try 內）
        try:
            goose_env = preflight.check(model_name, project_path)
        # 預檢失敗 → 記原因收工（工人失敗退出）
        except preflight.PreflightError as preflight_error:
            return exceptions.record_preflight_failure(preflight_error)

        # 對話歷史同步（失敗＝工單失敗——工人隨後退出）
        try:
            conversation_sync().reconcile_history()
        except Exception as exc:  # noqa: BLE001
            return exceptions.record_job_failure(exc, chat_id)

        # 以下動作（spawn／串流）失敗＝工單失敗
        try:
            # goose 子程序環境（路徑／系統指示／上下文／模式——全在此備妥）
            prepare.prepare_goose_env(goose_env)

            # ACP：準備連線參數 → 組回呼 → 執行（唯一路徑——權限請求可互動；其餘契約同源）
            session_request = session.prepare_session(goose_env)
            callbacks = session.build_callbacks(session_request)
            session.run_session(session_request, callbacks)
            return True

        # 盲捕例外（刻意——單次 run 失敗要完整收尾，不能外洩）
        except Exception as exc:  # noqa: BLE001
            return exceptions.record_job_failure(exc, chat_id)
