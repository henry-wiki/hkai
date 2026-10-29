// jsmods/stream/act/act_git_updater.js —— git 狀態變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 git changes → 套落狀態欄 sb_git（渲染交 git_status_item）

class _act_git_updater {
    constructor() {}

    // 套用 git 變動（git_change＝payload 內 git 段：{changes:{project_slug:[changeItem]}}）
    async apply_git_change(git_change) {
        const git_changes = git_change.changes || {};
        const changed_project_slugs = Object.keys(git_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_project_slugs.length < 1) {
            return;
        }

        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const git_status_item_instance = await status_bar_domain.act().git_status_item();

        // 逐個專案（當前專案一份）→ 併回完整 entry → 交渲染
        for (let change_index = 0; change_index < changed_project_slugs.length; change_index++) {
            const project_slug = changed_project_slugs[change_index];
            const git_change_items = git_changes[project_slug] || [];

            const current_entry = this.collect_current_entry(git_change_items);

            // 判定：已刪除（冇 current）→ 顯示佔位
            if (current_entry === null) {
                git_status_item_instance.apply_git_status(null);
                continue;
            }

            git_status_item_instance.apply_git_status(this.build_git_status_result(current_entry));
        }
    }

    // 逐條 change 的 meta.current 併回完整 entry（deleted → null）
    collect_current_entry(git_change_items) {
        let current_entry = null;

        for (let item_index = 0; item_index < git_change_items.length; item_index++) {
            const change_item = git_change_items[item_index];

            // 判定：刪除 → 明確標示
            if (change_item.type === "deleted") {
                return null;
            }

            if (current_entry === null) {
                current_entry = {};
            }

            const current_meta = (change_item.meta || {}).current || {};

            const current_keys = Object.keys(current_meta);

            for (let key_index = 0; key_index < current_keys.length; key_index++) {
                const field_key = current_keys[key_index];

                current_entry[field_key] = current_meta[field_key];
            }
        }

        return current_entry;
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

        const git_changes = {};
        git_changes.total = Number(git_entry.dirty || 0);

        git_status_result.changes = git_changes;

        return git_status_result;
    }
}

export const act_git_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_git_updater());
