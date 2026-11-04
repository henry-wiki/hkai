// jsmods/stream/act/build_stream_change_items.js —— 把新 delta（added／updated／deleted）轉成 change_items
// 職責：邊界翻譯——後端只交事實（整條 item 前後值）；前端既有 updater 仍用 change_items 格式
// 上游：stream/act/*_updater.js、jsmods/sc_stream/state/state_stream.js（polling）
// 下游：無

// 單條 item → change_item（type／lastupdate／meta{previous,current}）
function build_change_item(change_type, previous_item, current_item) {
    let change_lastupdate = "";

    // 判定：有當刻值 → 用當刻 lastupdate
    if (current_item !== null) {
        change_lastupdate = String(current_item.lastupdate || "");
    }
    // 判定：只有舊值 → 用舊 lastupdate
    else if (previous_item !== null) {
        change_lastupdate = String(previous_item.lastupdate || "");
    }

    return {
        type: change_type,
        lastupdate: change_lastupdate,
        meta: {
            previous: previous_item,
            current: current_item,
        },
    };
}

// 兩層版（chat）：{project_slug: {chat_sid: item}} → {project_slug: {chat_sid: [change_item]}}
function build_two_level_changes(stream_delta) {
    const change_map = {};

    const added_map = stream_delta.added || {};
    const updated_map = stream_delta.updated || {};
    const deleted_map = stream_delta.deleted || {};

    const project_slugs = [];

    const project_slug_sources = [Object.keys(added_map), Object.keys(updated_map), Object.keys(deleted_map)];

    for (let source_index = 0; source_index < project_slug_sources.length; source_index++) {
        const slug_list = project_slug_sources[source_index];

        for (let slug_index = 0; slug_index < slug_list.length; slug_index++) {
            const project_slug = slug_list[slug_index];

            // 判定：已收錄 → 跳過
            if (project_slugs.includes(project_slug) === true) {
                continue;
            }

            project_slugs.push(project_slug);
        }
    }

    for (let slug_index = 0; slug_index < project_slugs.length; slug_index++) {
        const project_slug = project_slugs[slug_index];

        const added_chats = added_map[project_slug] || {};
        const updated_chats = updated_map[project_slug] || {};
        const deleted_chats = deleted_map[project_slug] || {};

        const chat_sids = [];

        const chat_sid_sources = [Object.keys(added_chats), Object.keys(updated_chats), Object.keys(deleted_chats)];

        for (let source_index = 0; source_index < chat_sid_sources.length; source_index++) {
            const sid_list = chat_sid_sources[source_index];

            for (let sid_index = 0; sid_index < sid_list.length; sid_index++) {
                const chat_sid = sid_list[sid_index];

                // 判定：已收錄 → 跳過
                if (chat_sids.includes(chat_sid) === true) {
                    continue;
                }

                chat_sids.push(chat_sid);
            }
        }

        for (let sid_index = 0; sid_index < chat_sids.length; sid_index++) {
            const chat_sid = chat_sids[sid_index];

            const change_items = [];

            // 判定：新增 → added
            if (added_chats[chat_sid] !== undefined) {
                change_items.push(build_change_item("added", null, added_chats[chat_sid]));
            }

            // 判定：更新 → modified
            if (updated_chats[chat_sid] !== undefined) {
                change_items.push(build_change_item("modified", updated_chats[chat_sid].old, updated_chats[chat_sid].new));
            }

            // 判定：刪除 → deleted
            if (deleted_chats[chat_sid] !== undefined) {
                change_items.push(build_change_item("deleted", deleted_chats[chat_sid], null));
            }

            change_map[project_slug] = change_map[project_slug] || {};
            change_map[project_slug][chat_sid] = change_items;
        }
    }

    return change_map;
}

// 一層版（project／git／conn）：{key: item} → {key: [change_item]}
function build_one_level_changes(stream_delta) {
    const change_map = {};

    const added_map = stream_delta.added || {};
    const updated_map = stream_delta.updated || {};
    const deleted_map = stream_delta.deleted || {};

    const entry_keys = [];

    const entry_key_sources = [Object.keys(added_map), Object.keys(updated_map), Object.keys(deleted_map)];

    for (let source_index = 0; source_index < entry_key_sources.length; source_index++) {
        const key_list = entry_key_sources[source_index];

        for (let key_index = 0; key_index < key_list.length; key_index++) {
            const entry_key = key_list[key_index];

            // 判定：已收錄 → 跳過
            if (entry_keys.includes(entry_key) === true) {
                continue;
            }

            entry_keys.push(entry_key);
        }
    }

    for (let key_index = 0; key_index < entry_keys.length; key_index++) {
        const entry_key = entry_keys[key_index];

        const change_items = [];

        // 判定：新增 → added
        if (added_map[entry_key] !== undefined) {
            change_items.push(build_change_item("added", null, added_map[entry_key]));
        }

        // 判定：更新 → modified
        if (updated_map[entry_key] !== undefined) {
            change_items.push(build_change_item("modified", updated_map[entry_key].old, updated_map[entry_key].new));
        }

        // 判定：刪除 → deleted
        if (deleted_map[entry_key] !== undefined) {
            change_items.push(build_change_item("deleted", deleted_map[entry_key], null));
        }

        change_map[entry_key] = change_items;
    }

    return change_map;
}

// 對外：depth＝2（chat 兩層）／1（其他 target 一層）
export function build_stream_change_items(stream_delta, depth) {
    // 判定：兩層 → chat
    if (depth === 2) {
        return build_two_level_changes(stream_delta);
    }

    return build_one_level_changes(stream_delta);
}

// polling 專用：由 delta 重建完整 snapshot（{project:{}, chat:{}}）
export function build_stream_polling_snapshot(stream_delta) {
    const polling_snapshot = { project: {}, chat: {} };

    const added_map = stream_delta.added || {};
    const updated_map = stream_delta.updated || {};

    const polling_keys = ["project", "chat"];

    for (let key_index = 0; key_index < polling_keys.length; key_index++) {
        const polling_key = polling_keys[key_index];

        // 判定：新增 → 用新值
        if (added_map[polling_key] !== undefined) {
            polling_snapshot[polling_key] = added_map[polling_key];
            continue;
        }

        // 判定：更新 → 用新值
        if (updated_map[polling_key] !== undefined) {
            polling_snapshot[polling_key] = updated_map[polling_key].new;
        }
    }

    return polling_snapshot;
}
