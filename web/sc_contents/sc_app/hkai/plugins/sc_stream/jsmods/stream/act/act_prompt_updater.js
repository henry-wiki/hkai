// jsmods/stream/act/act_prompt_updater.js —— 草稿變動套用（act 層——一檔一事）
// 職責：收 state_stream 的 prompt change（本頁草稿的 change 陣列）→ 一律還原 composer（內容空＝輸入框自然變空）
// == 刪除唔在此處理：草稿刪除＝清道夫（過期掃描）另一套機制，不經 health change
// == 上游：jsmods/sc_stream/prompt/prompt_stream.js（changed 含 "prompt"；兩層 key：project_slug → chat_sid）
// == 下游：sc_ai_chats prompt evt prompt_draft_restore

class _act_prompt_updater {
    constructor() {}

    // 草稿有變 → 還原 composer（modified＝內容／附件變；空白亦由此反映）
    async apply_prompt_stream_delta(prompt_change_items, writer_view_id) {
        // 判定：冇任何改動 → 唔動作
        if (prompt_change_items.length < 1) {
            return;
        }



        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_draft_restore_instance = await prompt_instance.evt().prompt_draft_restore();

        // 最後一條 change 的 current＝最新草稿（{lastupdate, draft}）
        const latest_change_item = prompt_change_items[prompt_change_items.length - 1];
        const latest_entry = latest_change_item.current || {};
        const draft_result = latest_entry.draft || null;

        // 判定：冇帶內容（舊版後端）→ 退回 fetch 一次
        if (draft_result === null) {
            await prompt_draft_restore_instance.prompt_draft_restore(true);

            return;
        }

        // 判定：自己頁面寫的（event.view_id＝本頁 viewer）→ 唔套回自己（避免輸入被覆蓋）
        const view_meta = document.querySelector("meta[name='view-id']");
        const own_view_id = (view_meta === null) ? "" : view_meta.getAttribute("content");
        const writer_id = String(writer_view_id || "");

        // 判定：自己寫 → 唔動作
        if (writer_id !== "" && writer_id === own_view_id) {
            return;
        }

        console.log(prompt_change_items);

        // 直接套用事件內容（免 draft_fetch request）
        await prompt_draft_restore_instance.apply_draft(draft_result, true);
    }
}

export const act_prompt_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_prompt_updater());
