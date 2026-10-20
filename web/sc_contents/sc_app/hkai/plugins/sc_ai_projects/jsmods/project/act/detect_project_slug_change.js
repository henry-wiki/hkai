// jsmods/project/act/detect_project_slug_change.js —— 換專案偵測（本機側動作——act 層；一檔一事）
// 職責：detect_project_slug_change（URL 第一段有冇換——換咗＝交 project_item_fetch 切換（清單換高亮＋攞返本項＋轉向））
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _detect_project_slug_change {
    constructor() {}

    // 換專案偵測（前一條 URL 由變化事件帶入——前後第一段對照；零記憶、零首見特例）
    async detect_project_slug_change(previous_url) {
        const project_instance = await registry().sc_ai_projects().project();

        // 前後嘅專案段（URL 第一段——前一條抽自變化事件、現況＝域即讀）
        const previous_project_slug = project_instance.get_project_slug_from_url(previous_url);
        const project_slug = project_instance.get_project_slug();

        // 判定：專案冇換 → 唔做（唔關本域事嘅 URL 變）
        if (project_slug === previous_project_slug) {
            return;
        }

        const project_item_fetch_instance = await project_instance.act().project_item_fetch();

        await project_item_fetch_instance.switch_project(project_slug);
    }
}

export const detect_project_slug_change = (function (instance) {
    return function () {

        return instance;
    };
})(new _detect_project_slug_change());
