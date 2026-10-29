// jsmods/stream/act/act_message_updater.js —— 訊息變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 message changes（key＝{chat_sid}/{message_id}）→ 本頁對話有變 → 跑一輪 message_poll 更新畫面
// 上游：health/act/act_fetch_health.js（changed 含 "message"）；下游：message/act/message_poll.js

class _act_message_updater {
    constructor() {}

    // 套用 message 變動（message_change＝payload 內 message 段：{changes:{"chat_sid/message_id":[changeItem]}}）
    async apply_message_change(message_change) {
        const message_changes = message_change.changes || {};
        const changed_message_keys = Object.keys(message_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_message_keys.length < 1) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();

        const current_chat_sid = chat_instance.get_chat_sid();

        // 判定：本頁未選對話 → 唔動作
        if (current_chat_sid === "") {
            return;
        }

        const has_current_chat_change = this.has_chat_change(changed_message_keys, current_chat_sid);

        // 判定：變動唔屬於本頁對話 → 唔動作
        if (has_current_chat_change === false) {
            return;
        }

        const message_instance = await registry().sc_ai_chats().message();
        const message_poll_instance = await message_instance.act().message_poll();

        message_poll_instance.message_poll(current_chat_sid);
    }

    // 判定：有冇任何一條變動屬於指定對話（key 前段＝chat_sid）
    has_chat_change(changed_message_keys, chat_sid) {
        for (let key_index = 0; key_index < changed_message_keys.length; key_index++) {
            const message_key = changed_message_keys[key_index];
            const key_chat_sid = message_key.split("/")[0];

            // 判定：同一個對話 → 有變
            if (key_chat_sid === chat_sid) {
                return true;
            }
        }

        return false;
    }
}

export const act_message_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_message_updater());
