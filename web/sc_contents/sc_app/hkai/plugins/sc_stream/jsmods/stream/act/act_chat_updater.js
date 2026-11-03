// jsmods/stream/act/act_chat_updater.js —— 對話變動套用（act 層——一檔一事；主動作＝apply_chat_stream_delta）
// 職責：收 state_stream 的 chat changes → 只寫 state（唯一真相）＋重排
//   卡片增減／換卡一律交 chat/obs/chat_state_observer.js（單一路徑）
//
// == 上游：jsmods/sc_stream/state/state_stream.js（SSE 收到 chat change）
// == 下游：sc_state_watch（寫值）／chat_state_observer（排位）

import { build_stream_change_items } from "./build_stream_change_items.js";

class _act_chat_updater {
    constructor() {}

    // 套用 chat 變動（chat_stream_delta＝payload 內 chat 段：{changes:{project_slug:{chat_sid:[changeItem]}}}）
    // 次序：① 取當前專案那層 ② 寫 state ③ 有 lastupdate 變 → 重排
    async apply_chat_stream_delta(chat_stream_delta) {
        const chat_changes = build_stream_change_items(chat_stream_delta, 2);

        const project_instance = await registry().sc_ai_projects().project();
        const current_project_slug = project_instance.get_project_slug();

        // ① 只取當前專案那層（key＝chat_sid）
        const project_chat_changes = chat_changes[current_project_slug] || {};
        const project_chat_sids = Object.keys(project_chat_changes);

        console.log("[hkai][chat-updater] " + new Date().toISOString() + " project=" + current_project_slug + " change_projects=" + Object.keys(chat_changes).join(",") + " sids=" + project_chat_sids.join(","));

        // 判定：當前專案冇變動 → 唔動作
        if (project_chat_sids.length < 1) {
            return;
        }

        // 先寫狀態看守（polling／lastupdate 交 sc_state_watch——唯一效果入口）
        const has_lastupdate_change = await this.write_chat_values(project_chat_changes, project_chat_sids, ["status", "msg_count", "lastupdate"]);

        const chat_instance = await registry().sc_ai_chats().chat();

        // 判定：有對話更新時間改變 → 重排對話列（最新第一）
        if (has_lastupdate_change === true) {
            const chat_state_observer_instance = await chat_instance.obs().chat_state_observer();

            chat_state_observer_instance.chat_list_rank_refresh();
        }

    }

    async write_chat_values(project_chat_changes, project_chat_sids, value_types) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const write_instance = await state_watch_domain.act().write();
        let has_lastupdate_change = false;

        for (let chat_index = 0; chat_index < project_chat_sids.length; chat_index++) {
            const chat_sid = project_chat_sids[chat_index];
            const chat_change_items = project_chat_changes[chat_sid] || [];

            for (let type_index = 0; type_index < value_types.length; type_index++) {
                const value_type = value_types[type_index];
                const field_value = this.resolve_field_value(chat_change_items, value_type);

                // 判定：本次 delta 冇帶此欄 → 唔寫（唔猜）
                if (field_value === null) {
                    continue;
                }

                const has_changed = await write_instance.write({ target: "chat", unit: chat_sid, field: value_type, value: field_value });

                // 判定：lastupdate 有變 → 需要重排
                if (value_type === "lastupdate" && has_changed === true) {
                    has_lastupdate_change = true;
                }
            }
        }

        return has_lastupdate_change;
    }

    // 由改動項目解某欄值（deleted＝空字串；modified／added 取 meta.current[欄]；冇＝null）
    resolve_field_value(change_items, field_name) {
        let field_value = null;

        for (let item_index = 0; item_index < change_items.length; item_index++) {
            const change_item = change_items[item_index];

            // 判定：刪除 → 空值
            if (change_item.type === "deleted") {
                field_value = "";
                continue;
            }

            const current_entry = (change_item.meta || {}).current || {};

            // 判定：有帶此欄 → 用佢
            if (current_entry[field_name] !== undefined) {
                field_value = current_entry[field_name];
            }
        }

        return field_value;
    }

}

export const act_chat_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_chat_updater());
