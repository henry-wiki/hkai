// jsmods/bench_modal/act/modal_action.js —— model 選擇過程動作（action 層——一 action 一檔）
// 職責：modal_action（Refresh 重生——spawn 取 modal／scroll／fetch 組）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _modal_action {
    constructor() {
        this.model_data = null;
    }

    // 存 model_data（外灌——目前無呼叫者，保留骨架）
    set_model_data(model_data) {
        this.model_data = model_data;
    }

    // 重新下載＋重繪 entry models（① 後端重新下載 bench_source 檔 → ② fetch 該 entry 頁（loading——同撳左欄同一條載入路）；成敗皆回報 callbacks）
    async rebuild_bench_source_models(bench_source_id, entry, callbacks) {
        // registry 取值（shared——sc_alert 用）
        const shared = registry().shared();

        // spawn 取 fetch 組（req——redownload_bench_source_models／act——switch_bench_source_item）
        const redownload_bench_source_models_instance = await bench_modal_instance.req().redownload_bench_source_models();
        const switch_bench_source_item_instance = await bench_modal_instance.act().switch_bench_source_item();

        // ① 後端重新下載（req 層啞——alert 由呢度出；成功先續 ②）
        redownload_bench_source_models_instance.redownload_bench_source_models(bench_source_id, {
            success: function (result) {
                // 判定：重新下載成功（bench_source 層 error = 0）→ 續 fetch 該 bench_source 頁
                if (
                    result.results
                    && result.results[bench_source_id]
                    && result.results[bench_source_id].error === 0
                ) {
                    // ② fetch 該 entry 頁（loading → 灌列）→ 成敗皆回報
                    switch_bench_source_item_instance.switch_bench_source_item(bench_source_id, entry, {
                        success: function () {
                            callbacks.on_complete();
                        },
                        error: function () {
                            callbacks.on_fail();
                        },
                    });

                    return;
                }

                // reset 失敗 → sc_alert 顯示有用資料（後端 error_message）＋失敗回報
                let fail_message = "request failed";
                // 判定：有結果物件 → 入去搵本 bench_source 錯誤訊息
                if (result.results) {
                    // 判定：有本 bench_source 結果 → 取 error_message
                    if (result.results[bench_source_id]) {
                        fail_message = result.results[bench_source_id].error_message;
                    }
                }
                shared.sc_alert().error("cannot refresh models: " + bench_source_id, fail_message);
                callbacks.on_fail();
            },
            error: function (result) {
                // 網路／拒絕失敗 → sc_alert 顯示有用資料（後端 error／網路 message）＋失敗回報
                let fail_message = "request failed";
                // 判定：有結果物件 → 取後端 error／網路 message
                if (result) {
                    if (result.error) {
                        fail_message = result.error;
                    } else if (result.message) {
                        fail_message = result.message;
                    }
                }
                shared.sc_alert().error("cannot refresh models: " + bench_source_id, fail_message);
                callbacks.on_fail();
            },
        });
    }
}

export const modal_action = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_action());
