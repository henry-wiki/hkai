// jsmods/action/open_project_git_modal.js —— 開專案編輯 modal 停 Git tab（action 層——一 action 一檔）
// 職責：open_project_git_modal（目前選取專案 → projects modal 域 modal_open，tab_id="git"）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _open_project_git_modal {
    constructor() {}

    // 開啟「目前選取專案」編輯 modal 並停在 Git tab（本 bar 專屬動作——sb_git item 點擊；2026-09-23 自 sc_ai_projects/project.js 搬入）
    async open_project_git_modal() {
        // registry 取值（靜態——直回單例）
        const projects = registry().sc_ai_projects();

        const j_active_item = document.querySelector(".sc_project_list .sc_project_card--active");

        // 判定：無選取專案 → 不開
        if (!j_active_item) {
            return;
        }

        // 取得 project_slug → 經 projects modal 域 act 橋開 modal（tab_id="git"——body 直接載 Git pane）
        const project_slug = j_active_item.dataset.project_slug;

        // 橋接駁（modal 域 act 橋——開 modal）
        const modal_instance = await projects.modal();
        const modal_open_instance = await modal_instance.act().modal_open();

        await modal_open_instance.modal_open(project_slug, "git");
    }
}

export const open_project_git_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _open_project_git_modal());
