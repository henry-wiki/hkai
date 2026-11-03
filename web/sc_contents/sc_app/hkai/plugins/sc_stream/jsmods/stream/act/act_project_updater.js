// jsmods/stream/act/act_project_updater.js —— 專案變動套用（act 層——一檔一事；主動作＝apply_project_stream_delta）
// 職責：收 state_stream 的 project changes → 按三類改動分流（內容細節唔理——畫面由後端 HTML 出）
//
// == 三類改動對照表（後端 compare_entries 產生；只此三類）
// ① added    → 整份 project_list_fetch()（新卡位置由後端次序決定）
// ② deleted  → 整份 project_list_fetch()（被刪卡唔存在於後端清單）
// ③ modified → 逐張 project_item_fetch(project_slug)
//
// == 上游：jsmods/sc_stream/state/state_stream.js（SSE 收到 project change）
// == 下游：sc_ai_projects project act（project_list_fetch／project_item_fetch）

import { build_stream_change_items } from "./build_stream_change_items.js";

class _act_project_updater {
    constructor() {}

    // 套用 project 變動（project_stream_delta＝payload 內 project 段：{changes:{slug:[changeItem]}}）
    // 次序：① 全掃偵測 added／deleted ② added／deleted＝整份重取；其餘＝逐項換卡
    async apply_project_stream_delta(project_stream_delta) {
        const project_changes = build_stream_change_items(project_stream_delta, 1);
        const project_slugs = Object.keys(project_changes);

        // 判定：冇任何變動 → 唔動作
        if (project_slugs.length < 1) {
            return;
        }

        // 先寫狀態看守（polling／lastupdate 交 sc_state_watch——唯一效果入口）
        const has_lastupdate_change = await this.write_project_values(project_changes, project_slugs, ["chat_count", "lastupdate"]);

        const project_instance = await registry().sc_ai_projects().project();

        // 判定：有專案更新時間改變 → 重排專案列（最新第一）
        if (has_lastupdate_change === true) {
            const project_activity_instance = await project_instance.act().project_list_rank_refresh();
            project_activity_instance.project_list_rank_refresh();
        }

        // ① 全掃：逐類偵測（added／deleted 各自一支）
        const has_added_change = this.has_added_change(project_changes, project_slugs);
        const has_deleted_change = this.has_deleted_change(project_changes, project_slugs);

        // 判定：有新增 → 整份清單重取（卡插入位置由後端次序決定）
        if (has_added_change === true) {
            const project_list_fetch_instance = await project_instance.act().project_list_fetch();
            await project_list_fetch_instance.project_list_fetch();
        }
        // 判定：有刪除 → 整份清單重取（被刪卡唔存在於後端清單）
        else if (has_deleted_change === true) {
            const project_list_fetch_instance = await project_instance.act().project_list_fetch();
            await project_list_fetch_instance.project_list_fetch();
        }
        // 其餘（必定係 modified）→ 逐項換卡（事件帶 HTML＝直接換；冇帶＝fallback 發一次 request）
        else {
            for (let project_index = 0; project_index < project_slugs.length; project_index++) {
                const project_slug = project_slugs[project_index];
                const project_change_items = project_changes[project_slug] || [];

                const project_item_html = this.resolve_project_item_html(project_change_items);

                // 判定：事件已帶卡片 HTML → 直接換 children（免 request）
                if (project_item_html !== "") {
                    await this.replace_project_item(project_slug, project_item_html);
                    continue;
                }

                const project_item_fetch_instance = await project_instance.act().project_item_fetch();
                await project_item_fetch_instance.project_item_fetch(project_slug);
            }
        }
    }

    // 將本次 delta 嘅 polling 值寫入狀態看守（只寫有帶值嘅項）
    async write_project_values(project_changes, project_slugs, value_types) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const write_instance = await state_watch_domain.act().write();
        let has_lastupdate_change = false;

        for (let project_index = 0; project_index < project_slugs.length; project_index++) {
            const project_slug = project_slugs[project_index];
            const project_change_items = project_changes[project_slug] || [];

            for (let type_index = 0; type_index < value_types.length; type_index++) {
                const value_type = value_types[type_index];
                const field_value = this.resolve_field_value(project_change_items, value_type);

                // 判定：本次 delta 冇帶此欄 → 唔寫（唔猜）
                if (field_value === null) {
                    continue;
                }

                const has_changed = await write_instance.write({ target: "project", unit: project_slug, field: value_type, value: field_value });

                // 判定：lastupdate 有變 → 需要重排
                if (value_type === "lastupdate" && has_changed === true) {
                    has_lastupdate_change = true;
                }
            }
        }

        return has_lastupdate_change;
    }

    // 由改動項目解某欄值（deleted＝空字串；modified／added 取 meta.current[欄]；冇＝null）
    resolve_field_value(change_items, field_name) {
        let field_value = null;

        for (let item_index = 0; item_index < change_items.length; item_index++) {
            const change_item = change_items[item_index];

            // 判定：刪除 → 空值
            if (change_item.type === "deleted") {
                field_value = "";
                continue;
            }

            const current_entry = (change_item.meta || {}).current || {};

            // 判定：有帶此欄 → 用佢
            if (current_entry[field_name] !== undefined) {
                field_value = current_entry[field_name];
            }
        }

        return field_value;
    }

    // 由改動項目取後端帶嚟嘅卡片 HTML（冇＝空字串）
    resolve_project_item_html(project_change_items) {
        const project_item_html = "";

        for (let item_index = 0; item_index < project_change_items.length; item_index++) {
            const change_item_html = project_change_items[item_index].html || {};

            // 判定：此項帶卡片 HTML → 用佢
            if (typeof change_item_html.project_item === "string") {
                return change_item_html.project_item;
            }
        }

        return project_item_html;
    }

    // 換入卡片 HTML（只換 children——節點身份保留、觀察器唔斷）
    async replace_project_item(project_slug, project_item_html) {
        const j_project_item = jQuery(".sc_project_list .sc_project_card[data-project_slug='" + project_slug + "']");

        // 判定：卡片唔在畫面（未載入／已切走）→ 唔換
        if (j_project_item.length < 1) {
            return;
        }

        const j_fresh_item = jQuery(project_item_html);

        j_project_item.empty();
        j_project_item.append(j_fresh_item.children());

        // 根節點狀態（active 等 class 同連結喺 <a> 身上——children 換唔到）
        j_project_item.attr("class", j_fresh_item.attr("class"));
        j_project_item.attr("href", j_fresh_item.attr("href"));

        // 換卡會抹走 polling 標記 → 用 store 现值重套（权威來源；完成後會自然除）
        const chat_instance = await registry().sc_ai_chats().chat();
        const polling_state_observer_instance = await chat_instance.obs().polling_state_observer();

        await polling_state_observer_instance.refresh_polling_cards_from_store();
    }

    // 偵測：有冇新增
    has_added_change(project_changes, project_slugs) {
        return this.has_change_type(project_changes, project_slugs, "added");
    }

    // 偵測：有冇刪除
    has_deleted_change(project_changes, project_slugs) {
        return this.has_change_type(project_changes, project_slugs, "deleted");
    }

    // 偵測：有冇指定改動類型
    has_change_type(project_changes, project_slugs, change_type) {
        for (let project_index = 0; project_index < project_slugs.length; project_index++) {
            const project_slug = project_slugs[project_index];
            const project_change_items = project_changes[project_slug] || [];
            const change_types = this.collect_change_types(project_change_items);

            // 判定：類型命中 → 即回
            if (change_types.includes(change_type) === true) {
                return true;
            }
        }

        return false;
    }

    // 收集該 project_slug 的改動類型
    collect_change_types(project_change_items) {
        const change_types = [];

        for (let item_index = 0; item_index < project_change_items.length; item_index++) {
            change_types.push(project_change_items[item_index].type);
        }

        return change_types;
    }
}

export const act_project_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_project_updater());
