#!/usr/bin/env python3
"""
litellm_providers.py

🧩 可拆模組——自訂 provider 註冊器（復刻舊 chat_daemon/adapter/litellm_providers.py）。

職責（單一——本模組＝gateway 表嘅唯一註冊者）：
    litellm 唔原生支持嘅 gateway（如 kilo）→ 一筆註冊入 litellm：
    litellm 見 '<provider>/<model>' 前綴 → 路由去登記咗嘅轉發 handler → 打 gateway
    （key 由環境變數攞——oauth keys 檔同源，daemon 側已載、子程序繼承）。

安裝（main.py 嘅可拆邊界 try/except 呼叫）：
    setup() 必須在 import aider 前執行——每個用 litellm 的程序各自註冊一次
    （Windows spawn＝新程序、唔繼承 litellm 狀態）。

安全底線：註冊缺失＝openrouter 照行（litellm 原生）；自訂 gateway 嘅模型報 unknown provider。
本檔 import 零重成本——litellm 延遲至 setup() 先載（daemon 側讀 gateway_map 唔使 litellm）。
"""

import os

# gateway 表——新增 provider 加一行
#   鍵 = model 串前綴（app 傳嘅 'provider_id/model_id' 嗰個 provider_id）
#   api_key_env = oauth keys 檔條名（載入嘅環境變數同名）
gateway_map = {
    "kilo": {
        "base_url": "https://api.kilo.ai/api/gateway",
        "api_key_env": "KILO_API_KEY",
    },
}

# 註冊狀態（整程序一次）
_registered = False


def setup() -> None:
    """開機設定（main.py 呼叫）：將 gateway 表註冊入 litellm（必須在 import aider 前——整程序一次）。"""
    global _registered

    # 判定：已註冊 → 唔重複（幂等）
    if _registered:
        return

    _register()

    _registered = True


def _register() -> None:
    """
    將 gateway 表註冊入 litellm。

    流程：
      1. 每個 provider 建一個轉發 handler（OpenAI-compatible）
      2. 覆寫 litellm.custom_provider_map（litellm 靠佢配對 provider → handler）
      3. custom_llm_setup()——補 provider_list／_custom_providers
         （get_llm_provider 先認得嗰個 provider 名——少呢步會報 unknown provider）
    """
    import litellm
    from litellm.utils import custom_llm_setup

    # 轉發 handler 類（litellm.CustomLLM 子類——每個 provider 一實例）
    gateway_handler_class = _build_gateway_handler_class()

    litellm.custom_provider_map = [
        {
            "provider": provider,
            "custom_handler": gateway_handler_class(
                provider,
                gateway_row["base_url"],
                gateway_row["api_key_env"],
            ),
        }
        for provider, gateway_row in gateway_map.items()
    ]

    custom_llm_setup()


def _build_gateway_handler_class():
    """建轉發 handler 類（litellm.CustomLLM 子類——延遲載入 litellm：daemon 側 import 本檔零重成本）。"""
    import litellm

    class gateway_handler_class(litellm.CustomLLM):
        """
        單一 gateway 嘅轉發 handler（OpenAI-compatible）。

        職責單一：剝 model 前綴 → 用 litellm 原生 'openai/' 通道打嗰個 gateway。
        """

        # litellm router 傳入嘅內部參數——唔可以再傳落內層 completion
        _internal_params = (
            "acompletion",
            "api_base",
            "api_key",
            "client",
            "custom_prompt_dict",
            "encoding",
            "litellm_params",
            "logger_fn",
            "logging_obj",
            "model_response",
            "optional_params",
            "print_verbose",
        )

        def __init__(self, provider: str, base_url: str, api_key_env: str):
            super().__init__()
            self._provider = provider
            self._base_url = base_url
            self._api_key_env = api_key_env

        def completion(self, model: str, messages: list, **kwargs):
            """非流式——轉發去 gateway。"""
            return self._forward(model, messages, False, kwargs)

        def streaming(self, model: str, messages: list, **kwargs):
            """
            流式（aider 主路徑）——轉發去 gateway。

            litellm 對自訂 provider 有硬契約：外層包裝器強制將每個 chunk 當
            GenericStreamingChunk（dict：text／is_finished／finish_reason／usage）處理。
            故此內層 chunk 必須逐個轉成該形狀；內層原 chunk 掛喺 original_chunk
            ——外層會原樣複製返 choices（reasoning_content 等欄位先保得住）。
            """
            inner_stream = self._forward(model, messages, True, kwargs)

            # 逐 chunk 轉契約形（text／is_finished／finish_reason／usage）
            for inner_chunk in inner_stream:
                inner_choices = getattr(inner_chunk, "choices", None) or []
                inner_choice = inner_choices[0] if inner_choices else None
                inner_delta = getattr(inner_choice, "delta", None) if inner_choice is not None else None
                finish_reason = getattr(inner_choice, "finish_reason", None) if inner_choice is not None else None

                yield {
                    "text": getattr(inner_delta, "content", None) or "",
                    "is_finished": finish_reason is not None,
                    "finish_reason": finish_reason or "",
                    "usage": None,
                    "index": 0,
                    # 空 dict（非 None）＝通過外層 is_chunk_non_empty 閘（純 reasoning chunk 都要放行）
                    "provider_specific_fields": {},
                    # 內層已轉換嘅 chunk 原樣帶過——外層複製 choices 用（保 reasoning 等欄位）
                    "original_chunk": inner_chunk,
                }

        def _forward(self, model: str, messages: list, stream: bool, kwargs: dict):
            """打 gateway（api_key 由環境變數攞——同 oauth keys 檔同源）。"""
            # 用原版 completion（litellm.main）——唔用頂層 litellm.completion：
            # litellm_hook 會將頂層符號覆蓋成 interceptor；若喺呢度再入嗰層，
            # 內層請求會被當成「摘要」吞流造假回應 → 上層串流契約爛（next() TypeError）
            from litellm.main import completion as litellm_original_completion

            forward_kwargs = {
                key: value
                for key, value in kwargs.items()
                if key not in self._internal_params
            }

            # model 串兩態都食：有 '<provider>/' 前綴 → 剝一個；冇（上游 aider 已剝）→ 原樣用
            prefix = self._provider + "/"
            model_name = model[len(prefix):] if model.startswith(prefix) else model

            return litellm_original_completion(
                model="openai/" + model_name,
                api_base=self._base_url,
                api_key=os.environ.get(self._api_key_env),
                messages=messages,
                stream=stream,
                **forward_kwargs,
            )

    return gateway_handler_class
