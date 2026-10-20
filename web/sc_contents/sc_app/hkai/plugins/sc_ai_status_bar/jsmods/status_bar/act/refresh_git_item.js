// jsmods/action/refresh_git_item.js —— Git 狀態刷新落 sb_git（action 層——一 action 一檔）
// 職責：refresh_git_item（project_slug → projects tab_git 域 git_status → apply_git_status；60 秒 interval 重跑）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _refresh_git_item {
    constructor() {
        // Git 刷新 interval id（start_git_refresh 建立）
        this.git_refresh_timer_id = null;
    }

    // Git 狀態 → sb_git（來源：POST /request/hkai/git_status/——唯讀）
    async refresh_git_item() {
        // registry 取值（靜態——直回單例）
        const projects = registry().sc_ai_projects();

        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        // 動態載入（callback——git status item）
        const git_status_item_instance = await status_bar_domain.act().git_status_item();

        // 判定：item 不存在 → 不做（plugin 未渲染）
        if (!status_item_render_instance.get_item("sb_git")) {
            return;
        }

        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();

        // 判定：無選取專案 → 維持預設
        if (!project_slug) {
            status_item_render_instance.set_item("sb_git", "—");
            status_item_render_instance.set_item_class("sb_git", "");
            return;
        }

        // 橋接駁（tab_git 域 req 橋——取 git 狀態）
        const tab_git_instance = await projects.tab_git();
        const git_status_instance = await tab_git_instance.req().git_status();

        const git_status_result = await git_status_instance.git_status(project_slug);

        git_status_item_instance.apply_git_status(git_status_result);
    }

    // 定時刷新 Git 狀態（60 秒——build 模式 aider 改檔後狀態會變）
    start_git_refresh() {
        const thisObject = this;
        thisObject.git_refresh_timer_id = setInterval(function () {
            thisObject.refresh_git_item();
        }, 60000);
    }
}

export const refresh_git_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_git_item());
