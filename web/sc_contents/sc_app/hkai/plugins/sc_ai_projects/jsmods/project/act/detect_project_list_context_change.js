// jsmods/project/act/detect_project_list_context_change.js —— 換項目清單語境偵測（本機側動作——act 層；一檔一事）
// 職責：detect_project_list_context_change（前後兩條 URL 嘅項目清單語境（普通／歸檔）有換＝交清單重取；冇換＝return）
// 備註：項目清單唔隨換專案重取（普通↔普通＝同一組卡）——淨係入／離歸檔先要換組（普通清單 ↔ 歸檔清單）；所以冇 chats 側嗰個「專案換埋→交畀換專案偵測」嘅跳過閘
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _detect_project_list_context_change {
    constructor() {}

    // 換清單語境偵測（入／離歸檔——清單要換組（普通清單 ↔ 歸檔清單））
    async detect_project_list_context_change(previous_url) {
        const project_instance = await registry().sc_ai_projects().project();

        const curr_context = project_instance.get_project_list_context_from_url(location.href);
        const prev_context = project_instance.get_project_list_context_from_url(previous_url);

        // 判定：清單語境冇換（前後同語境）→ 唔做（清單唔重取）
        if (curr_context === prev_context) {
            return;
        }

        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        await project_list_fetch_instance.project_list_fetch();
    }
}

export const detect_project_list_context_change = (function (instance) {
    return function () {

        return instance;
    };
})(new _detect_project_list_context_change());
