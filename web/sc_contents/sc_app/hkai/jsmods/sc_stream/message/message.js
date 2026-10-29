// jsmods/sc_stream/message/message.js —— SSE 加速器（後端有新內容即觸發一次 poll）
// 職責：message_stream（單例）——start(stream_url, change_event_names, on_change)／stop()／fire_change()
// 設計：只收「現成 SSE URL＋事件名＋回呼」——唔知 project_slug／message_id，URL 由 caller 砌
// 設計：SSE 只做「有更新」訊號，唔直接改 DOM（渲染永遠由 message_poll 唯一負責——避免同 writer／markdown 打架）
//       bridge 死咗／唔 enabled → 完全唔開 EventSource，純 poll 照舊；poll 永遠係 fallback
//       開／關連線要向 sc_stream 登記（同 presence 一齊計瀏覽器 6 條上限）

class _message_stream {
    constructor() {
        this.event_source = null;
        this.stream_url = null;
        this.on_change = null;
        this.throttle_timer_id = null;
        this.throttle_ms = 400;

        // 邊啲事件叫 on_change（由 caller 傳入——域知識唔寫死喺本模組）
        this.change_event_names = [];

        // sc_stream 登記編號（關閉時除名）
        this.connection_id = null;
    }

    // 框架 stub（域 init 召集時呼叫；冇狀態要初始化）
    init() {
        return this;
    }

    // 啟動（同一條 URL 已連 → 唔重開）；事件名同回呼由 caller 傳入
    start(stream_url, change_event_names, on_change) {
        // 判定：冇 URL（未啟用／未設定）→ 唔開（純 poll）
        if (stream_url === "") {
            return false;
        }

        // 判定：同一條連線已經開緊 → 只換回呼
        if (this.event_source && this.stream_url === stream_url) {
            this.change_event_names = change_event_names;
            this.on_change = on_change;
            return true;
        }

        this.stop();

        this.connection_id = registry().sc_stream().open("stream");

        // 判定：連線額滿（其他 tab／presence 用晒）→ 唔開，純 poll
        if (this.connection_id === null) {
            return false;
        }

        this.stream_url = stream_url;
        this.change_event_names = change_event_names;
        this.on_change = on_change;

        this.event_source = new EventSource(stream_url);

        const thisObject = this;

        // 有更新（事件名由 caller 傳入）→ 觸發一次 poll
        for (let event_index = 0; event_index < change_event_names.length; event_index++) {
            this.event_source.addEventListener(change_event_names[event_index], function () {
                thisObject.fire_change();
            });
        }

        // 終態（done）→ 只關連線；唔叫 poll（完成後再叫＝無限重啟 tasks: []）
        this.event_source.addEventListener("done", function () {
            thisObject.stop();
        });

        // 錯誤（bridge 死／403）→ 關掉，純 poll 接手
        this.event_source.addEventListener("error", function () {
            thisObject.stop();
        });

        return true;
    }

    // 停止（清 EventSource 同節流計時器）
    stop() {
        // 判定：有連線 → 關
        if (this.event_source) {
            this.event_source.close();
        }

        // 判定：有節流計時器 → 清
        if (this.throttle_timer_id) {
            clearTimeout(this.throttle_timer_id);
        }

        this.event_source = null;
        this.throttle_timer_id = null;
        this.stream_url = null;
        this.change_event_names = [];
        this.on_change = null;

        // 判定：有登記 → 除名
        if (this.connection_id !== null) {
            registry().sc_stream().close(this.connection_id);
            this.connection_id = null;
        }
    }

    // 觸發（節流——最多每 throttle_ms 一次）
    fire_change() {
        const thisObject = this;

        // 判定：已有排程 → 唔重複排
        if (this.throttle_timer_id) {
            return;
        }

        this.throttle_timer_id = setTimeout(function () {
            thisObject.throttle_timer_id = null;

            const on_change = thisObject.on_change;

            // 判定：有回呼 → 叫一次 poll
            if (on_change) {
                on_change();
            }
        }, this.throttle_ms);
    }
}

export const message_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_stream());
