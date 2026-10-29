// jsmods/stream/act/act_conn_updater.js —— 連線狀態變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 conn changes → 套落狀態欄 sb_conn（渲染交 conn_status_item）

class _act_conn_updater {
    constructor() {}

    // 套用 conn 變動（conn_change＝payload 內 conn 段：{changes:{bridge:[changeItem]}}）
    async apply_conn_change(conn_change) {
        const conn_changes = conn_change.changes || {};

        const changed_keys = Object.keys(conn_changes);

        // 判定：冇任何變動 → 唔動作
        if (changed_keys.length < 1) {
            return;
        }

        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const conn_status_item_instance = await status_bar_domain.act().conn_status_item();

        // 逐條 key（現時只有 bridge）→ 併回完整 entry → 交渲染
        for (let change_index = 0; change_index < changed_keys.length; change_index++) {
            const conn_key = changed_keys[change_index];
            const conn_change_items = conn_changes[conn_key] || [];

            const current_entry = this.collect_current_entry(conn_change_items);

            // 判定：已刪除（冇 current）→ 當未連線
            if (current_entry === null) {
                await conn_status_item_instance.apply_conn_status(null);
                continue;
            }

            const conn_result = {};
            conn_result.is_alive = current_entry.is_alive === true;
            conn_result.base_url = current_entry.base_url || "";

            await conn_status_item_instance.apply_conn_status(conn_result);

            // presence 由 conn 狀態驅動（起了→開；停了→關，唔再盲重連）
            const presence_instance = registry().sc_presence();

            // 判定：bridge 在生 → 確保 presence 開住
            if (conn_result.is_alive === true) {
                await presence_instance.ensure_open(conn_result.base_url);
            } else {
                presence_instance.stop();
            }
        }
    }

    // 逐條 change 的 meta.current 併回完整 entry（deleted → null）
    collect_current_entry(conn_change_items) {
        let current_entry = null;

        for (let item_index = 0; item_index < conn_change_items.length; item_index++) {
            const change_item = conn_change_items[item_index];

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
}

export const act_conn_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_conn_updater());
