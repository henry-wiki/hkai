// jsmods/sc_stream/workspace/workspace_observed.js —— UI 現況標準收集器（ACK 回報用）
// 職責：按 event 名收集畫面現況，統一形狀 {items:{id:{field:value}}}；只讀 DOM，唔改任何東西
//   欄位一律取「畫面原值」（attr／data-*）——同後台文件比對時唔會有格式差異
// 上游：workspace_stream.js collect_observed()（四個 snapshot observer 回 ACK 前呼叫）
// 下游：無
// 結構：eager 單例 closure factory（動態 import 用——零 .default）

class _workspace_observed {
    constructor() {}

    // 收集指定 event 嘅畫面現況
    collect(event_name) {
        // 判定：chat 事件 → 對話卡現況
        if (event_name === "chat") {
            return this.collect_chat();
        }

        // 判定：project 事件 → 專案卡現況
        if (event_name === "project") {
            return this.collect_project();
        }

        // 判定：message 事件 → 訊息卡現況
        if (event_name === "message") {
            return this.collect_message();
        }

        // 判定：polling 事件 → 標記現況（有／冇 job）
        if (event_name === "polling") {
            return this.collect_polling();
        }

        return { items: {} };
    }

    // 卡片身份（歸檔卡帶 archive@<slug|sid> 前綴——後台文件用純 id，要剝）
    strip_context(identity_text) {
        const raw_text = String(identity_text || "");

        // 判定：冇前綴 → 原值
        if (raw_text.indexOf("@") === -1) {
            return raw_text;
        }

        return raw_text.slice(raw_text.indexOf("@") + 1);
    }

    // 對話卡現況（{chat_sid: {updated_at, msg_count}}）
    collect_chat() {
        const item_map = {};
        const card_nodes = document.querySelectorAll(".sc_chat_list .sc_chat_card");

        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];
            const chat_sid = this.strip_context(card_node.getAttribute("data-chat_sid"));

            // 判定：冇身份 → 跳過
            if (chat_sid === "") {
                continue;
            }

            const entry = {};
            entry.updated_at = String(card_node.getAttribute("data-update_time") || "");

            const msg_count_node = card_node.querySelector(".sc_chat_card__msg_count");
            entry.msg_count = msg_count_node === null ? "0" : String(msg_count_node.getAttribute("data-count") || "0");

            item_map[chat_sid] = entry;
        }

        return { items: item_map };
    }

    // 專案卡現況（{project_slug: {updated_at}}）
    collect_project() {
        const item_map = {};
        const card_nodes = document.querySelectorAll(".sc_project_card");

        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];
            const project_slug = this.strip_context(card_node.getAttribute("data-project_slug"));

            // 判定：冇身份 → 跳過
            if (project_slug === "") {
                continue;
            }

            const entry = {};
            entry.updated_at = String(card_node.getAttribute("data-update_time") || "");

            item_map[project_slug] = entry;
        }

        return { items: item_map };
    }

    // 訊息卡現況（{message_id: {status}}；樂觀泡泡唔算——未確認嘅唔應該參與比對）
    collect_message() {
        const item_map = {};
        const card_nodes = document.querySelectorAll(".sc_thread__viewport .message_wrapper");

        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];

            // 判定：樂觀泡泡 → 跳過
            if (String(card_node.getAttribute("data-optimistic") || "") !== "") {
                continue;
            }

            const message_id = String(card_node.getAttribute("data-message_id") || "");

            // 判定：冇身份 → 跳過
            if (message_id === "") {
                continue;
            }

            const entry = {};
            entry.status = String(card_node.getAttribute("data-status") || "");

            item_map[message_id] = entry;
        }

        return { items: item_map };
    }

    // polling 現況（有標記即有 job；{projects:{slug:1}, chats:{sid:1}}）
    collect_polling() {
        const project_map = {};
        const chat_map = {};

        const project_nodes = document.querySelectorAll(".sc_project_card.polling");

        for (let project_index = 0; project_index < project_nodes.length; project_index++) {
            const project_slug = this.strip_context(project_nodes[project_index].getAttribute("data-project_slug"));

            // 判定：有身份 → 記
            if (project_slug !== "") {
                project_map[project_slug] = 1;
            }
        }

        const chat_nodes = document.querySelectorAll(".sc_chat_card.polling");

        for (let chat_index = 0; chat_index < chat_nodes.length; chat_index++) {
            const chat_sid = this.strip_context(chat_nodes[chat_index].getAttribute("data-chat_sid"));

            // 判定：有身份 → 記
            if (chat_sid !== "") {
                chat_map[chat_sid] = 1;
            }
        }

        const result = {};
        result.projects = project_map;
        result.chats = chat_map;

        return { items: result };
    }
}

export const workspace_observed = (function (instance) {
    return function () {

        return instance;
    };
})(new _workspace_observed());
