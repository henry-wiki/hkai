#!/usr/bin/env python3
"""
openhands_api_client.py

OpenHands agent-server（B2 原生 API）客戶端（openhands_api_client_class singleton）。

職責單一：講 HTTP／WebSocket——容器生命週期、conversation CRUD、訊息送出、事件串流、權限回應。
零業務判斷（唔識 chat／model／專案語義——全由 entry 傳入）。

設定來源：.env（runtime external_paths.oauth_keys_file 指住嗰份）
    OPENHANDS_AGENT_SERVER_URL      本機 server base（例：http://127.0.0.1:3010）
    OPENHANDS_DOCKER_EXECUTABLE     docker.exe 完整路徑
    OPENHANDS_CONTAINER_NAME        容器名（例：oh-agent-server）
    OPENHANDS_SITES_ROOT            主機 sites 根（Windows 路徑）
    OPENHANDS_CONTAINER_SITES_ROOT  容器內 sites 根（例：/sites）
"""

import json
import subprocess
import time
import urllib.error
import urllib.request
from pathlib import Path

from websockets.sync.client import connect as websocket_connect

from ..runtime import sc_logger


# 容器啟動後等 /health 嘅上限（秒——冷啟 10–20 秒，留鬆）
SERVER_READY_TIMEOUT_SEC = 90

# 容器健康檢查輪詢間隔（秒）
SERVER_READY_POLL_INTERVAL_SEC = 1.0

# 單次 HTTP 請求上限（秒）
HTTP_TIMEOUT_SEC = 30


class openhands_api_client_class:
    """agent-server 客戶端（singleton）。"""

    _instance: "openhands_api_client_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._instance._configured = False
        return cls._instance

    @classmethod
    def get_instance(cls) -> "openhands_api_client_class":
        """取得 singleton。"""
        return cls()

    # ----------------------------------------------------------
    # 設定（.env 讀取——每輪 run_job 重讀，改設定即生效）
    # ----------------------------------------------------------
    def configure(self, env_file_path: str) -> None:
        """由 .env 讀取連線／容器設定（缺必要值＝掟錯，外層記失敗）。"""
        env_values = self._read_env_values(env_file_path)

        self.base_url = env_values.get("OPENHANDS_AGENT_SERVER_URL", "").rstrip("/")
        self.docker_executable = env_values.get("OPENHANDS_DOCKER_EXECUTABLE", "")
        self.container_name = env_values.get("OPENHANDS_CONTAINER_NAME", "")
        self.sites_root = env_values.get("OPENHANDS_SITES_ROOT", "")
        self.container_sites_root = env_values.get("OPENHANDS_CONTAINER_SITES_ROOT", "").rstrip("/")
        self._configured = True

    def _read_env_values(self, env_file_path: str) -> dict:
        """讀 .env（KEY=VALUE；缺檔／壞行＝跳過）。"""
        env_values = {}

        # 判定：路徑空 → 回空表（configure 之後檢查必填會報）
        if env_file_path == "":
            return env_values

        try:
            env_lines = Path(env_file_path).read_text(encoding="utf-8", errors="replace").splitlines()
        except OSError:
            return env_values

        # 逐行解析 KEY=VALUE（# 開頭＝註解）
        for env_line in env_lines:
            stripped_line = env_line.strip()

            # 判定：空行／註解 → 跳過
            if stripped_line == "" or stripped_line.startswith("#"):
                continue

            key_name, separator, key_value = stripped_line.partition("=")

            # 判定：冇等號 → 跳過壞行
            if separator == "":
                continue

            env_values[key_name.strip()] = key_value.strip().strip('"').strip("'")

        return env_values

    def _require_configured(self) -> None:
        """檢查必填設定（缺＝掟錯帶清楚鍵名）。"""
        # 判定：未 configure → 掟錯
        if self._configured is False:
            raise RuntimeError("openhands api client not configured")

        required_values = {
            "OPENHANDS_AGENT_SERVER_URL": self.base_url,
            "OPENHANDS_DOCKER_EXECUTABLE": self.docker_executable,
            "OPENHANDS_CONTAINER_NAME": self.container_name,
            "OPENHANDS_SITES_ROOT": self.sites_root,
            "OPENHANDS_CONTAINER_SITES_ROOT": self.container_sites_root,
        }

        # 逐項檢查空值
        for key_name, key_value in required_values.items():
            # 判定：空 → 掟錯
            if str(key_value or "") == "":
                raise RuntimeError("missing .env value: " + key_name)

    # ----------------------------------------------------------
    # 容器生命週期
    # ----------------------------------------------------------
    def ensure_container_running(self) -> None:
        """確保容器行緊（未行＝docker start；再等 /health）。"""
        self._require_configured()

        # 判定：容器已 running → 只等 health（可能剛起）
        if self._is_container_running():
            self._wait_server_ready()
            return

        sc_logger.log("openhands: starting container " + self.container_name)
        start_result = subprocess.run(
            [self.docker_executable, "start", self.container_name],
            capture_output=True,
            text=True,
            timeout=120,
        )

        # 判定：docker start 失敗（容器唔存在等）→ 掟錯帶 stderr
        if start_result.returncode != 0:
            raise RuntimeError("docker start failed: " + (start_result.stderr or "").strip()[:300])

        self._wait_server_ready()

    def _is_container_running(self) -> bool:
        """容器 running？（docker inspect——缺容器＝False）。"""
        inspect_result = subprocess.run(
            [self.docker_executable, "inspect", "-f", "{{.State.Running}}", self.container_name],
            capture_output=True,
            text=True,
            timeout=60,
        )

        # 判定：docker inspect 失敗（容器唔存在）→ False
        if inspect_result.returncode != 0:
            return False

        return inspect_result.stdout.strip().lower() == "true"

    def _wait_server_ready(self) -> None:
        """等 /health 回 200（上限 SERVER_READY_TIMEOUT_SEC）。"""
        deadline = time.time() + SERVER_READY_TIMEOUT_SEC

        # 輪詢 health 直到通或逾時
        while time.time() < deadline:
            # 判定：health 通 → 完成
            if self._health_ok():
                return
            time.sleep(SERVER_READY_POLL_INTERVAL_SEC)

        raise RuntimeError("agent-server health timeout (" + self.base_url + "/health)")

    def _health_ok(self) -> bool:
        """/health 回 200？"""
        try:
            with urllib.request.urlopen(self.base_url + "/health", timeout=5) as response:
                return response.status == 200
        except Exception:  # noqa: BLE001
            return False

    # ----------------------------------------------------------
    # HTTP（REST）
    # ----------------------------------------------------------
    def _request_json(self, method: str, path: str, payload: dict | None = None) -> tuple[int, dict]:
        """發 JSON 請求 → (status, body)；HTTP 4xx/5xx 亦回 body（呼叫端判定）。"""
        request_url = self.base_url + path
        request_data = None

        # 判定：有 payload → 編 JSON bytes
        if payload is not None:
            request_data = json.dumps(payload, ensure_ascii=False).encode("utf-8")

        request = urllib.request.Request(request_url, data=request_data, method=method)
        request.add_header("Content-Type", "application/json")

        try:
            with urllib.request.urlopen(request, timeout=HTTP_TIMEOUT_SEC) as response:
                raw_body = response.read().decode("utf-8", errors="replace")
                return response.status, self._parse_json_or_empty(raw_body)
        except urllib.error.HTTPError as http_error:
            raw_body = http_error.read().decode("utf-8", errors="replace")
            return http_error.code, self._parse_json_or_empty(raw_body)

    def _parse_json_or_empty(self, raw_body: str) -> dict:
        """JSON 解析（壞／空＝{}）。"""
        try:
            parsed_value = json.loads(raw_body)
        except Exception:  # noqa: BLE001
            return {}

        # 判定：非 dict → 包成 {"data": ...}
        if not isinstance(parsed_value, dict):
            return {"data": parsed_value}

        return parsed_value

    # ----------------------------------------------------------
    # conversation 生命週期
    # ----------------------------------------------------------
    def get_conversation(self, conversation_id: str) -> dict | None:
        """取 conversation（404＝None）。"""
        status_code, body = self._request_json("GET", "/api/conversations/" + conversation_id)

        # 判定：404 → 唔存在
        if status_code == 404:
            return None

        # 判定：非 200 → 掟錯（帶 status 同 detail）
        if status_code != 200:
            raise RuntimeError("get conversation failed: http " + str(status_code) + " " + str(body.get("detail", ""))[:200])

        return body

    def create_conversation(self, working_dir: str, llm_config: dict, tool_names: list, confirmation_policy: dict, security_analyzer: dict | None = None) -> dict:
        """建立 conversation（回傳 server 回應 dict）。"""
        payload = {
            "workspace":      {"kind": "LocalWorkspace", "working_dir": working_dir},
            "agent":          {"kind": "Agent", "llm": llm_config, "tools": [{"name": tool_name} for tool_name in tool_names]},
            "confirmation_policy": confirmation_policy,
            "autotitle":      False,
        }

        # 判定：有 analyzer（smart 模式）→ 帶上（ConfirmRisky 必須有 analyzer 才生效）
        if security_analyzer is not None:
            payload["security_analyzer"] = security_analyzer
        status_code, body = self._request_json("POST", "/api/conversations", payload)

        # 判定：非 201 → 掟錯（帶 detail——例如 tool 名錯）
        if status_code != 201:
            raise RuntimeError("create conversation failed: http " + str(status_code) + " " + str(body.get("detail") or body.get("exception") or "")[:300])

        return body

    def send_message(self, conversation_id: str, text: str) -> None:
        """送使用者訊息並開始執行（run=True）。"""
        payload = {"content": [{"type": "text", "text": text}], "run": True}
        status_code, body = self._request_json("POST", "/api/conversations/" + conversation_id + "/events", payload)

        # 判定：失敗 → 掟錯
        if status_code not in (200, 201):
            raise RuntimeError("send message failed: http " + str(status_code) + " " + str(body.get("detail", ""))[:200])

    def interrupt(self, conversation_id: str) -> None:
        """中斷執行（取消用——失敗只記 log）。"""
        status_code, body = self._request_json("POST", "/api/conversations/" + conversation_id + "/interrupt", {})

        # 判定：失敗 → 記 log（收尾照走）
        if status_code not in (200, 202):
            sc_logger.log("openhands: interrupt failed http " + str(status_code) + " " + str(body.get("detail", ""))[:120])

    def respond_confirmation(self, conversation_id: str, accept: bool, reason: str = "") -> None:
        """回應權限請求（accept=True 放行；False 拒絕）。"""
        payload: dict[str, object] = {"accept": bool(accept)}

        # 判定：有拒絕理由 → 帶上（server 顯示用）
        if accept is False and reason != "":
            payload["reason"] = reason

        status_code, body = self._request_json(
            "POST",
            "/api/conversations/" + conversation_id + "/events/respond_to_confirmation",
            payload,
        )

        # 判定：失敗 → 掟錯（權限卡已出，收唔到回應屬嚴重）
        if status_code not in (200, 201):
            raise RuntimeError("respond confirmation failed: http " + str(status_code) + " " + str(body.get("detail", ""))[:200])

    def set_security_analyzer(self, conversation_id: str, security_analyzer: dict | None) -> None:
        """設定／清除 conversation security analyzer（smart 模式用）。"""
        status_code, body = self._request_json(
            "POST",
            "/api/conversations/" + conversation_id + "/security_analyzer",
            {"security_analyzer": security_analyzer},
        )

        # 判定：失敗 → 掟錯
        if status_code not in (200, 201):
            raise RuntimeError("set security analyzer failed: http " + str(status_code) + " " + str(body.get("detail", ""))[:200])

    def set_confirmation_policy(self, conversation_id: str, policy: dict) -> None:
        """更新既有 conversation 嘅 confirmation policy（chat_mode 中途切換用）。"""
        status_code, body = self._request_json(
            "POST",
            "/api/conversations/" + conversation_id + "/confirmation_policy",
            {"policy": policy},
        )

        # 判定：失敗 → 掟錯（policy 錯＝模式語義會錯，唔可以靜默）
        if status_code not in (200, 201):
            raise RuntimeError("set confirmation policy failed: http " + str(status_code) + " " + str(body.get("detail", ""))[:200])

    def get_usage_metrics(self, conversation_id: str) -> dict:
        """取 stats（tokens／cost；缺＝空 dict）。"""
        conversation_data = self.get_conversation(conversation_id)

        # 判定：取唔到 → 空
        if conversation_data is None:
            return {}

        return conversation_data.get("stats", {}) or {}

    # ----------------------------------------------------------
    # WebSocket 事件串流
    # ----------------------------------------------------------
    def stream_events(self, conversation_id: str, event_handler, is_cancelled, deadline_ts: float, on_open=None, skip_until_prompt_text: str = "") -> str:
        """
        連 /sockets/events/{id} 收事件（阻塞）。
        回傳結束原因："finished"／"cancelled"／"timeout"／"closed"；
        event_handler(payload) 回 "stop" 即結束；on_open（可選）喺連線成功後、收事件前呼叫。
        """
        websocket_url = self.base_url.replace("http://", "ws://").replace("https://", "wss://")
        websocket_url += "/sockets/events/" + conversation_id + "?resend_mode=all"

        # 連線（open_timeout——server 未 ready 即掟錯）
        try:
            websocket = websocket_connect(websocket_url, open_timeout=15, max_size=None)
        except Exception as connect_error:  # noqa: BLE001
            raise RuntimeError("websocket connect failed: " + str(connect_error)[:200])

        try:
            # 判定：有 on_open → 連線後即呼叫（送訊息等）
            if on_open is not None:
                on_open()

            # 重播過濾狀態（resend_mode=all 會重播舊事件——跳過界線之前嘅）
            replay_skipping = skip_until_prompt_text != ""

            # 收事件直到結束（每 tick 1 秒——順便查取消／逾時）
            while True:
                # 判定：使用者取消 → 回 cancelled（entry 負責 interrupt）
                if is_cancelled():
                    return "cancelled"

                # 判定：逾時 → 回 timeout
                if time.time() > deadline_ts:
                    return "timeout"

                try:
                    raw_message = websocket.recv(timeout=1.0)
                except TimeoutError:
                    continue
                except Exception:  # noqa: BLE001
                    return "closed"

                # 解析事件（壞 JSON＝跳過）
                try:
                    event_payload = json.loads(raw_message)
                except Exception:  # noqa: BLE001
                    continue

                # 判定：重播中 → 跳到「今次送出嘅使用者訊息」為止（之後全部係新事件）
                if replay_skipping:
                    if self._is_matching_user_message(event_payload, skip_until_prompt_text):
                        replay_skipping = False
                    continue

                handler_result = event_handler(event_payload)

                # 判定：handler 叫停 → 完成
                if handler_result == "stop":
                    return "finished"
        finally:
            try:
                websocket.close()
            except Exception:  # noqa: BLE001
                pass

    def _is_matching_user_message(self, event_payload: dict, prompt_text: str) -> bool:
        """係唔係今次送出嘅使用者訊息？（MessageEvent／source=user／文字相符）"""
        # 判定：非訊息事件 → 唔係
        if str(event_payload.get("kind", "")) != "MessageEvent":
            return False

        # 判定：非使用者訊息 → 唔係
        if str(event_payload.get("source", "")) != "user":
            return False

        llm_message = event_payload.get("llm_message", {}) or {}
        content_parts = llm_message.get("content", []) or []
        message_text = ""

        # 逐段抽文字
        for content_part in content_parts:
            # 判定：文字段 → 併入
            if isinstance(content_part, dict) and content_part.get("type") == "text":
                message_text += str(content_part.get("text", ""))

        return message_text.strip() == prompt_text.strip()

    # ----------------------------------------------------------
    # 路徑換算（主機 → 容器）
    # ----------------------------------------------------------
    def to_container_path(self, host_project_path: str) -> str:
        """主機專案路徑 → 容器路徑（/sites/<相對>）；超出 sites 根＝掟錯。"""
        self._require_configured()

        host_root = Path(self.sites_root).resolve()
        project_root = Path(host_project_path).resolve()

        try:
            relative_project = project_root.relative_to(host_root)
        except ValueError:
            raise RuntimeError("project path outside OPENHANDS_SITES_ROOT: " + host_project_path)

        return self.container_sites_root + "/" + relative_project.as_posix()
