// jsmods/sc_stream/presence/presence.js —— 頁面在線（presence）單例（app 層——跨 plugin）
// 職責：每個瀏覽器頁面開一條 SSE 直連 bridge（GET /presence）——bridge 以連線數判斷「仲有冇人」
// 連線額滿：先向 sc_stream 登記（總數含其他 tab 嘅 presence／stream）；滿額 → 3 秒後再試
// 斷線處理：一律 3 秒後重連（6 條上限由 sc_stream 本地判斷，唔查 server）
// 上游：bootstrap.boot() 呼叫 init()
// 下游：bridge server_presence.py（GET /presence）、jsmods/sc_stream/sc_stream.js（登記／除名）

// SSE 連線建立超時（毫秒）——超過即當排喺瀏覽器連線上限後面
const PRESENCE_CONNECT_TIMEOUT_MS = 6000;

// 斷線後重連間隔（毫秒）
const PRESENCE_RETRY_DELAY_MS = 3000;

class _sc_presence {
    constructor() {
        this.event_source = null;
        this.retry_timer = null;

        // 瀏覽器連線上限警告（per origin）
        this.warned_browser_limit = false;
        this.connect_timer = null;

        // sc_stream 登記編號（關閉時除名）
        this.connection_id = null;

        // 連線狀態監聽（外部註冊——狀態有變才通知："connected"／"disconnected"）
        this.status_listeners = [];
        this.is_connected = null;

        // 自動重連（主動 stop 後＝false——改由外部 status 輪詢接手）
        this.auto_reconnect = true;
    }

    // 啟動（重複呼叫只行一次）；向 sc_stream 取 bridge 位址
    init() {
        // 判定：已經開緊 → 直回
        if (this.event_source !== null) {
            return this;
        }

        const bridge_base_url = registry().sc_stream().get_base_url();

        // 判定：冇 bridge 位址（未啟動／未啟用）→ 唔開
        if (bridge_base_url === "") {
            return this;
        }

        const stream_manager = registry().sc_stream();

        this.auto_reconnect = true;

        this.connection_id = stream_manager.open("presence");

        // 判定：連線額滿（其他 tab／stream 用晒）→ 3 秒後再試
        if (this.connection_id === null) {
            this.schedule_reconnect();

            return this;
        }

        const thisObject = this;

        this.event_source = new EventSource(bridge_base_url + "/presence");

        // 連線 6 秒內未 onopen → 可能排喺連線上限後面（靜默塞車）
        this.connect_timer = window.setTimeout(function () {
            thisObject.connect_timer = null;
            thisObject.check_connection_opened();
        }, PRESENCE_CONNECT_TIMEOUT_MS);

        this.event_source.onopen = function () {
            thisObject.clear_connect_timer();
            thisObject.set_connected(true);
        };

        // 斷線（bridge 未起／被殺／重啟）→ 通知監聽者；仍允許重連才排 3 秒後再試
        this.event_source.onerror = function () {
            thisObject.clear_connect_timer();
            thisObject.set_connected(false);

            // 判定：監聽者已叫停（auto_reconnect=false）→ 唔重連
            if (thisObject.auto_reconnect === true) {
                thisObject.schedule_reconnect();
            }
        };

        return this;
    }

    // 註冊連線狀態監聽（listener 收 "connected"／"disconnected"）
    register_status_listener(status_listener) {
        this.status_listeners.push(status_listener);
    }

    // 更新連線狀態（有變才通知——避免每次重連失敗都通知）
    set_connected(is_connected) {
        // 判定：狀態冇變 → 唔通知
        if (this.is_connected === is_connected) {
            return;
        }

        this.is_connected = is_connected;

        const status_text = is_connected ? "connected" : "disconnected";

        for (const status_listener of this.status_listeners) {
            status_listener(status_text);
        }
    }

    // 清連線逾時計時器
    clear_connect_timer() {
        // 判定：有計時器 → 清
        if (this.connect_timer !== null) {
            window.clearTimeout(this.connect_timer);
            this.connect_timer = null;
        }
    }

    // 連線逾時未 open → 警告（多數係瀏覽器連線上限排隊）
    check_connection_opened() {
        // 判定：已經連上 → 冇事
        if (this.event_source === null) {
            return;
        }

        // 判定：readyState 已經 OPEN → 冇事
        if (this.event_source.readyState === EventSource.OPEN) {
            return;
        }

        // 判定：同一頁只警告一次
        if (this.warned_browser_limit === true) {
            return;
        }

        this.warned_browser_limit = true;

        const message_title = registry().sc_i18n().get("presence.connect_timeout_title");

        const message = registry().sc_stream().fill_message("presence.connect_timeout_message", {
            max_connections: registry().sc_stream().max_connections(),
        });

        registry().shared().sc_alert().warning(message_title, message);
    }

    // 主動停止（斷線後交返外部 status 輪詢——唔自動重連）
    stop() {
        this.auto_reconnect = false;
        this.close();
    }

    // 排程重連（先關舊連線，3 秒後重新讀 meta 再開）
    schedule_reconnect() {
        this.close();

        const thisObject = this;

        this.retry_timer = window.setTimeout(function () {
            thisObject.retry_timer = null;
            thisObject.init();
        }, PRESENCE_RETRY_DELAY_MS);
    }

    // 關閉連線（清計時器＋向 sc_stream 除名）
    close() {
        // 判定：有重連計時器 → 清
        if (this.retry_timer !== null) {
            window.clearTimeout(this.retry_timer);
            this.retry_timer = null;
        }

        // 判定：有連線 → 關
        if (this.event_source !== null) {
            this.event_source.close();
            this.event_source = null;
        }

        // 判定：有登記 → 除名
        if (this.connection_id !== null) {
            registry().sc_stream().close(this.connection_id);
            this.connection_id = null;
        }

        // 關閉等同斷線（有變才通知）
        this.set_connected(false);
    }
}

export const sc_presence = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_presence());
