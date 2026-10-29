// jsmods/status_bar/act/refresh_git_item.js —— Git 狀態刷新（act 層——一 action 一檔）
// 職責：refresh_git_item（project_slug → projects tab_git 域 git_status → apply_git_status）
// 上游：status bar obs:url_changed（切換專案／對話）；下游：git_status req＋git_status_item 渲染
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _refresh_git_item {
    constructor() {
        this.refreshing = false;
    }

    // Git 狀態 → sb_git（來源：POST /request/hkai/git_status/——唯讀）
    async refresh_git_item() {
        // 判定：已有請求在途 → 唔重複發（init 與 url_changed 同刻只跑一次）
        if (this.refreshing === true) {
            return;
        }

        this.refreshing = true;

        try {
            await this.fetch_and_apply();
        } finally {
            this.refreshing = false;
        }
    }

    // 實際取 git 狀態並套用（單飛包住的主體）
    async fetch_and_apply() {
        const projects = registry().sc_ai_projects();

        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        const git_status_item_instance = await status_bar_domain.act().git_status_item();

        // 判定：item 不存在 → 不做（plugin 未渲染）
        if (!status_item_render_instance.get_item("sb_git")) {
            return;
        }

        const project_instance = await projects.project();
        let project_slug = project_instance.get_project_slug();

        // 判定：專案域未就緒 → 由 URL 第一段取
        if (!project_slug) {
            const path_segments = location.pathname.split("/").filter(function (path_segment) { return path_segment !== ""; });

            project_slug = path_segments[0] || "";
        }

        // 判定：無選取專案 → 維持預設
        if (!project_slug) {
                status_item_render_instance.set_item("sb_git", "—");
            status_item_render_instance.set_item_class("sb_git", "");

            return;
        }

        const tab_git_instance = await projects.tab_git();
        const git_status_instance = await tab_git_instance.req().git_status();

        const git_status_result = await git_status_instance.git_status(project_slug);

        // 判定：取唔到（null／ok 非 true）→ 保留現有顯示（唔清成「—」）
        if (!git_status_result) {
            return;
        }

        if (git_status_result.ok !== true) {
            return;
        }

        git_status_item_instance.apply_git_status(git_status_result);
    }
}

export const refresh_git_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_git_item());
