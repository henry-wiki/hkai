// jsmods/sc_stream/state/state_stream.js —— 健康狀態 SSE 連線（單例）
// 職責：open(project_slug, chat_sid, view_id)——開 /state_stream/；每個 type 一個 event（open／chat／project／polling／git／conn／close）
// 上游：jsmods/sc_stream/sc_stream.js（open_state_stream——開頁／換對話唯一入口）
// 下游：registry().sc_state_watch（polling）＋ stream/act/*_updater（project／chat／git／conn）
// 註：chat／project 基線（seed）唔再接收——伺服器 view 檔同步後只送有變項目

import { build_stream_polling_snapshot } from "../../../plugins/sc_stream/jsmods/stream/act/build_stream_change_items.js";

// console 過濾 scope（模組級定義一次——呼叫點唔重複寫死）
const CONSOLE_SCOPES = ["polling", "state_stream"];

class _state_stream {
    constructor() {
        // 連線與網址（唯一出口——狀態由 open／close 管理）
        this.event_source = null;
        this.stream_url = "/request/hkai/state_stream/";

        // 事件類型（後端逐個 type 派發——只認呢五個）
        this.stream_types = ["chat", "project", "polling", "message"];

        // 開線令牌（open 期間再 open／close → 舊連線作廢）
        this.stream_token = 0;

        // 已啟動旗標（事件 handler 先檢查——close 後殘留事件唔處理）
        this.enabled = false;

        // 換線狀態（新線待接手——未換前舊線照收事件，零空隙）
        this.connection_seq = 0;
        this.pending_token = 0;
        this.pending_event_source = null;
        this.previous_event_source = null;
    }

    // 開線（URL 由本模組砌——caller 只交三個身份值）
    async open(project_slug, chat_sid, view_id) {
        const thisObject = this;

        // 判定：瀏覽器唔支援 EventSource → 唔開
        if (typeof EventSource === "undefined") {
            return;
        }

        thisObject.connection_seq = thisObject.connection_seq + 1;
        const open_token = thisObject.connection_seq;

        // 換線：舊線留住收事件，等新線第一個事件接手（零空隙）
        thisObject.previous_event_source = thisObject.event_source;
        thisObject.pending_token = open_token;

        thisObject.enabled = true;

        const stream_url = thisObject.stream_url
            + "?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid)
            + "&view_id=" + encodeURIComponent(view_id);

        const event_source = new EventSource(stream_url);

        // open（handshake——冇資料要處理；順便由 store 全量重算 polling 卡標記（重連補償））
        event_source.addEventListener("open", async function () {
            registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "open", { ready_state: event_source.readyState });

            try {
                const chat_instance = await registry().sc_ai_chats().chat();
                const polling_state_observer_instance = await chat_instance.obs().polling_state_observer();

                await polling_state_observer_instance.refresh_polling_cards_from_store();
            } catch (reconcile_error) {
                registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "polling_reconcile_error", { message: String(reconcile_error) });
            }
        });

        // 伺服器 handshake（event: open 帶 base_url）→ 換埠時同步 presence
        event_source.addEventListener("open", async function (event) {
            // 判定：原生 open（無 data）→ 唔關事
            if (!event.data) {
                return;
            }

            const handshake_payload = JSON.parse(event.data);
            const bridge_base_url = handshake_payload.base_url || "";

            // 判定：冇位址 → 唔動作
            if (bridge_base_url === "") {
                return;
            }

            await registry().sc_presence().ensure_open(bridge_base_url);
        });

        // error（連線層錯誤——EventSource 會自行重連；記錄用嚟對時序）
        event_source.addEventListener("error", function () {
            registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "error", { ready_state: event_source.readyState });
        });

        // 逐個 type 綁監聽（chat／project／polling／git／conn）
        for (let type_index = 0; type_index < thisObject.stream_types.length; type_index++) {
            const stream_type = thisObject.stream_types[type_index];

            event_source.addEventListener(stream_type, async function (event) {
                await thisObject.apply_stream_event(stream_type, event, open_token);
            });
        }

        // close（伺服器主動收線——EventSource 會自動重連）
        event_source.addEventListener("close", function () {
        });

        thisObject.pending_event_source = event_source;

        // 安全網：新線 open 後 2 秒仍未見事件 → 照換（靠新線首 tick 全量補）
        window.setTimeout(function () {
            thisObject.activate_pending_source(open_token);
        }, 2000);
    }

    // 新線接手：舊線關閉，之後只認新線
    activate_pending_source(open_token) {
        // 判定：唔係待換線 → 唔郁
        if (this.pending_token !== open_token) {
            return;
        }

        this.stream_token = this.pending_token;
        this.pending_token = 0;
        this.event_source = this.pending_event_source;
        this.pending_event_source = null;

        // 判定：有舊線 → 關（新線已接手）
        if (this.previous_event_source !== null) {
            this.previous_event_source.close();
            this.previous_event_source = null;
        }
    }

    // 套用單一 type 事件（seed 或 delta——只認本條線）
    async apply_stream_event(stream_type, event, open_token) {
        // 判定：新線第一個事件 → 即刻換線（舊線關閉——之後只認新線）
        if (this.pending_token === open_token) {
            this.activate_pending_source(open_token);
        }


        // 判定：已關線 → 唔處理
        if (this.enabled === false) {
            registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "drop_disabled", { type: stream_type });

            return;
        }

        // 判定：open 期間已被取代 → 唔處理
        if (this.stream_token !== open_token) {
            registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "drop_token", { type: stream_type });

            return;
        }

        let event_payload = null;

        // 判定：解析失敗 → 跳過
        try {
            event_payload = JSON.parse(event.data);
        } catch (parse_error) {
            registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "drop_parse", { type: stream_type, message: String(parse_error) });

            return;
        }

        // polling（seed 帶 current；delta 亦以 current 為準）
        if (stream_type === "polling") {
            const polling_snapshot = build_stream_polling_snapshot(event_payload);


            try {
                const state_watch_module = registry().sc_state_watch();
                const state_watch_domain = state_watch_module.state_watch();
                const apply_polling_instance = await state_watch_domain.act().apply_polling();
                await apply_polling_instance.apply_polling(polling_snapshot);

            } catch (apply_error) {
                registry().sc_console(...CONSOLE_SCOPES).log("[trace]", "polling_error", { rev: event_payload.rev, message: String(apply_error) });
            }

            return;
        }

        // chat delta
        if (stream_type === "chat") {
            const stream_instance = await registry().sc_state_stream().stream();
            const chat_updater_instance = await stream_instance.act().act_chat_updater();

            await chat_updater_instance.apply_chat_stream_delta(event_payload);
        }
        // project delta
        else if (stream_type === "project") {
            const stream_instance = await registry().sc_state_stream().stream();
            const project_updater_instance = await stream_instance.act().act_project_updater();

            await project_updater_instance.apply_project_stream_delta(event_payload);
        }
        // 其餘類型 → 唔處理
        else {
            return;
        }
    }

    // 關線（停／重開前先清）
    close() {
        this.enabled = false;
        this.stream_token = this.stream_token + 1;

        // 判定：有連線 → 關閉
        if (this.event_source !== null) {
            this.event_source.close();
            this.event_source = null;
        }

        // 判定：換線中途 → 兩條都清（唔留孤兒連線）
        this.pending_token = 0;

        // 判定：有待接手線 → 關
        if (this.pending_event_source !== null) {
            this.pending_event_source.close();
            this.pending_event_source = null;
        }

        // 判定：有舊線 → 關
        if (this.previous_event_source !== null) {
            this.previous_event_source.close();
            this.previous_event_source = null;
        }
    }
}

export const state_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _state_stream());
