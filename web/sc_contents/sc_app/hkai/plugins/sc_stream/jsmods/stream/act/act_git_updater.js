// jsmods/stream/act/act_git_updater.js —— git 狀態變動套用（act 層——一檔一事）
// 職責：收 state_stream 的 git changes → 只留當前專案 → 套落狀態欄 sb_git（渲染交 git_status_item）
// == 上游：jsmods/sc_stream/state/state_stream.js（changed 含 "git"）
// == 下游：sc_ai_status_bar act git_status_item

import { build_stream_change_items } from "./build_stream_change_items.js";

class _act_git_updater {
    constructor() {}

    // 套用 git 變動（git_stream_delta＝payload 內 git 段：{changes:{project_slug:[changeItem]}}）
    async apply_git_stream_delta(git_stream_delta) {
        const git_changes = build_stream_change_items(git_stream_delta, 1);
        const changed_project_slugs = Object.keys(git_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_project_slugs.length < 1) {
            return;
        }

        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const git_status_item_instance = await status_bar_domain.act().git_status_item();

        const project_instance = await registry().sc_ai_projects().project();
        const current_project_slug = project_instance.get_project_slug();

        const collect_module = await import("./collect_current_entry.js");

        // 逐個專案 → 只處理當前專案（狀態欄只顯示當前專案）
        for (let change_index = 0; change_index < changed_project_slugs.length; change_index++) {
            const project_slug = changed_project_slugs[change_index];

            // 判定：唔係當前專案 → 跳過
            if (project_slug !== current_project_slug) {
                continue;
            }

            const git_change_items = git_changes[project_slug] || [];

            const current_entry = collect_module.collect_current_entry(git_change_items);

            // 判定：已刪除（冇 current）→ 顯示佔位
            if (current_entry === null) {
                await git_status_item_instance.apply_git_status(null);
                continue;
            }

            await git_status_item_instance.apply_git_status(this.build_git_status_result(current_entry));
        }
    }

    // monitor entry → git_status 結果形（供 apply_git_status 直接使用）
    build_git_status_result(git_entry) {
        const git_status_result = {};

        git_status_result.ok        = true;
        git_status_result.is_repo   = git_entry.is_repo === true;
        git_status_result.branch    = git_entry.branch || "";
        git_status_result.detached  = git_entry.detached === true;
        git_status_result.head_sha  = git_entry.head_sha || "";
        git_status_result.upstream  = git_entry.upstream || "";
        git_status_result.ahead     = Number(git_entry.ahead || 0);
        git_status_result.behind    = Number(git_entry.behind || 0);

        const git_change_counts = {};
        git_change_counts.total = Number(git_entry.dirty || 0);

        git_status_result.changes = git_change_counts;

        return git_status_result;
    }
}

export const act_git_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_git_updater());
