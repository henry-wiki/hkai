// jsmods/model_selector/act/apply_model.js —— Apply 選中 model（action 層——一 action 一檔）
// 職責：apply_model（檢查選取〔model＋effort〕→ hidden fields（value 屬性）→ 同步顯示 → 關 modal；提交交單一提交點）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _apply_model {
    constructor() {}

    // Apply（檢查選取〔model＋effort〕→ hidden fields（value 屬性——觀察器觸發提交）→ 同步顯示 → 關 modal）
    async apply_model() {
        // 動態載入（registry——用先載）
        const shared = registry().shared();

        // 動態載入（act——model context／efforts 讀取／顯示刷新）
        const model_context_instance = await model_selector_instance.act().model_context();
        const render_modal_reasoning_efforts_instance = await model_selector_instance.act().render_modal_reasoning_efforts();
        const refresh_model_display_instance = await model_selector_instance.act().refresh_model_display();

        // ① 檢查選取（model 必有；model 支援 efforts 時 effort 必有——缺 → popup 中止）
        const j_tr_model_row_selected = model_selector_instance.get_tr_model_row_selected();

        const provider_id = j_tr_model_row_selected.attr("data-provider_id");
        const model_id = j_tr_model_row_selected.attr("data-model_id");

        if (!provider_id || !model_id) {
            // popup（確認——未揀 model，是否仍要繼續套用）
            const close_reason = await shared.sc_alert().show({
                message      : "Continue to apply without a model?",
                confirm_text : "確定",
                cancel_text  : "取消",
            });

            // 判定：未確認（取消／關閉）→ return false（中止——不寫 hidden fields、不關 modal、不發 fetch）
            if (close_reason !== "confirm") {
                return false;
            }
        }

        const j_reasoning_effort_buttons = render_modal_reasoning_efforts_instance.get_reasoning_effort_buttons();
        const j_reasoning_effort_button = model_context_instance.get_actived_reasoning_effort_button();

        // 有 effort 鈕＝model 支援 efforts；無一個 active ＝未揀 → popup 中止（不適用＝零鈕——合法）
        if (j_reasoning_effort_buttons.length && !j_reasoning_effort_button.length) {
            shared.sc_alert().warning("Please select a reasoning effort, then apply");
            return;
        }

        // ② modal DOM → hidden fields（即時同步選取狀態；寫 value 屬性——觀察器觸發提交）
        let context_provider_id = provider_id;
        let context_model_id = model_id;

        // 判定：無 model（確認續行路徑）→ 落空字串（attr 唔收 undefined——清空語義）
        if (!context_provider_id) {
            context_provider_id = "";
        }

        // 判定：無 model（確認續行路徑）→ 落空字串
        if (!context_model_id) {
            context_model_id = "";
        }

        model_context_instance.set_provider_id(context_provider_id);
        model_context_instance.set_model_id(context_model_id);

        if (j_reasoning_effort_button.length) {

            const reasoning_effort = j_reasoning_effort_button.attr("data-effort");
            model_context_instance.set_reasoning_effort(reasoning_effort);
        }
        else {
            model_context_instance.set_reasoning_effort("");
        }

        // ③ 同步顯示 + 關 modal
        await refresh_model_display_instance.refresh_model_display_info();

        const j_modal = model_selector_instance.get_j_modal();
        j_modal[0].modal_close();

        // ④ 提交（交單一提交點——model_info 觀察器見 value 屬性變自動 POST；唔喺呢度出街）
    }
}

export const apply_model = (function (instance) {
    return function () {

        return instance;
    };
})(new _apply_model());
