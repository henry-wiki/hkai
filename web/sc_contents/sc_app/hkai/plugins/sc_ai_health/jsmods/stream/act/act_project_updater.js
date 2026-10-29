// jsmods/stream/act/act_project_updater.js —— 專案變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 project changes（逐欄一條）→ 逐 slug 更新專案卡

class _act_project_updater {
    constructor() {}

    // 套用 project 變動（project_change＝payload 內 project 段：{changes:{slug:[changeItem]}}）
    async apply_project_change(project_change) {
        const project_changes = project_change.changes || {};
        const changed_project_slugs = Object.keys(project_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_project_slugs.length < 1) {
            return;
        }

        const project_instance = await registry().sc_ai_projects().project();

        for (let change_index = 0; change_index < changed_project_slugs.length; change_index++) {
            const project_slug = changed_project_slugs[change_index];
            const project_change_items = project_changes[project_slug] || [];

            const change_types = this.collect_change_types(project_change_items);

            // 判定：專案被刪 → 移除該卡
            if (change_types.includes("deleted")) {
                const project_item_remove_instance = await project_instance.act().project_item_remove();
                project_item_remove_instance.project_item_remove(project_slug);
            }
            // 判定：專案新增 → 按清單語境補入該卡（歸檔專案唔准入正常列，反之亦然）
            else if (change_types.includes("added")) {
                const current_entry = this.collect_current_entry(project_change_items);

                const list_context = project_instance.get_project_list_context_from_url(location.href);

                const is_archive_context = (list_context === "archive");
                const is_archived_project = (current_entry.status === "archived");

                // 判定：狀態同語境唔對應 → 跳過
                if (is_archived_project !== is_archive_context) {
                    continue;
                }

                const project_segment = (is_archive_context === true) ? ("archive@" + project_slug) : project_slug;

                const project_item_insert_instance = await project_instance.act().project_item_insert();
                await project_item_insert_instance.project_item_insert(project_segment);
            }
            // 判定：欄位改動 → 併回完整 current entry，更新時間 attr＋polling class
            else if (change_types.includes("modified")) {
                const current_entry = this.collect_current_entry(project_change_items);

                // 判定：status 有變 → 換清單（正常列 ↔ 歸檔列）
                if (Object.prototype.hasOwnProperty.call(current_entry, "status")) {
                    await this.apply_status_move(project_slug, current_entry.status);
                } else {
                    this.apply_modified_project(project_slug, current_entry);
                }
            }
        }

        // 更新完 → 重排（次序無變＝no-op）
        const project_list_rank_refresh_instance = await project_instance.act().project_list_rank_refresh();
        project_list_rank_refresh_instance.project_list_rank_refresh();

        // 狀態欄專案區跟隨（同一支 URL 驅動 fetch——單一來源）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const project_fetch_instance = await status_bar_domain.act().project_fetch();
        project_fetch_instance.project_fetch();
    }

    // 收集該 slug 的改動類型
    collect_change_types(project_change_items) {
        const change_types = [];

        for (let item_index = 0; item_index < project_change_items.length; item_index++) {
            change_types.push(project_change_items[item_index].type);
        }

        return change_types;
    }

    // 逐條 change 的 meta.current 併回一個完整 entry
    collect_current_entry(project_change_items) {
        const current_entry = {};

        for (let item_index = 0; item_index < project_change_items.length; item_index++) {
            const item_current = project_change_items[item_index].meta.current || {};
            const field_names = Object.keys(item_current);

            for (let field_index = 0; field_index < field_names.length; field_index++) {
                const field_name = field_names[field_index];
                current_entry[field_name] = item_current[field_name];
            }
        }

        return current_entry;
    }

    // status 轉換 → 卡片換清單（現時語境＝正常列：archived 移除／active 補回；歸檔列相反）
    async apply_status_move(project_slug, project_status) {
        const project_instance = await registry().sc_ai_projects().project();

        const list_context = project_instance.get_project_list_context_from_url(location.href);
        const is_archive_context = (list_context === "archive");

        const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug*='" + project_slug + "']");

        const should_show = (project_status === "archived") ? is_archive_context : (is_archive_context === false);

        // 判定：應顯示但卡唔在 → 補入（歸檔語境＝archive@<slug>）
        if (should_show === true && j_project_item.length < 1) {
            const project_segment = (is_archive_context === true) ? ("archive@" + project_slug) : project_slug;

            const project_item_insert_instance = await project_instance.act().project_item_insert();
            await project_item_insert_instance.project_item_insert(project_segment);

            return;
        }

        // 判定：唔應顯示但卡仍在 → 移除
        if (should_show === false && j_project_item.length > 0) {
            const project_item_remove_instance = await project_instance.act().project_item_remove();
            project_item_remove_instance.project_item_remove(project_slug);
        }
    }

    // 單一 modified 專案卡：時間 attr＋polling class
    apply_modified_project(project_slug, current_entry) {
        const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']");

        // 判定：卡唔存在 → 唔動作
        if (j_project_item.length < 1) {
            return;
        }

        // 判定：本條 change 有帶 lastupdate → 才寫 attr（只帶 polling 時唔可以蓋走真實時間）
        if (Object.prototype.hasOwnProperty.call(current_entry, "lastupdate")) {
            const project_lastupdate = String(current_entry.lastupdate);

            // 判定：時間有變 → 寫 attr（觀察器據此重繪）
            if (j_project_item.attr("data-update_time") !== project_lastupdate) {
                j_project_item.attr("data-update_time", project_lastupdate);
            }
        }

        // 判定：polling 有變 → 加／除 class（純 CSS 畫 bar）
        if (Object.prototype.hasOwnProperty.call(current_entry, "polling")) {
            if (Number(current_entry.polling) > 0) {
                j_project_item.addClass("polling");
            } else {
                j_project_item.removeClass("polling");
            }
        }
    }
}

export const act_project_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_project_updater());
