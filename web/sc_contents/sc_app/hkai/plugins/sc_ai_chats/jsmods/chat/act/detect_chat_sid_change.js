// jsmods/chat/act/detect_chat_sid_change.js —— 換對話偵測（本機側動作——act 層；一檔一事）
// 職責：detect_chat_sid_change（前後兩條 URL 嘅 sid 有換＝交 chat 域 act 換主（窗側主鍵＋清單高亮）＋載入訊息窗＋prompt；冇換＝return——shallow 事件唔會嚟到（觀察者已篩））
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _detect_chat_sid_change {
    constructor() {}

    // 換對話偵測（點擊／URL 換對話——前後兩條 URL 嘅 sid 有換＝交換主＋載入訊息窗；冇換＝return）
    async detect_chat_sid_change(goto_url,prev_url) {
        const chat_instance = await registry().sc_ai_chats().chat();

        const curr_chat_sid = chat_instance.get_chat_sid_from_url(goto_url);
        const prev_chat_sid = chat_instance.get_chat_sid_from_url(prev_url);

        // 判定：對話冇換（前後兩條 URL 嘅 sid 相同）→ 唔做（唔關本域事）
        if (curr_chat_sid === prev_chat_sid) {
            return;
        }

        // 判定：專案同時換咗 → 唔做（交畀換專案偵測——佢已連訊息窗＋prompt 一齊重取；防雙重 fetch）
        const project_instance = await registry().sc_ai_projects().project();
        const curr_project_slug = project_instance.get_project_slug_from_url(goto_url);
        const prev_project_slug = project_instance.get_project_slug_from_url(prev_url);

        if (curr_project_slug !== prev_project_slug) {
            return;
        }

        // 橋接（兩邊 instance 先攞齊——先攞後發，兩邊同刻出發）
        const message_instance = await registry().sc_ai_chats().message();
        const message_list_fetch_instance = await message_instance.act().message_list_fetch();

        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

        // 兩邊同時出發（load_message_list before 同 load_prompt before 同刻派——兩個 panel 嘅 loading 同步 show）
        const message_load_promise = message_list_fetch_instance.message_list_fetch();
        const prompt_load_promise  = prompt_fetch_instance.prompt_fetch();

        // 等齊（各自完成各自收遮罩）
        await message_load_promise;
        await prompt_load_promise;
    }
}

export const detect_chat_sid_change = (function (instance) {
    return function () {

        return instance;
    };
})(new _detect_chat_sid_change());
