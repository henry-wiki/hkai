// jsmods/action/refresh_model_display.js —— 模型顯示欄刷新（action 層——一 action 一檔）
// 職責：refresh_model_display（.sc_composer__model_picker 顯示：provider＋model＋effort；未選 → has_model 提示）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _refresh_model_display {
    constructor() {}

    // 更新 model 顯示（未選模型 → 提示；有選 → provider ＋ model ＋ effort）
    async refresh_model_display_info() {
        // 動態載入（callback——model context）
        const model_context_instance = await model_selector_instance.act().model_context();

        const provider_id      = model_context_instance.get_context_provider_id();
        const model_id         = model_context_instance.get_context_model_id();
        const reasoning_effort = model_context_instance.get_context_reasoning_effort();

        const j_selector = jQuery(".sc_composer__model_picker");

        // 未選模型（provider 或 model 任一為空）→ 提示
        let has_model = true;
        if (provider_id === "") {
            has_model = false;
        }
        if (model_id === "") {
            has_model = false;
        }

        j_selector.toggleClass("sc_composer__model_picker--has_model", has_model);

        // 未選模型 → 欄位清空（提示文字由 CSS 依 has_model 顯示）
        if (has_model === false) {
            j_selector.find(".sc_composer__model_provider").text("");
            j_selector.find(".sc_composer__model_name").text("");
            j_selector.find(".sc_composer__model_effort").text("").hide();

            return;
        }

        j_selector.find(".sc_composer__model_provider").text(provider_id);
        j_selector.find(".sc_composer__model_name").text(model_id);
        j_selector.find(".sc_composer__model_effort").text(reasoning_effort);

        // 無 effort → 隱藏（有才顯示）
        if (reasoning_effort === "") {
            j_selector.find(".sc_composer__model_effort").hide();
        }
        else {
            j_selector.find(".sc_composer__model_effort").show();
        }

    }
}

export const refresh_model_display = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_model_display());
