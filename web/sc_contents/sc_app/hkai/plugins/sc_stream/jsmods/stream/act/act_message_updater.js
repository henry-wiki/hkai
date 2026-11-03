// jsmods/stream/act/act_message_updater.js —— 訊息變動套用（act 層——一檔一事）
// 職責：收 state_stream 的 message changes（key＝message_id；added／updated／deleted）→ 跑一輪 message_poll 更新畫面
// 上游：jsmods/sc_stream/state/state_stream.js（event: message）；下游：message/act/message_poll.js、message/act/message_stream.js

class _act_message_updater {
    constructor() {}

    // 套用 message 變動（added／updated／deleted；key＝message_id）
    async apply_message_stream_delta(message_stream_delta) {
        const added_items = message_stream_delta.added || {};
        const updated_items = message_stream_delta.updated || {};
        const deleted_items = message_stream_delta.deleted || {};

        const changed_message_ids = this.collect_message_ids(added_items, updated_items, deleted_items);

        // 判定：冇任何變動 → 唔動作
        if (changed_message_ids.length < 1) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();
        const current_chat_sid = chat_instance.get_chat_sid();

        // 判定：本頁未選對話 → 唔動作
        if (current_chat_sid === "") {
            return;
        }

        const message_instance = await registry().sc_ai_chats().message();
        const message_poll_instance = await message_instance.act().message_poll();

        // 判定：poll 已暫停（SSE 驅動）→ 用串流路徑：開工作中卡片嘅線＋單次渲染
        if (message_poll_instance.poll_paused === true) {
            const message_stream_instance = await message_instance.act().message_stream();

            await message_stream_instance.start_for_page();

            message_poll_instance.render_once(current_chat_sid);

            await this.refresh_missing_messages(changed_message_ids);

            return;
        }

        message_poll_instance.message_poll(current_chat_sid);
    }

    // 收集三類變動嘅 message_id（去重）
    collect_message_ids(added_items, updated_items, deleted_items) {
        const message_ids = [];

        const item_groups = [added_items, updated_items, deleted_items];

        // 逐組收集
        for (let group_index = 0; group_index < item_groups.length; group_index = group_index + 1) {
            const item_group = item_groups[group_index];
            const group_ids = Object.keys(item_group);

            // 逐個 id 收集（未出現過才加入）
            for (let id_index = 0; id_index < group_ids.length; id_index = id_index + 1) {
                const message_id = group_ids[id_index];

                // 判定：已收集 → 跳過
                if (message_ids.includes(message_id) === true) {
                    continue;
                }

                message_ids.push(message_id);
            }
        }

        return message_ids;
    }

    // 畫面缺少變動訊息 → 重取訊息窗（SSE 完成後冇 poll 回應帶 HTML 嘅替代路徑）
    async refresh_missing_messages(changed_message_ids) {
        let has_missing_message = false;

        // 逐條變動：key＝message_id；畫面搵唔到＝缺
        for (let key_index = 0; key_index < changed_message_ids.length; key_index = key_index + 1) {
            const key_message_id = Number(changed_message_ids[key_index]);

            // 判定：id 無效 → 跳過
            if (key_message_id < 1) {
                continue;
            }

            // 判定：已在畫面 → 跳過
            if (jQuery(".message_wrapper[data-message_id='" + key_message_id + "']").length > 0) {
                continue;
            }

            has_missing_message = true;
        }

        // 判定：冇缺 → 唔做
        if (has_missing_message === false) {
            return;
        }

        const message_instance = await registry().sc_ai_chats().message();
        const message_list_fetch_instance = await message_instance.act().message_list_fetch();

        await message_list_fetch_instance.message_list_fetch();
    }
}

export const act_message_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_message_updater());
