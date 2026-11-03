// jsmods/sc_state_watch/contract/contract.js —— 狀態監視契約（唯一裝載點）
// 基礎：data/stream/<target>/snapshot.json（chat／project／polling／git／conn）
// 職責：界定每個 target 嘅單位名同欄位名
// 上游：store（寫入前核對）
// 下游：無（純定義——唔掂 DOM、唔掂記憶體）

export const STATE_WATCH_CONTRACT = {
    targets: {
        chat: {
            unit_name: "chat_sid",
            fields: ["status", "lastupdate", "msg_count"],
        },
        project: {
            unit_name: "project_slug",
            fields: ["status", "lastupdate", "chat_count"],
        },
        polling: {
            unit_name: "kind",
            units: ["project", "chat"],
            fields: [],
            dynamic_field: true,
        },
        git: {
            unit_name: "project_slug",
            fields: ["is_repo", "branch", "detached", "head_sha", "upstream", "ahead", "behind", "dirty", "lastupdate"],
        },
        conn: {
            unit_name: "conn_name",
            fields: ["is_alive", "base_url"],
        },
    },
};

// 檢查 target 存唔存在（契約內）
export function is_known_target(target) {
    // 判定：契約冇此 target → 假
    if (Object.prototype.hasOwnProperty.call(STATE_WATCH_CONTRACT.targets, target) === false) {
        return false;
    }

    return true;
}

// 檢查 unit 合法（target 有 units 清單時；冇清單＝任何非空都准）
export function is_known_unit(target, unit) {
    // 判定：target 唔存在 → 假
    if (is_known_target(target) === false) {
        return false;
    }

    const target_contract = STATE_WATCH_CONTRACT.targets[target];

    // 判定：冇 units 清單 → 任何非空 unit 都准
    if (target_contract.units === undefined) {
        return true;
    }

    return target_contract.units.indexOf(unit) >= 0;
}

// 檢查 target＋field 組合合法（契約內）
export function is_known_entry(target, field) {
    // 判定：target 唔存在 → 假
    if (is_known_target(target) === false) {
        return false;
    }

    const target_contract = STATE_WATCH_CONTRACT.targets[target];

    // 判定：動態 field（例如 polling——field 係 slug／sid）→ 非空即合法
    if (target_contract.dynamic_field === true) {
        return typeof field === "string" && field !== "";
    }

    // 判定：此 target 冇此 field → 假
    if (target_contract.fields.indexOf(field) < 0) {
        return false;
    }

    return true;
}
