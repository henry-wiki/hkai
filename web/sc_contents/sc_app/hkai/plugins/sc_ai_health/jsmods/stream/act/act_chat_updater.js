// jsmods/stream/act/act_chat_updater.js —— 對話變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 chat changes（逐欄一條）→ 逐 chat_sid 更新對話卡

class _act_chat_updater {
    constructor() {}

    // 套用 chat 變動（chat_change＝payload 內 chat 段：{changes:{chat_sid:[changeItem]}}）
    async apply_chat_change(chat_change) {
        const chat_changes = chat_change.changes || {};
        const changed_chat_sids = Object.keys(chat_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_chat_sids.length < 1) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();
        const project_instance = await registry().sc_ai_projects().project();

        const current_project_slug = project_instance.get_project_slug();

        for (let change_index = 0; change_index < changed_chat_sids.length; change_index++) {
            const chat_sid = changed_chat_sids[change_index];
            const chat_change_items = chat_changes[chat_sid] || [];

            const change_types = this.collect_change_types(chat_change_items);

            // 判定：對話被刪 → 專案匹配才移除該卡
            if (change_types.includes("deleted")) {
                const previous_entry = this.collect_previous_entry(chat_change_items);

                // 判定：唔屬於現時專案 → 跳過
                if (previous_entry.project_slug !== current_project_slug) {
                    continue;
                }

                const chat_item_remove_instance = await chat_instance.act().chat_item_remove();
                chat_item_remove_instance.chat_item_remove(chat_sid);
            }
            // 判定：對話新增 → 專案匹配才補入該卡
            else if (change_types.includes("added")) {
                const current_entry = this.collect_current_entry(chat_change_items);

                // 判定：唔屬於現時專案 → 跳過
                if (current_entry.project_slug !== current_project_slug) {
                    continue;
                }

                const chat_item_insert_instance = await chat_instance.act().chat_item_insert();
                await chat_item_insert_instance.chat_item_insert(chat_sid);
            }
            // 判定：欄位改動 → 併回完整 current entry
            else if (change_types.includes("modified")) {
                const current_entry = this.collect_current_entry(chat_change_items);

                // 判定：唔屬於現時專案 → 跳過
                if (current_entry.project_slug !== current_project_slug) {
                    continue;
                }

                // 判定：status 有變 → 換清單（正常列 ↔ 歸檔列）
                if (Object.prototype.hasOwnProperty.call(current_entry, "status")) {
                    await this.apply_chat_status_move(chat_sid, current_entry.status);
                } else {
                    this.apply_modified_chat(chat_sid, current_entry);
                }

                // 判定：本對話就係當前頁面 → 起 poll（另一頁新訊息／狀態變化——poll 有 running guard）
                if (chat_sid === chat_instance.get_chat_sid()) {
                    const message_instance = await registry().sc_ai_chats().message();
                    const message_poll_instance = await message_instance.act().message_poll();

                    message_poll_instance.message_poll(chat_sid);
                }
            }
        }

        // 更新完 → 重排（次序無變＝no-op）
        const chat_activity_instance = await chat_instance.act().chat_activity();

        chat_activity_instance.chat_list_rank_refresh();

        // 狀態欄對話區跟隨（同一支 URL 驅動 fetch——單一來源）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const chat_fetch_instance = await status_bar_domain.act().chat_fetch();
        chat_fetch_instance.chat_fetch();
    }

    // 收集該 chat_sid 的改動類型
    collect_change_types(chat_change_items) {
        const change_types = [];

        for (let item_index = 0; item_index < chat_change_items.length; item_index++) {
            change_types.push(chat_change_items[item_index].type);
        }

        return change_types;
    }

    // 逐條 change 的 meta.previous 併回一個完整 entry（deleted 用）
    collect_previous_entry(chat_change_items) {
        const previous_entry = {};

        for (let item_index = 0; item_index < chat_change_items.length; item_index++) {
            const item_previous = chat_change_items[item_index].meta.previous || {};
            const field_names = Object.keys(item_previous);

            for (let field_index = 0; field_index < field_names.length; field_index++) {
                const field_name = field_names[field_index];
                previous_entry[field_name] = item_previous[field_name];
            }
        }

        return previous_entry;
    }

    // 逐條 change 的 meta.current 併回一個完整 entry
    collect_current_entry(chat_change_items) {
        const current_entry = {};

        for (let item_index = 0; item_index < chat_change_items.length; item_index++) {
            const item_current = chat_change_items[item_index].meta.current || {};
            const field_names = Object.keys(item_current);

            for (let field_index = 0; field_index < field_names.length; field_index++) {
                const field_name = field_names[field_index];
                current_entry[field_name] = item_current[field_name];
            }
        }

        return current_entry;
    }

    // status 轉換 → 卡片換清單（現時語境＝正常列：archived 移除／normal 補回；歸檔列相反）
    async apply_chat_status_move(chat_sid, chat_status) {
        const chat_instance = await registry().sc_ai_chats().chat();

        const list_context = chat_instance.get_chat_list_context_from_url(location.href);
        const is_archive_context = (list_context === "archive");

        const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid*='" + chat_sid + "']");

        const should_show = (chat_status === "archived") ? is_archive_context : (is_archive_context === false);

        // 判定：應顯示但卡唔在 → 補入（歸檔語境＝archive@<sid>）
        if (should_show === true && j_chat_item.length < 1) {
            const chat_segment = (is_archive_context === true) ? ("archive@" + chat_sid) : chat_sid;

            const chat_item_insert_instance = await chat_instance.act().chat_item_insert();
            await chat_item_insert_instance.chat_item_insert(chat_segment);

            return;
        }

        // 判定：唔應顯示但卡仍在 → 移除
        if (should_show === false && j_chat_item.length > 0) {
            const chat_item_remove_instance = await chat_instance.act().chat_item_remove();
            chat_item_remove_instance.chat_item_remove(chat_sid);
        }
    }

    // 單一 modified 對話卡：時間 attr＋polling class
    apply_modified_chat(chat_sid, current_entry) {
        const j_chat_item = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid*='" + chat_sid + "']");

        // 判定：卡唔存在 → 唔動作
        if (j_chat_item.length < 1) {
            return;
        }

        // 判定：本條 change 有帶 lastupdate → 才寫 attr（只帶 polling 時唔可以蓋走真實時間）
        if (Object.prototype.hasOwnProperty.call(current_entry, "lastupdate")) {
            const chat_lastupdate = String(current_entry.lastupdate);

            // 判定：時間有變 → 寫 attr（觀察器據此重繪）
            if (j_chat_item.attr("data-update_time") !== chat_lastupdate) {
                j_chat_item.attr("data-update_time", chat_lastupdate);
            }
        }

        // 判定：polling 有變 → 加／除 class（純 CSS 畫 bar）
        if (Object.prototype.hasOwnProperty.call(current_entry, "polling")) {
            if (Number(current_entry.polling) > 0) {
                j_chat_item.addClass("polling");
            } else {
                j_chat_item.removeClass("polling");
            }
        }
    }
}

export const act_chat_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_chat_updater());
