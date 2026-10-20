// jsmods/project/act/project_item_remove.js —— 專案項移除（act 層——一檔一事；主動作＝檔名）
// 職責：project_item_remove（按 project_slug 移除該專案項 DOM——由 project_list_refresh 迴圈逐個呼叫）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_item_remove {
    constructor() {}

    // 按 project_slug 移除單一專案項
    project_item_remove(project_slug) {
        const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']");

        j_project_item.remove();
    }
}

export const project_item_remove = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_item_remove());
