// jsmods/stream/act/collect_current_entry.js —— change 清單併回 entry（純函數——零狀態；conn／git updater 共用）
// 職責：逐條 change item 的 meta.current 併成一個完整 entry；任何一條 type＝deleted → 回 null
// 上游：act_conn_updater
// 下游：無（純資料處理）

// 併回完整 entry（change_items＝該 key 的 change 陣列）
export function collect_current_entry(change_items) {
    let current_entry = null;

    for (let item_index = 0; item_index < change_items.length; item_index++) {
        const change_item = change_items[item_index];

        // 判定：刪除 → 明確標示（current 為 null）
        if (change_item.type === "deleted") {
            return null;
        }

        // 判定：首次到達 → 開容器
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
