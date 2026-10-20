// jsmods/action/modal_action.js —— model 選擇過程動作（action 層——一 action 一檔）
// 職責：modal_action（Refresh 重生——spawn 取 modal／scroll／fetch 組）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _modal_action {
    constructor() {
        this.model_data = null;
    }

    // 存 model_data（外灌——目前無呼叫者，保留骨架）
    set_model_data(model_data) {
        this.model_data = model_data;
    }

    // 重新下載＋重繪 provider models（① 後端重新下載 provider 檔 → ② fetch 該 provider 頁（loading——同撳左欄同一條載入路）；成敗皆回報 callbacks）
    async rebuild_provider_models(provider_id, callbacks) {
        // spawn 取 fetch 組（req——redownload_provider_models／act——switch_provider_item）
        const redownload_provider_models_instance = await model_selector_instance.req().redownload_provider_models();
        const switch_provider_item_instance = await model_selector_instance.act().switch_provider_item();

        // ① 後端重新下載（req 層啞——alert 由呢度出；成功先續 ②）
        redownload_provider_models_instance.redownload_provider_models(provider_id, {
            success: function (result) {
                // 判定：重新下載成功（provider 層 error = 0）→ 續 fetch 該 provider 頁
                if (
                    result.results
                    && result.results[provider_id]
                    && result.results[provider_id].error === 0
                ) {
                    // ② fetch 該 provider 頁（loading → 灌列）→ 成敗皆回報
                    switch_provider_item_instance.switch_provider_item(provider_id, {
                        success: function () {
                            callbacks.on_complete();
                        },
                        error: function () {
                            callbacks.on_fail();
                        },
                    });

                    return;
                }

                // reset 失敗 → alert ＋ 失敗回報
                alert("cannot refresh models: " + provider_id);
                callbacks.on_fail();
            },
            error: function () {
                // 網路／拒絕失敗 → alert ＋ 失敗回報
                alert("cannot refresh models: " + provider_id);
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
