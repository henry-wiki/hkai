// jsmods/sc_stream/sc_stream.js —— 直連 bridge 嘅連線總管（中心計數＋上限閘門）
// 職責：presence／stream 等 SSE 開之前向呢度登記，關閉時除名；BroadcastChannel 同步各 tab
// 上游：presence/presence.js、message/message.js（開／關連線時呼叫）
// 下游：sc_alert（滿額警告）、sc_i18n（警告文字）
// 設計：總數 = 本頁連線 + 其他 tab 報上嚟嘅數（過期 30 秒自動除名）

// 瀏覽器每個 origin 嘅並行連線上限（HTTP/1.1——Chrome／Edge／WebView2 都係 6）
const MAX_CONNECTIONS_PER_ORIGIN = 6;

// 各 tab 互相報數嘅間隔（毫秒）
const SYNC_INTERVAL_MS = 10000;

// 其他 tab 嘅數目幾耐冇更新當過期（毫秒——頁面已關）
const TAB_COUNT_STALE_MS = 30000;

class _sc_stream {
    constructor() {
        // 本頁登記表（connection_id → kind）
        this.connections = {};
        this.connection_seq = 0;

        // 跨 tab 同步
        this.tab_id = "tab_" + Math.random().toString(36).slice(2);
        this.tab_counts = {};
        this.tab_channel = null;
        this.sync_timer = null;

        this.warned_full = false;
    }

    // bootstrap 自動呼叫：開跨 tab 同步＋presence（只做一次）
    async init() {
        this.watch_tab_counts();

        const stream_config = this.get_config();

        // 判定：已啟用且有 base_url → 開 presence
        if (stream_config.enabled === true && stream_config.base_url) {
            await registry().sc_presence().ensure_open(stream_config.base_url);
        }

        return this;
    }

    // 登記一條連線（回 connection_id；滿額回 null 並彈警告）
    open(kind) {
        const used_count = this.total_count();

        // 判定：已達瀏覽器上限 → 唔開，彈警告
        if (used_count >= MAX_CONNECTIONS_PER_ORIGIN) {
            this.warn_full(used_count);

            return null;
        }

        this.connection_seq = this.connection_seq + 1;

        const connection_id = "connection_" + this.connection_seq;

        this.connections[connection_id] = kind;

        this.broadcast_count();

        return connection_id;
    }

    // 除名（連線已關）
    close(connection_id) {
        // 判定：唔係本頁登記嘅 id → 唔理
        if (!(connection_id in this.connections)) {
            return;
        }

        delete this.connections[connection_id];

        this.broadcast_count();
    }

    // 開狀態線（唯一入口——開頁／換對話都經呢度；身份由當前頁算）
    async open_state_stream() {
        const project_instance = await registry().sc_ai_projects().project();
        const project_slug = project_instance.get_project_slug();

        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_sid = chat_instance.get_chat_sid();

        const view_meta = document.querySelector("meta[name='view-id']");
        const view_id = (view_meta === null) ? "" : view_meta.getAttribute("content");

        const state_stream_module = await import("./state/state_stream.js");

        await state_stream_module.state_stream().open(project_slug, chat_sid, view_id);
    }

    // 讀頁面 stream 設定（meta stream-config——PHP 由 hkai_config.json 輸出）
    get_config() {
        const meta_element = document.querySelector("meta[name='stream-config']");

        // 判定：冇 meta → 未啟用
        if (meta_element === null) {
            return { enabled: false };
        }

        try {
            return JSON.parse(meta_element.getAttribute("content") || "{}");
        } catch (parse_error) {
            return { enabled: false };
        }
    }

    // bridge 位址（未啟用／未設定 → 空字串）
    get_base_url() {
        const stream_config = this.get_config();

        // 判定：未啟用串流 → 空
        if (stream_config.enabled !== true) {
            return "";
        }

        // 判定：冇 base_url → 空
        if (!stream_config.base_url) {
            return "";
        }

        return stream_config.base_url;
    }

    // 瀏覽器上限（俾警告文字用）
    max_connections() {
        return MAX_CONNECTIONS_PER_ORIGIN;
    }

    // 本頁連線數
    local_count() {
        return Object.keys(this.connections).length;
    }

    // 總連線數（本頁 + 其他 tab；先清過期 tab）
    total_count() {
        this.prune_tab_counts();

        let total_count = this.local_count();

        const tab_ids = Object.keys(this.tab_counts);

        for (const tab_id of tab_ids) {
            total_count = total_count + this.tab_counts[tab_id].count;
        }

        return total_count;
    }

    // 清走過期 tab（頁面已關、冇再報數）
    prune_tab_counts() {
        const now_ms = Date.now();

        const tab_ids = Object.keys(this.tab_counts);

        for (const tab_id of tab_ids) {
            const tab_count = this.tab_counts[tab_id];

            // 判定：超過過期時限 → 除名
            if (now_ms - tab_count.at > TAB_COUNT_STALE_MS) {
                delete this.tab_counts[tab_id];
            }
        }
    }

    // 開跨 tab 同步（hello 交朋友 + 定時報數）
    watch_tab_counts() {
        // 判定：瀏覽器唔支援 → 只計本頁
        if (typeof BroadcastChannel === "undefined") {
            return;
        }

        // 判定：已經開咗 → 唔重複
        if (this.tab_channel !== null) {
            return;
        }

        const thisObject = this;

        this.tab_channel = new BroadcastChannel("hkai_sc_stream");

        this.tab_channel.onmessage = function (message_event) {
            thisObject.receive_tab_message(message_event.data);
        };

        this.tab_channel.postMessage({ type: "hello", tab_id: this.tab_id });

        // 定時報數（其他 tab 靠呢個更新／延長有效期）
        this.sync_timer = window.setInterval(function () {
            thisObject.broadcast_count();
        }, SYNC_INTERVAL_MS);
    }

    // 收其他 tab 嘅訊息（hello → 回報自己；count → 記錄）
    receive_tab_message(message) {
        // 判定：冇內容／係自己發出 → 唔理
        if (message === null || message === undefined) {
            return;
        }

        // 判定：自己嘅訊息 → 唔理
        if (message.tab_id === this.tab_id) {
            return;
        }

        // 判定：新 tab 加入 → 回報自己數目
        if (message.type === "hello") {
            this.broadcast_count();

            return;
        }

        // 判定：數目更新 → 記錄
        if (message.type === "count") {
            this.tab_counts[message.tab_id] = {
                count: message.count,
                at: Date.now(),
            };
        }
    }

    // 報自己嘅連線數俾其他 tab
    broadcast_count() {
        // 判定：冇 channel → 只計本頁
        if (this.tab_channel === null) {
            return;
        }

        this.tab_channel.postMessage({
            type: "count",
            tab_id: this.tab_id,
            count: this.local_count(),
        });
    }

    // 滿額警告（同一頁只彈一次）
    warn_full(used_count) {
        // 判定：已警告過 → 唔重複
        if (this.warned_full === true) {
            return;
        }

        this.warned_full = true;

        const message_title = registry().sc_i18n().get("stream.full_title");

        const message = this.fill_message("stream.full_message", {
            used_count: used_count,
            max_connections: MAX_CONNECTIONS_PER_ORIGIN,
        });

        registry().shared().sc_alert().warning(message_title, message);
    }

    // 取 i18n 訊息並替換 [[key]] 佔位符（replacements＝{ key: value }）
    fill_message(key, replacements) {
        let message = registry().sc_i18n().get(key);

        const placeholder_keys = Object.keys(replacements);

        for (const placeholder_key of placeholder_keys) {
            const placeholder = "[[" + placeholder_key + "]]";
            message = message.split(placeholder).join(String(replacements[placeholder_key]));
        }

        return message;
    }
}

export const sc_stream = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_stream());
