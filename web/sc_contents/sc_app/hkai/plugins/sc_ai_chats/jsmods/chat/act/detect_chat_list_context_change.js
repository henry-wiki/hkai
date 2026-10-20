// jsmods/chat/act/detect_chat_list_context_change.js —— 換清單語境偵測（本機側動作——act 層；一檔一事）
// 職責：detect_chat_list_context_change（前後兩條 URL 嘅清單語境（普通／歸檔）有換＝交清單重取；冇換＝return；換埋專案＝交畀換專案偵測）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _detect_chat_list_context_change {
    constructor() {}

    // 換清單語境偵測（入／離歸檔——清單要換組（普通清單 ↔ 歸檔清單））
    async detect_chat_list_context_change(goto_url,prev_url) {
        const project_instance = await registry().sc_ai_projects().project();

        const curr_project_slug = project_instance.get_project_slug_from_url(goto_url);
        const prev_project_slug = project_instance.get_project_slug_from_url(prev_url);

        // 判定：專案換埋（換專案偵測會重取清單）→ 唔做（免重複重取）
        if (curr_project_slug !== prev_project_slug) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();

        const curr_context = chat_instance.get_chat_list_context_from_url(goto_url);
        const prev_context = chat_instance.get_chat_list_context_from_url(prev_url);

        // 判定：清單語境冇換（前後兩條 URL 同語境）→ 唔做（清單唔重取）
        if (curr_context === prev_context) {
            return;
        }

        const chat_list_fetch_instance = await chat_instance.act().chat_list_fetch();

        await chat_list_fetch_instance.chat_list_fetch();
    }
}

export const detect_chat_list_context_change = (function (instance) {
    return function () {

        return instance;
    };
})(new _detect_chat_list_context_change());
