// jsmods/sc_stream/message/message.js —— SSE 連線（單例）
// 職責：start(stream_url, event_names, on_event)——開線；每個事件原樣交 (name, payload) 俾 caller
//       stop()——關線（caller 決定；done 唔自動關——對話級線要長開）
// 設計：唔知 project_slug／message_id（URL 由 caller 砌）；唔改 DOM（渲染交 caller）

class _message_stream {
    constructor() {
        this.event_source = null;
        this.stream_url = null;
        this.on_event = null;

        // 邊啲事件要交俾 caller（由 caller 傳入）
        this.event_names = [];

        // sc_stream 登記編號（關閉時除名）
        this.connection_id = null;
    }

    // 框架 stub（域 init 召集時呼叫；冇狀態要初始化）
    init() {
        return this;
    }

    // 啟動（同一條 URL 已連 → 只換回呼）
    start(stream_url, event_names, on_event) {
        sc_console("stream", "message").log("start 敲門", stream_url);

        // 判定：冇 URL（未啟用／未設定）→ 唔開
        if (stream_url === "") {
            sc_console("stream", "message").log("無 URL → 唔開");

            return false;
        }

        // 判定：同一條連線已經開緊 → 只換回呼
        if (this.event_source && this.stream_url === stream_url) {
            sc_console("stream", "message").log("同 URL 已開 → 唔重開，只換 callback");

            this.event_names = event_names;
            this.on_event = on_event;

            return true;
        }

        this.stop();

        sc_console("stream", "message").log("開新連線", stream_url);

        this.connection_id = registry().sc_stream().open("stream");

        // 判定：連線額滿（其他 tab／presence 用晒）→ 唔開
        if (this.connection_id === null) {
            sc_console("stream", "message").log("連線額滿 → 唔開");

            return false;
        }

        this.stream_url = stream_url;
        this.event_names = event_names;
        this.on_event = on_event;

        this.event_source = new EventSource(stream_url);

        const thisObject = this;

        // 逐個事件掛監聽：原樣交 name＋payload
        for (let event_index = 0; event_index < event_names.length; event_index++) {
            thisObject._listen(event_names[event_index]);
        }

        // 錯誤（bridge 死／403）→ 關線
        this.event_source.addEventListener("error", function () {
            thisObject.stop();
        });

        return true;
    }

    // 單一事件監聽（parse JSON → 交 caller；done 之後關線）
    _listen(event_name) {
        const thisObject = this;

        this.event_source.addEventListener(event_name, function (event_message) {
            let event_payload = {};

            try {
                event_payload = JSON.parse(event_message.data || "{}");
            } catch (parse_error) {
                sc_console("stream", "message").log("payload 解析失敗", event_name, parse_error.message);

                return;
            }

            sc_console("stream", "message").log("收到", event_name, event_payload);

            // 判定：有回呼 → 交出去
            if (thisObject.on_event !== null) {
                thisObject.on_event(event_name, event_payload);
            }

            // done 唔關線（對話級線——該條訊息完結，線繼續等下條）
        });
    }

    // 線路仲開住？（readyState OPEN——唔靠「有冇收到幀」判斷）
    is_open() {
        // 判定：冇線 → 未開
        if (this.event_source === null) {
            return false;
        }

        return this.event_source.readyState === EventSource.OPEN;
    }

    // 停止（清 EventSource；登記除名）
    stop() {
        // 判定：有連線 → 關
        if (this.event_source) {
            this.event_source.close();
        }

        this.event_source = null;
        this.stream_url = null;
        this.event_names = [];
        this.on_event = null;

        // 判定：有登記 → 除名
        if (this.connection_id !== null) {
            registry().sc_stream().close(this.connection_id);
            this.connection_id = null;
        }
    }
}

export const message_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_stream());
