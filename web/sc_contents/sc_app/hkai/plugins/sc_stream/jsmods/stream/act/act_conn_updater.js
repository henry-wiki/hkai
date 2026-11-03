// jsmods/stream/act/act_conn_updater.js —— 連線狀態變動套用（act 層——一檔一事）
// 職責：收 state_stream 的 conn changes → 套落狀態欄 sb_conn（渲染交 conn_status_item）＋presence 開關
// == 上游：jsmods/sc_stream/state/state_stream.js（changed 含 "conn"）
// == 下游：sc_ai_status_bar act conn_status_item；sc_presence

import { build_stream_change_items } from "./build_stream_change_items.js";

class _act_conn_updater {
    constructor() {
        // 本頁已嘗試恢復未完成回覆（只做一次）
        this.resume_attempted = false;
    }

    // 套用 conn 變動（conn_stream_delta＝payload 內 conn 段：{changes:{bridge:[changeItem]}}）
    async apply_conn_stream_delta(conn_stream_delta) {
        const conn_changes = build_stream_change_items(conn_stream_delta, 1);

        const changed_keys = Object.keys(conn_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_keys.length < 1) {
            return;
        }

        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const conn_status_item_instance = await status_bar_domain.act().conn_status_item();

        const collect_module = await import("./collect_current_entry.js");

        // 逐條 key（現時只有 bridge）→ 併回完整 entry → 交渲染
        for (let change_index = 0; change_index < changed_keys.length; change_index++) {
            const conn_key = changed_keys[change_index];
            const conn_change_items = conn_changes[conn_key] || [];

            const current_entry = collect_module.collect_current_entry(conn_change_items);

            // 判定：已刪除（冇 current）→ 當未連線
            if (current_entry === null) {
                await conn_status_item_instance.apply_conn_status(null);
                continue;
            }

            const conn_result = {};
            conn_result.is_alive = current_entry.is_alive === true;
            conn_result.base_url = current_entry.base_url || "";

            await conn_status_item_instance.apply_conn_status(conn_result);

            await this.sync_presence(conn_result);

            await this.resume_unfinished_reply(conn_result);
        }
    }

    // 伺服器上線後恢復未完成回覆（只做一次；先確認 bridge 在生＋畫面有未完成訊息）
    async resume_unfinished_reply(conn_result) {
        // 判定：bridge 唔在生 → 唔恢復
        if (conn_result.is_alive !== true) {
            return;
        }

        // 判定：本頁已試過 → 唔重複
        if (this.resume_attempted === true) {
            return;
        }

        const current_chat_sid = String(jQuery(".sc_thread__viewport").attr("data-chat_sid") || "");

        // 判定：冇對話身份 → 唔恢復
        if (current_chat_sid === "") {
            return;
        }

        this.resume_attempted = true;

        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_resume_instance = await prompt_instance.act().prompt_resume();

        await prompt_resume_instance.prompt_resume(current_chat_sid);

        console.log("[resume] 已通知伺服器恢復未完成回覆 chat_sid=" + current_chat_sid);
    }

    // presence 跟隨 conn 狀態（起了→開；停了→關，唔再盲重連）
    async sync_presence(conn_result) {
        const presence_instance = registry().sc_presence();

        // 判定：bridge 在生 → 確保 presence 開住
        if (conn_result.is_alive === true) {
            await presence_instance.ensure_open(conn_result.base_url);

            return;
        }

        presence_instance.stop();
    }

}

export const act_conn_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_conn_updater());
