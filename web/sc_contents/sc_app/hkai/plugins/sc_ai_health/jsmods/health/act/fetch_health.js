// jsmods/health/act/fetch_health.js —— health 抓取引擎（action 層——一 action 一檔）
// 職責：維持心跳——init（初始化——唔啟動）／start（開始抓取）／stop（停止＋中止進行中請求）／restart（立即重跳）／check_health（一輪：請求→套結果→排下一輪）
// 備註：啟動決定權喺 root（sc_ai_health）——顯示中先開、切走即停（root 現場判斷）；發送新訊息由 prompt_sent hook 反應叫 restart()
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _fetch_health {
    constructor() {
        this.interval = 9000;       // 每輪間隔（毫秒）
        this.timeout = 15000;       // 單輪逾時（毫秒——到時自動中止）

        this.running = false;
        this.running_timer_id = null;
        this.abort_controller = null;

        // session 狀態（重整頁面歸零）
        this.health_count = 0;          // 每輪 +1（loop／restart／任何呼叫者）
    }

    // 初始化（對外——root（sc_ai_health）呼叫；唔啟動——啟動由 root 顯式叫 start()）
    init() {
        return this;
    }

    // 停止（對外——清排程＋中止進行中請求；停止後唔排下一輪）
    stop() {
        const thisObject = this;

        // 清尚未到點嘅下一輪排程
        if (thisObject.running_timer_id) {
            clearTimeout(thisObject.running_timer_id);
        }

        // 中止進行中的請求（停止後不殘留——同步中止，唔等動態載入）
        if (thisObject.abort_controller) {
            thisObject.abort_controller.abort();
        }

        thisObject.running = false;
    }

    // 重啟抓取（對外——停 → 立即重跳；發送新訊息等事件後喚醒偵測新任務）
    restart() {
        const thisObject = this;

        thisObject.stop();

        return thisObject.start();
    }

    // 開始抓取（root 顯式啟動／restart 用——先停再起；每輪結束自動排下一輪）
    start() {
        const thisObject = this;

        thisObject.stop();

        thisObject.running = true;

        thisObject.check_health();

        return thisObject;
    }

    // 一輪 health（組請求資料 → 交 handler 連線 → 套結果 → 排下一輪）
    async check_health() {
        const thisObject = this;

        // session 計數（無論 loop／restart／任何呼叫者——每輪 +1）
        thisObject.health_count = thisObject.health_count + 1;

        // 派發 health_updated hook（before 相位——本輪請求前；反應登記喺用家側）
        registry().sc_ai_health().execute_hook_before("health_updated", []);

        // 本輪中止接口（stop() 即時中止用）＋逾時自動中止
        const abort_controller = new AbortController();
        thisObject.abort_controller = abort_controller;

        const abort_timer_id = setTimeout(function () {
            abort_controller.abort();
        }, thisObject.timeout);

        // 請求資料（count 由引擎提供；time／page_context 由 handler 組；signal＝中止接口）
        const request_data = {};
        request_data["count"] = thisObject.health_count;
        request_data["request_signal"] = abort_controller.signal;

        // 橋接駁（同域 req 橋——新方式）
        const health_instance = await registry().sc_ai_health().health();
        const req_fetch_health_instance = await health_instance.req().fetch_health();

        const health_result = await req_fetch_health_instance.fetch_health(request_data);

        clearTimeout(abort_timer_id);

        // 本輪結果處理（失敗＝null 靜默跳過；處理異常＝靜默——本輪結束，下一輪照排）
        try {
            if (health_result !== null) {
                thisObject.apply_health_result(health_result);
            }
        } catch (apply_error) {
            // 心跳維持不阻斷（單輪處理異常不中斷輪詢）
        }

        // 等本輪完成才排下一輪（running 才續；is_enabled=false → stop() 或明確 stop() 後不排）
        if (thisObject.running) {
            thisObject.running_timer_id = setTimeout(function () {
                thisObject.check_health();
            }, thisObject.interval);
        }
    }

    // 套用一輪結果（派發 health_updated hook——反應登記喺用家側）
    apply_health_result(health_result) {
        registry().sc_ai_health().execute_hook_after("health_updated", health_result);
    }
}

export const fetch_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _fetch_health());
