// jsmods/_obsolete/check_health.js —— 舊心跳迴圈（已停用——保留作參考，零 caller）
// 狀態：obsolete。SSE（health_stream）取代本輪詢；檔案保留以便日後查閱或恢復。
// 恢復方法：於 act_fetch_health.start() 內呼叫
//   (await import("../_obsolete/check_health.js")).check_health().check_health(引擎實例)
// 依賴：req_fetch_health.js（同目錄——由 health/req/ 搬入）
// 依賴：health 引擎實例須提供 running／health_count／timeout／interval／abort_controller／check_in_flight

class _obsolete_check_health {
    constructor() {}

    // 一輪 health（組請求資料 → 交 handler 連線 → 套結果 → 排下一輪）
    async check_health(health_engine) {
        const thisObject = health_engine;

        thisObject.check_in_flight = true;

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

        const req_fetch_health_module = await import("./req_fetch_health.js");
        const req_fetch_health_instance = req_fetch_health_module.req_fetch_health();

        const health_result = await req_fetch_health_instance.req_fetch_health(request_data);

        clearTimeout(abort_timer_id);

        thisObject.check_in_flight = false;

        // 本輪結果處理（失敗＝null 靜默跳過；處理異常＝靜默——本輪結束，下一輪照排）
        try {
            if (health_result !== null) {
                this.apply_health_result(health_result);
            }
        } catch (apply_error) {
            // 心跳維持不阻斷（單輪處理異常不中斷輪詢）
        }

        // 等本輪完成才排下一輪（running 才續）
        if (thisObject.running) {
            thisObject.running_timer_id = setTimeout(function () {
                (async function () {
                    await thisObject.check_health(thisObject);
                })();
            }, thisObject.interval);
        }
    }

    // 套用一輪結果（派發 health_updated hook——反應登記喺用家側）
    apply_health_result(health_result) {
        registry().sc_ai_health().execute_hook_after("health_updated", health_result);
    }
}

export const check_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _obsolete_check_health());
