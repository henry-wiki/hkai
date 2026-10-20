#!/usr/bin/env python3
"""
session_sync.py

goose session 同步（發問前檢查——goose_entry.run_job 呼叫）。

機制（checkpoints 快照——每對話一檔）：
    檔：<storage>/localdb/checkpoints/<project_slug>/<chat_sid>.json
    形：{"curr": {"stats": {"message_count": N, "max_message_id": M}, "checksum": md5}, "prev": {...}}

每次發問前（問題發去 goose 之前）：
    1. 讀快照——比對「今次問題之前」現場統計（messages 表 count／max message_id——唔數答案行）。
    2. 唔等（或檔案唔存在／壞）＝有修改 → 用我方對話記錄全量重建該對話 goose session
       （session 缺＝建立〔鏡像 goose create_session〕；有＝原地重寫〔鏡像 replace_conversation〕）。
    3. 寫新快照（curr→prev 移位；curr＝含今次問題嘅統計）。

對齊口徑（防「自己嗰輪」干擾——send 時問題行已入庫、答案後至）：
    比對＝message_id < 今次；快照＝message_id <= 今次——答案行唔入統計，兩邊自然對齊。

換 model 對齊（sync_model_switch_before_prompt——獨立於 checkpoints 開關，一律先執行）：
    goose 續話死跟 session 記錄嗰個 model——發問前比對「session 記錄（provider_name／model_config_json.model_name）」
    同「今次 msg_conf 揀嘅（model_name 剝 provider 前綴）」：唔同（或未記錄）＝更新 session 行＋全量重建訊息。

開關：checkpoints 根資料夾（localdb/checkpoints）唔存在＝功能未開——整支跳過（原路徑直去 goose）。
異常：一律盲捕記 log（fail-open——唔阻斷訊息；有修改但同步失敗＝唔前進快照，下輪再試）。
"""

import hashlib
import json
import os
import shutil
import sqlite3
import subprocess
import sys
import uuid
from datetime import datetime, timezone
from pathlib import Path

from ..context import *
from ..runtime import sc_logger


class session_sync_class:
    """
    session 同步（singleton——整個程序只有一個實例）。

    職責單一：發問前令 goose session 對齊我哋嘅真相——model 對齊（換咗＝更新記錄＋重建）＋快照比對（有變即全量重建）；不含 goose 執行。
    """

    _instance: "session_sync_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "session_sync_class":
        """取得 session_sync_class 單例。"""
        return cls()

    def sync_before_prompt(self) -> None:
        """發問前同步（run_job 呼叫——盲捕：任何異常唔阻斷訊息）。"""
        try:
            self._compare_and_sync()
        except Exception as exc:  # noqa: BLE001
            # 例外（刻意盲捕——同步失敗唔阻斷訊息；有修改嘅快照未前進，下輪自動再試）
            sc_logger.log("session_sync error: " + str(exc))

    def sync_model_switch_before_prompt(self) -> None:
        """發問前對齊 session model（run_job 呼叫——盲捕：任何異常唔阻斷訊息）。"""
        try:
            self._align_session_model()
        except Exception as exc:  # noqa: BLE001
            # 例外（刻意盲捕——對齊失敗唔阻斷訊息；下輪發問自動再試）
            sc_logger.log("session_sync model-switch error: " + str(exc))

    # ----------------------------------------------------------
    # 主流程
    # ----------------------------------------------------------

    def _compare_and_sync(self) -> None:
        """主流程：資料夾開關 → chat_sid → 讀快照 → 對比 → （有變）重建 → 寫快照。"""
        # 語境（msg_conf——run_job 已載入）
        chat_id = str(msg_main().get_chat_id()).strip()
        message_id = int(msg_main().get_message_id())
        project_path = str(msg_main().get_msg_conf_data().get("project_path", ""))

        checkpoints_base_dir = self._resolve_checkpoints_base_dir()

        # 判定：checkpoints 根唔存在＝功能未開 → 跳過（唔建立、唔讀寫——原路徑）
        if checkpoints_base_dir.is_dir() is False:
            return

        chat_sid = self._query_chat_sid(chat_id)

        # 判定：chat_sid 查唔到（對話唔存在）→ 記 log 收工
        if chat_sid == "":
            sc_logger.log("session_sync: chat not found (chat_id=" + chat_id + ")")
            return

        checkpoint_file = checkpoints_base_dir / Path(project_path).name / (chat_sid + ".json")

        # 現場統計（唔含今次問題＝對比用；含今次問題＝寫快照用）
        live_stats_before = self._query_live_stats(chat_id, message_id, False)
        live_stats_upto = self._query_live_stats(chat_id, message_id, True)

        # 讀快照（缺失／損壞／checksum 唔對＝None）
        stored_curr_stats = self._read_checkpoint(checkpoint_file)

        # 判定：快照唔存在（首次／新對話）→ 必有修改
        if stored_curr_stats is None:
            is_modified = True
        # 判定：快照在 → 對比現場統計（唔等＝有修改）
        else:
            is_modified = (stored_curr_stats != live_stats_before)

        # 判定：有修改 → 全量重建（用我方對話——今次問題以外）
        if is_modified is True:
            sc_logger.log("session_sync: change detected (chat_id=" + chat_id + ") - rebuilding session")
            self._rebuild_session_from_conversation(chat_id, message_id, project_path)

        # 寫新快照（curr→prev 移位；curr＝含今次問題嘅現場統計）
        self._write_checkpoint(checkpoint_file, live_stats_upto, stored_curr_stats)

    # ----------------------------------------------------------
    # model 對齊
    # ----------------------------------------------------------

    def _align_session_model(self) -> None:
        """對齊 session model（session 記錄嘅 provider／model ≠ 今次揀＝更新嗰行＋全量重建訊息）。"""
        # 語境（msg_conf——run_job 已載入）
        chat_id = str(msg_main().get_chat_id()).strip()
        message_id = int(msg_main().get_message_id())
        project_path = str(msg_main().get_msg_conf_data().get("project_path", ""))
        model_name = str(msg_main().get_msg_conf_data().get("model_name", ""))

        # 解析（provider 前綴／model 名——同 goose_entry._build_goose_env 同口徑）
        provider_prefix, separator, model_part = model_name.partition("/")

        # 判定：解唔到 model 名（唔支持嘅寫法）→ 唔郁（外層已有失敗路徑）
        if separator == "" or model_part == "":
            return

        sessions_db_file = self._resolve_sessions_db_file(project_path)

        # 判定：session DB 唔存在（此專案從未行過 goose）→ 唔關事（首次建立用今次環境）
        if sessions_db_file.is_file() is False:
            return

        session_name = "hkai-chat-" + chat_id

        connection = sqlite3.connect(str(sessions_db_file), timeout=30)
        try:
            session_row = connection.execute(
                "SELECT id, provider_name, model_config_json FROM sessions WHERE name = ? ORDER BY created_at DESC LIMIT 1",
                (session_name,),
            ).fetchone()
        finally:
            connection.close()

        # 判定：冇 session（首次建立交返 goose 自己）→ 唔郁
        if session_row is None:
            return

        session_provider = str(session_row[1] or "")
        session_model = self._read_session_model_name(str(session_row[2] or ""))

        # 判定：provider／model 同今次揀嘅一樣 → 唔使動（照續話）
        if session_provider == provider_prefix and session_model == model_part:
            return

        # 更新 session 行（provider／model——goose 續話讀嗰行嘅值）
        updated_model_config_text = self._merge_session_model_name(str(session_row[2] or ""), model_part)

        connection = sqlite3.connect(str(sessions_db_file), timeout=30)
        try:
            connection.isolation_level = None
            connection.execute("BEGIN IMMEDIATE")
            connection.execute(
                "UPDATE sessions SET provider_name = ?, model_config_json = ? WHERE id = ?",
                (provider_prefix, updated_model_config_text, str(session_row[0])),
            )
            connection.execute("COMMIT")
        finally:
            # 未 COMMIT 就離開（例外）→ 連線關閉時 SQLite 自動回滾（DB 維持原狀）
            connection.close()

        sc_logger.log(
            "session_sync: session model aligned (name=" + session_name
            + ", " + session_provider + "/" + session_model
            + " -> " + provider_prefix + "/" + model_part + ")"
        )

        # 全量重建訊息（我哋 DB 係真相——出去嗰個請求＝新 model ＋ 重組後歷史）
        self._rebuild_session_from_conversation(chat_id, message_id, project_path)

    def _read_session_model_name(self, session_model_config_text: str) -> str:
        """讀 session 記錄嘅 model 名（model_config_json——壞值／缺＝空字串）。"""
        try:
            session_model_config = json.loads(session_model_config_text)
        except Exception:  # noqa: BLE001
            return ""

        # 判定：非 dict（壞形）→ 當冇
        if not isinstance(session_model_config, dict):
            return ""

        return str(session_model_config.get("model_name", "") or "")

    def _merge_session_model_name(self, session_model_config_text: str, model_name: str) -> str:
        """合併新 model 名入 session 設定（保留其餘欄位——壞值／缺＝重新起一個淨設定）。"""
        try:
            session_model_config = json.loads(session_model_config_text)
        except Exception:  # noqa: BLE001
            session_model_config = {}

        # 判定：非 dict（壞形）→ 重新起一個
        if not isinstance(session_model_config, dict):
            session_model_config = {}

        session_model_config["model_name"] = model_name
        return json.dumps(session_model_config, separators=(",", ":"))

    # ----------------------------------------------------------
    # 快照（讀／寫／checksum）
    # ----------------------------------------------------------

    def _read_checkpoint(self, checkpoint_file: Path) -> dict | None:
        """讀快照 curr stats（缺失／損壞／checksum 唔對 → None——一律當有修改）。"""
        try:
            payload = json.loads(checkpoint_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return None

        # 判定：非 dict → 當冇
        if not isinstance(payload, dict):
            return None

        curr_entry = payload.get("curr")

        # 判定：curr 非 dict → 當冇
        if not isinstance(curr_entry, dict):
            return None

        stats = curr_entry.get("stats")

        # 判定：stats 唔齊（count／max 缺）→ 當冇
        if not isinstance(stats, dict) or "message_count" not in stats or "max_message_id" not in stats:
            return None

        normalized_stats = {
            "message_count": int(stats["message_count"]),
            "max_message_id": int(stats["max_message_id"]),
        }

        # 判定：checksum 唔對（檔壞／被人手改）→ 當冇
        if str(curr_entry.get("checksum", "")) != self._stats_checksum(normalized_stats):
            return None

        return normalized_stats

    def _write_checkpoint(self, checkpoint_file: Path, current_stats: dict, previous_curr_stats: dict | None) -> None:
        """寫快照（curr→prev 移位；curr＝現行——原子寫，讀者唔會見到半截檔）。"""
        curr_entry = {
            "stats": current_stats,
            "checksum": self._stats_checksum(current_stats),
        }

        # 判定：有舊 curr → 移入 prev；冇（首次）→ prev 留 null
        if previous_curr_stats is None:
            prev_entry = None
        else:
            prev_entry = {
                "stats": previous_curr_stats,
                "checksum": self._stats_checksum(previous_curr_stats),
            }

        payload = {
            "curr": curr_entry,
            "prev": prev_entry,
        }

        checkpoint_file.parent.mkdir(parents=True, exist_ok=True)
        temporary_file = checkpoint_file.with_suffix(checkpoint_file.suffix + ".tmp")
        temporary_file.write_text(json.dumps(payload, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
        os.replace(temporary_file, checkpoint_file)

    def _stats_checksum(self, stats: dict) -> str:
        """統計 checksum（md5——"count:max"；檔案完整性自檢用）。"""
        canonical_text = str(stats["message_count"]) + ":" + str(stats["max_message_id"])
        return hashlib.md5(canonical_text.encode("utf-8")).hexdigest()

    # ----------------------------------------------------------
    # 查詢（我方 DB）
    # ----------------------------------------------------------

    def _query_chat_sid(self, chat_id: str) -> str:
        """查我方 DB：該對話嘅 chat_sid（缺＝空字串）。"""
        our_db_file = self._resolve_our_db_file()

        # 判定：我哋 DB 唔存在 → 空字串
        if our_db_file.is_file() is False:
            return ""

        connection = sqlite3.connect("file:" + our_db_file.as_posix() + "?mode=ro", uri=True)
        try:
            chat_row = connection.execute(
                "SELECT chat_sid FROM chats WHERE chat_id = ?",
                (int(chat_id),),
            ).fetchone()
        finally:
            connection.close()

        # 判定：冇列 → 空字串
        if chat_row is None:
            return ""

        return str(chat_row[0] or "")

    def _query_live_stats(self, chat_id: str, message_id: int, include_current: bool) -> dict:
        """現場統計（messages 表 count／max；include_current＝今次問題計唔計——對齊口徑見檔頭）。"""
        # 邊界（唔含＝嚴格細過；含＝細過或等於）
        if include_current is True:
            message_bound_sql = "message_id <= ?"
        else:
            message_bound_sql = "message_id < ?"

        our_db_file = self._resolve_our_db_file()
        connection = sqlite3.connect("file:" + our_db_file.as_posix() + "?mode=ro", uri=True)
        try:
            stats_row = connection.execute(
                "SELECT COUNT(*), COALESCE(MAX(message_id), 0) FROM messages WHERE chat_id = ? AND " + message_bound_sql,
                (int(chat_id), int(message_id)),
            ).fetchone()
        finally:
            connection.close()

        return {
            "message_count": int(stats_row[0]),
            "max_message_id": int(stats_row[1]),
        }

    # ----------------------------------------------------------
    # 全量重建（goose sessions.db）
    # ----------------------------------------------------------

    def _rebuild_session_from_conversation(self, chat_id: str, message_id: int, project_path: str) -> None:
        """全量重建該對話 session（用我方對話——今次問題以外；缺＝建立、有＝原地重寫）。"""
        self._ensure_sessions_db(project_path)

        sessions_db_file = self._resolve_sessions_db_file(project_path)
        session_name = "hkai-chat-" + chat_id

        connection = sqlite3.connect(str(sessions_db_file), timeout=30)
        try:
            connection.isolation_level = None
            connection.execute("BEGIN IMMEDIATE")

            session_id = self._resolve_or_create_session_id(connection, session_name, project_path)
            conversation_rows = self._build_conversation_rows(chat_id, message_id, session_id)

            connection.execute("DELETE FROM messages WHERE session_id = ?", (session_id,))
            connection.executemany(
                "INSERT INTO messages (message_id, session_id, role, content_json, created_timestamp, metadata_json) VALUES (?, ?, ?, ?, ?, ?)",
                conversation_rows,
            )

            connection.execute("COMMIT")
        finally:
            # 未 COMMIT 就離開（例外）→ 連線關閉時 SQLite 自動回滾（DB 維持原狀）
            connection.close()

        sc_logger.log("session_sync: session rebuilt (name=" + session_name + ", rows=" + str(len(conversation_rows)) + ")")

    def _ensure_sessions_db(self, project_path: str) -> None:
        """sessions.db 唔存在（此專案從未行過 goose）→ 官方 CLI 開一次（goose 自建 DB＋schema）。"""
        sessions_db_file = self._resolve_sessions_db_file(project_path)

        # 判定：DB 已存在 → 直返
        if sessions_db_file.is_file() is True:
            return

        goose_executable = self._resolve_goose_executable()

        # 判定：執行檔解析唔到 → 拋返上層（盲捕記 log——快照唔前進，下輪再試）
        if goose_executable == "":
            raise RuntimeError("goose executable not found (session db init)")

        subprocess_kwargs = {}

        # 判定：Windows → 無視窗
        if sys.platform == "win32":
            subprocess_kwargs["creationflags"] = subprocess.CREATE_NO_WINDOW

        goose_env = dict(os.environ)
        goose_env["GOOSE_PATH_ROOT"] = str(self._resolve_goose_root(project_path))

        # session list（banner 建立 DB＋schema——輸出唔理）
        subprocess.run(
            [goose_executable, "session", "list"],
            env=goose_env,
            stdin=subprocess.DEVNULL,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=60,
            **subprocess_kwargs,
        )

    def _resolve_or_create_session_id(self, connection: sqlite3.Connection, session_name: str, project_path: str) -> str:
        """取該對話 session id（同名取最新）；唔存在＝建立（鏡像 goose create_session）。"""
        session_row = connection.execute(
            "SELECT id FROM sessions WHERE name = ? ORDER BY created_at DESC LIMIT 1",
            (session_name,),
        ).fetchone()

        # 判定：已存在 → 用之
        if session_row is not None:
            return str(session_row[0])

        # 建立（id＝今日連號——同 goose 同一生成式；user_set_name＝TRUE＝我哋話事個名）
        session_today = datetime.now(timezone.utc).strftime("%Y%m%d")

        connection.execute(
            "INSERT INTO sessions (id, name, user_set_name, session_type, working_dir, extension_data, goose_mode) "
            "VALUES ("
            "? || '_' || CAST(COALESCE(("
            "SELECT MAX(CAST(SUBSTR(id, 10) AS INTEGER)) FROM sessions WHERE id LIKE ? || '_%'"
            "), 0) + 1 AS TEXT), "
            "?, TRUE, 'acp', ?, '{}', ?"
            ")",
            (session_today, session_today, session_name, project_path, self._resolve_session_goose_mode()),
        )

        created_session_row = connection.execute(
            "SELECT id FROM sessions WHERE name = ? ORDER BY created_at DESC LIMIT 1",
            (session_name,),
        ).fetchone()

        return str(created_session_row[0])

    def _resolve_session_goose_mode(self) -> str:
        """建立 session 時嘅模式值（chat_mode 查表——空／未對映＝auto）。"""
        msg_conf_data = msg_main().get_msg_conf_data()
        chat_mode = str(msg_conf_data.get("chat_mode", ""))
        chat_mode_map = daemon().get_daemon_conf_data().get("daemon_chat_mode_map", {})
        goose_mode = str(chat_mode_map.get(chat_mode, ""))

        # 判定：未對映 → 引擎默認
        if goose_mode == "":
            return "auto"

        return goose_mode

    def _read_exchange_source(self, chat_id: str, message_id: int) -> tuple:
        """讀我方對話交流（messages＋response_result 兩查＋答案併文）——goose 重建來源。"""
        our_db_file = self._resolve_our_db_file()
        connection = sqlite3.connect("file:" + our_db_file.as_posix() + "?mode=ro", uri=True)
        try:
            # 篩選：只取真交流——failed 訊息（無法送出卡＝錯誤源）唔入 session
            our_message_rows = connection.execute(
                "SELECT message_id, message_content, message_created_at FROM messages WHERE chat_id = ? AND message_id < ? AND message_status != 'failed' ORDER BY message_id ASC",
                (int(chat_id), int(message_id)),
            ).fetchall()

            answer_rows = connection.execute(
                "SELECT message_id, response_index, response_content FROM message_response WHERE chat_id = ? AND message_id < ? AND response_type = 'response_result' ORDER BY message_id ASC, response_index ASC",
                (int(chat_id), int(message_id)),
            ).fetchall()
        finally:
            connection.close()

        # 答案併文（同一訊息多段——按 response_index 次序貼埋）
        answer_text_by_message = {}
        for answer_row in answer_rows:
            answer_message_id = int(answer_row[0])
            answer_text_by_message[answer_message_id] = answer_text_by_message.get(answer_message_id, "") + str(answer_row[2] or "")

        return our_message_rows, answer_text_by_message

    def _build_conversation_rows(self, chat_id: str, message_id: int, session_id: str) -> list:
        """我方對話 → goose 訊息列（user／assistant 文字對——今次問題以外，按序）。"""
        our_message_rows, answer_text_by_message = self._read_exchange_source(chat_id, message_id)

        conversation_rows = []
        last_created_timestamp = 0

        for our_message_row in our_message_rows:
            our_message_id = int(our_message_row[0])
            our_message_text = str(our_message_row[1] or "")
            our_created_at = str(our_message_row[2] or "")

            created_timestamp = self._to_epoch_seconds(our_created_at)

            # 判定：時間戳唔前進（同秒多列）→ +1（保嚴格遞增排序）
            if created_timestamp <= last_created_timestamp:
                created_timestamp = last_created_timestamp + 1
            last_created_timestamp = created_timestamp

            conversation_rows.append(self._build_message_row("user", our_message_text, created_timestamp, session_id))

            answer_text = answer_text_by_message.get(our_message_id, "")

            # 判定：有答案文字 → 補 assistant 列（無＝略——例如取消咗嗰啲）
            if answer_text != "":
                last_created_timestamp = last_created_timestamp + 1
                conversation_rows.append(self._build_message_row("assistant", answer_text, last_created_timestamp, session_id))

        return conversation_rows

    def _build_message_row(self, role: str, text: str, created_timestamp: int, session_id: str) -> tuple:
        """組一列 goose 訊息（content_json／metadata 格式同 goose 自家一致）。"""
        content_json = json.dumps(
            [{"type": "text", "text": text}],
            ensure_ascii=False,
            separators=(",", ":"),
        )
        metadata_json = json.dumps(
            {"userVisible": True, "agentVisible": True},
            separators=(",", ":"),
        )
        return ("msg_" + str(uuid.uuid4()), session_id, role, content_json, created_timestamp, metadata_json)

    def _to_epoch_seconds(self, created_at_text: str) -> int:
        """我哋格式時間字串（Y-m-d H:M:S——本地）→ epoch 秒（goose created_timestamp 口徑）。"""
        try:
            return int(datetime.strptime(created_at_text, "%Y-%m-%d %H:%M:%S").timestamp())
        except Exception:  # noqa: BLE001
            # 解析唔到 → 用當前時間（排序由 monotonic 遞增補回）
            return int(datetime.now().timestamp())

    # ----------------------------------------------------------
    # 路徑
    # ----------------------------------------------------------

    def _resolve_storage_dir(self) -> str:
        """storage 根（runtime.json storage_dir——同 goose 資料根同源）。"""
        return str(daemon().get_daemon_conf_data().get("storage_dir", ""))

    def _resolve_our_db_file(self) -> Path:
        """我方 SQLite（hkai_chat_data.sqlite；剝 Windows 裝置前綴——sqlite URI 前置）。"""
        storage_path_text = str(self._resolve_storage_dir()).replace("\\", "/")
        # 判定：帶 Windows 裝置前綴（"//./"）→ 剝走（sqlite URI 解析會誤當 authority）
        if storage_path_text.startswith("//./"):
            storage_path_text = storage_path_text[4:]
        # 判定：帶 Windows 長路徑前綴（"//?/"）→ 剝走（同上）
        if storage_path_text.startswith("//?/"):
            storage_path_text = storage_path_text[4:]
        return Path(storage_path_text) / "localdb" / "hkai_chat_data.sqlite"

    def _resolve_checkpoints_base_dir(self) -> Path:
        """checkpoints 根（功能開關：存在＝開、刪走＝關——原路徑直去；專案子資料夾由寫檔自動建立）。"""
        return Path(self._resolve_storage_dir()) / "localdb" / "checkpoints"

    def _resolve_goose_root(self, project_path: str) -> Path:
        """goose 資料根（鏡像 goose_entry　每專案一資料夾）。"""
        project_slug = Path(project_path).name
        return Path(self._resolve_storage_dir()) / "localdb" / "goose" / project_slug

    def _resolve_sessions_db_file(self, project_path: str) -> Path:
        """該專案 goose session DB（官方佈局：data/sessions/sessions.db）。"""
        return self._resolve_goose_root(project_path) / "data" / "sessions" / "sessions.db"

    def _resolve_goose_executable(self) -> str:
        """goose 執行檔（runtime.json external_paths 優先；缺＝PATH 搜尋；再缺＝空字串）。"""
        goose_executable = str(daemon().get_daemon_conf_data().get("goose_executable", "") or "")

        # 判定：配置有值 → 用之
        if goose_executable != "":
            return goose_executable

        # 退回 PATH 搜尋
        return shutil.which("goose") or ""
