// jsmods/message/act/message_stream.js —— SSE 加速器（後端有新內容即觸發一次 poll）
// 職責：message_stream（單例）——get_config()（讀 meta stream-config）／start(project_slug, message_id, on_change)／stop()／fire_change()
// 設計：SSE 只做「有更新」訊號，唔直接改 DOM（渲染永遠由 message_poll 唯一負責——避免同 writer／markdown 打架）
//       bridge 死咗／唔 enabled → 完全唔開 EventSource，純 poll 照舊；poll 永遠係 fallback

class _message_stream {
    constructor() {
        this.event_source = null;
        this.message_id = null;
        this.on_change = null;
        this.throttle_timer_id = null;
        this.throttle_ms = 400;
    }

    // 框架 stub（域 init 召集時呼叫；冇狀態要初始化）
    init() {
        return this;
    }

    // 讀頁面設定（PHP 由 hkai_config.json stream 段輸出——enabled／base_url）
    get_config() {
        const meta_element = document.querySelector("meta[name='stream-config']");

        // 判定：頁面冇設定 meta → 當停用
        if (!meta_element) {
            return { enabled: false };
        }

        try {
            return JSON.parse(meta_element.getAttribute("content") || "{}");
        } catch (parse_error) {
            return { enabled: false };
        }
    }

    // 啟動（同一訊息已連 → 唔重開）
    start(project_slug, message_id, on_change) {
        const stream_config = this.get_config();

        // 判定：未啟用 → 唔開（純 poll）
        if (!stream_config.enabled) {
            return false;
        }

        // 判定：同一條訊息已經連緊 → 只換回呼
        if (this.event_source && this.message_id === message_id) {
            this.on_change = on_change;
            return true;
        }

        this.stop();

        this.message_id = message_id;
        this.on_change = on_change;

        const stream_url = stream_config.base_url
            + "/stream?project_slug=" + encodeURIComponent(project_slug)
            + "&message_id=" + encodeURIComponent(message_id)
            + "&response_index=0";

        this.event_source = new EventSource(stream_url);

        const thisObject = this;

        // 有更新（delta／status／reset）→ 觸發一次 poll
        const change_events = ["delta", "status", "reset"];
        for (let event_index = 0; event_index < change_events.length; event_index++) {
            this.event_source.addEventListener(change_events[event_index], function () {
                thisObject.fire_change();
            });
        }

        // 終態 → 即刻觸發最後一次 poll（收尾渲染）再關連線
        this.event_source.addEventListener("done", function () {
            const on_change = thisObject.on_change;

            thisObject.stop();

            // 判定：有回呼 → 叫最後一次
            if (on_change) {
                on_change();
            }
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
        this.message_id = null;
        this.on_change = null;
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
