// jsmods/model_selector/act/render_modal_reasoning_efforts.js —— reasoning effort 按鈕產生（action 層——一 action 一檔）
// 職責：render_modal_reasoning_efforts（依選中列 data attrs 產生 effort 按鈕——無 efforts＝不適用；預設 effort 掛容器 attr；收尾讀活動記憶 → 傳入 switch_reasoning_effort 落定；＋get_reasoning_effort_buttons 讀取）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _render_modal_reasoning_efforts {
    constructor() {}

    // render 左側 effort 按鈕（依選中 model 動態生成——資料由選中列 data attrs 讀；無 efforts → 不適用零按鈕；預設掛容器 attr 收尾讀記錄傳入 switch_reasoning_effort）
    async render_modal_reasoning_efforts() {
        const j_model_selector_efforts = jQuery("#model_selector_efforts");
        j_model_selector_efforts.empty();

        // 選中列（無選中 → 不再渲染）
        const j_tr_model_row_selected = model_selector_instance.get_tr_model_row_selected();
        // 判定：無選中列 → 結束
        if (!j_tr_model_row_selected.length) {
            return;
        }

        // effort 資料（後端砌列時掛 data attrs——逗號字串 → 陣列；缺＝空）
        let reasoning_supported_efforts = [];
        const supported_efforts_text = j_tr_model_row_selected.attr("data-reasoning_supported_efforts");
        // 判定：有帶字串 → 拆逗號
        if (supported_efforts_text) {
            reasoning_supported_efforts = supported_efforts_text.split(",");
        }

        // 預設 effort（讀選中列 → 掛容器 attr；switch_reasoning_effort 揀唔到候選時嘅落腳點）
        const reasoning_default_effort = j_tr_model_row_selected.attr("data-reasoning_default_effort");
        j_model_selector_efforts.attr("data-reasoning_default_effort", reasoning_default_effort);

        // 無 efforts → 不適用（用戶無需選／不能選）
        if (reasoning_supported_efforts.length === 0) {
            const empty_element = jQuery("<span class='model_selector_efforts_empty'></span>");
            empty_element.text("不適用（此 model 不支援 reasoning effort）");
            j_model_selector_efforts.append(empty_element);

            return;
        }
        else
        {
            for (const effort_value of reasoning_supported_efforts) {

                // effort 掣（data-effort——事件委派分派依據；細件直接砌）
                const j_effort_button = jQuery("<button class='model_selector_effort_button' ></button>");
                j_effort_button.attr("data-effort", effort_value);
                j_effort_button.text(effort_value);
                j_model_selector_efforts.append(j_effort_button);
            }
        }

        // 讀活動記憶（系統記錄——記錄嗰個 effort 傳返入 act 落定）
        const model_activity_instance = await model_selector_instance.act().model_activity();
        const model_info = await model_activity_instance.get_activity();

        let recorded_reasoning_effort = "";
        // 判定：有記憶 → 取記錄 effort 做傳入值
        if (model_info) {
            recorded_reasoning_effort = model_info.reasoning_effort;
        }

        // 交 act 落定選取（傳入記錄 effort——揀唔到 → 容器預設）
        const switch_reasoning_effort_instance = await model_selector_instance.act().switch_reasoning_effort();
        await switch_reasoning_effort_instance.switch_reasoning_effort(recorded_reasoning_effort);
    }

    // 取全部 effort 按鈕（modal 未開 → null）
    get_reasoning_effort_buttons() {
        const j_modal = model_selector_instance.get_j_modal();

        if (!j_modal)
            return null;

        if (!j_modal.length)
            return null;

        return j_modal.find(".model_selector_effort_button");
    }

}

export const render_modal_reasoning_efforts = (function (instance) {
    return function () {

        return instance;
    };
})(new _render_modal_reasoning_efforts());
