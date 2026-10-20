// jsmods/chat/act/detect_project_slug_change.js —— 換專案偵測（本機側動作——act 層；一檔一事）
// 職責：detect_project_slug_change（前後兩條 URL 嘅專案 slug 有換＝交清單重取＋訊息窗（快照復位）＋prompt 一齊重取；冇換＝return）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _detect_project_slug_change {
    constructor() {}

    // 換專案偵測（專案 slug 有換＝情境全換——清單＋訊息窗＋prompt 一齊重取）
    async detect_project_slug_change(goto_url,prev_url) {

        const project_instance = await registry().sc_ai_projects().project();

        const curr_project_slug = project_instance.get_project_slug_from_url(goto_url);
        const prev_project_slug = project_instance.get_project_slug_from_url(prev_url);

        // 判定：專案冇換（前後兩條 URL 嘅 slug 相同）→ 唔做（清單唔重取）
        if (curr_project_slug === prev_project_slug) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();

        // 清單重取（專案換＝左側清單換項目）
        const chat_list_fetch_instance = await chat_instance.act().chat_list_fetch();
        await chat_list_fetch_instance.chat_list_fetch();

        // 「係唔係最底」快照復位（換專案＝開新情境——訊息窗由跟底起步；舊情境嘅瀏覽態唔帶過去）
        jQuery(".sc_thread__viewport").attr("data-is_at_bottom", "1");

        // 訊息窗＋prompt 重取（專案換＝對話情境全換——對照 detect_chat_sid_change 嘅載入塊，同一反應）
        const message_instance = await registry().sc_ai_chats().message();
        const message_list_fetch_instance = await message_instance.act().message_list_fetch();

        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

        // 兩邊同時出發（兩個 panel 嘅 loading 同步 show）
        const message_load_promise = message_list_fetch_instance.message_list_fetch();
        const prompt_load_promise  = prompt_fetch_instance.prompt_fetch();

        // 等齊（各自完成各自收遮罩）
        await message_load_promise;
        await prompt_load_promise;
    }
}

export const detect_project_slug_change = (function (instance) {
    return function () {

        return instance;
    };
})(new _detect_project_slug_change());
