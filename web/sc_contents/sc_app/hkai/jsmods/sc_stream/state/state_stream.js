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
        this.stream_types = ["chat", "project", "polling", "git", "conn", "message"];

        // 開線令牌（open 期間再 open／close → 舊連線作廢）
        this.stream_token = 0;

        // 已啟動旗標（事件 handler 先檢查——close 後殘留事件唔處理）
        this.enabled = false;
    }

    // 開線（URL 由本模組砌——caller 只交三個身份值）
    async open(project_slug, chat_sid, view_id) {
        const thisObject = this;

        thisObject.close();

        // 判定：瀏覽器唔支援 EventSource → 唔開
        if (typeof EventSource === "undefined") {
            return;
        }

        thisObject.stream_token = thisObject.stream_token + 1;
        const open_token = thisObject.stream_token;

        thisObject.enabled = true;

        const stream_url = thisObject.stream_url
            + "?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid)
            + "&view_id=" + encodeURIComponent(view_id);

        const event_source = new EventSource(stream_url);

        // open（handshake——冇資料要處理）
        event_source.addEventListener("open", function () {
            thisObject._trace("open", { ready_state: event_source.readyState });
        });

        // error（連線層錯誤——EventSource 會自行重連；記錄用嚟對時序）
        event_source.addEventListener("error", function () {
            thisObject._trace("error", { ready_state: event_source.readyState });
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

        thisObject.event_source = event_source;
    }

    // 套用單一 type 事件（seed 或 delta——只認本條線）
    async apply_stream_event(stream_type, event, open_token) {
        // 記錄：事件入咗 JS（未判線／未解析——分辨「未派發」用）
        this._trace("evt_in", { type: stream_type, same_token: this.stream_token === open_token, enabled: this.enabled });

        // 判定：已關線 → 唔處理
        if (this.enabled === false) {
            this._trace("drop_disabled", { type: stream_type });

            return;
        }

        // 判定：open 期間已被取代 → 唔處理
        if (this.stream_token !== open_token) {
            this._trace("drop_token", { type: stream_type });

            return;
        }

        let event_payload = null;

        // 判定：解析失敗 → 跳過
        try {
            event_payload = JSON.parse(event.data);
        } catch (parse_error) {
            this._trace("drop_parse", { type: stream_type, message: String(parse_error) });

            return;
        }

        // polling（seed 帶 current；delta 亦以 current 為準）
        if (stream_type === "polling") {
            const polling_snapshot = build_stream_polling_snapshot(event_payload);

            this._trace("polling_before", {
                rev: event_payload.rev,
                payload_keys: Object.keys(event_payload).join(","),
                has_changes: (event_payload.changed !== undefined),
                dom_polling_cards: document.querySelectorAll(".sc_chat_card.polling").length,
            });

            try {
                const state_watch_module = registry().sc_state_watch();
                const state_watch_domain = state_watch_module.state_watch();
                const apply_polling_instance = await state_watch_domain.act().apply_polling();
                await apply_polling_instance.apply_polling(polling_snapshot);

                this._trace("polling_after", {
                    rev: event_payload.rev,
                    dom_polling_cards: document.querySelectorAll(".sc_chat_card.polling").length,
                });
            } catch (apply_error) {
                this._trace("polling_error", { rev: event_payload.rev, message: String(apply_error) });
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
        // git delta
        else if (stream_type === "git") {
            const stream_instance = await registry().sc_state_stream().stream();
            const git_updater_instance = await stream_instance.act().act_git_updater();

            await git_updater_instance.apply_git_stream_delta(event_payload);
        }
        // conn delta
        else if (stream_type === "conn") {
            const stream_instance = await registry().sc_state_stream().stream();
            const conn_updater_instance = await stream_instance.act().act_conn_updater();

            await conn_updater_instance.apply_conn_stream_delta(event_payload);
        }
        // 其餘類型 → 唔處理
        else {
            return;
        }
    }

    // 記錄一條（落 localStorage ring buffer——查漏報用；唔影響流程）
    _trace(trace_kind, trace_detail) {
        const trace_record = {
            t: Date.now(),
            iso: new Date().toISOString(),
            kind: trace_kind,
            detail: trace_detail,
        };

        try {
            const raw_trace = localStorage.getItem("__hkai_poll_trace");
            const trace_list = (raw_trace === null) ? [] : JSON.parse(raw_trace);

            trace_list.push(trace_record);
            localStorage.setItem("__hkai_poll_trace", JSON.stringify(trace_list.slice(-600)));
        } catch (storage_error) {
            return;
        }

        this._trace_console(trace_record);
    }

    // 同步落 console（registry().sc_console——靜態入口，免動態 import）
    _trace_console(trace_record) {
        registry().sc_console(...CONSOLE_SCOPES).log("[trace]", trace_record.kind, trace_record.detail);
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
    }
}

export const state_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _state_stream());
