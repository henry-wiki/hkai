// jsmods/model_selector/act/switch_reasoning_effort.js —— reasoning effort 切換（action 層——一 action 一檔）
// 職責：switch_reasoning_effort（接收傳入 effort 值 → 高亮落定；揀唔到 → 容器預設；無預設＝零 active；hidden field 於 Apply 時先寫入；順手更新活動記憶）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_reasoning_effort {
    constructor() {}

    // 切換 reasoning effort（接收傳入值落定高亮——揀唔到 → 容器預設；無預設＝零 active；順手更新活動記憶）
    async switch_reasoning_effort(effort) {
        const j_modal = model_selector_instance.get_j_modal();
        const j_effort_buttons = j_modal.find(".model_selector_effort_button");

        let selected_effort = effort;

        // 判定：傳入值無對應掣（空／唔支援）→ 用容器預設 effort
        if (!j_effort_buttons.filter("[data-effort='" + effort + "']").length) {
            selected_effort = jQuery("#model_selector_efforts").attr("data-reasoning_default_effort");
        }

        // 高亮落定（先清再上目標；無預設＝零 active）
        j_effort_buttons.filter(".active").removeClass("active");
        j_effort_buttons.filter("[data-effort='" + selected_effort + "']").addClass("active");

        // 寫入頁面活動記憶（選取落定——唔 await——唔阻選取流程）
        const model_activity_instance = await model_selector_instance.act().model_activity();

        model_activity_instance.update_activity();
    }
}

export const switch_reasoning_effort = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_reasoning_effort());
