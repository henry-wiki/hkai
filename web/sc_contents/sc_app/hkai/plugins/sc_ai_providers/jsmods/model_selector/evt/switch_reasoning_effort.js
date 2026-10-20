// jsmods/model_selector/evt/switch_reasoning_effort.js —— reasoning effort 按鈕綁定（evt 層——一檔一事）
// 職責：switch_reasoning_effort（.model_selector_effort_button 點擊 → 按鈕高亮切換）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_reasoning_effort {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_switch_reasoning_effort")
            .on("click.sc_ai_providers_switch_reasoning_effort", ".model_selector_effort_button", this.switch_reasoning_effort.bind(this));
    }

    // 切換 reasoning effort（data-effort 缺 → 不動作）
    async switch_reasoning_effort(event) {
        const j_element = jQuery(event.currentTarget);

        const effort = j_element.attr("data-effort");
        if (!effort) {
            return;
        }

        // spawn 取組（switch_reasoning_effort——切換高亮＋更新記憶）
        const switch_reasoning_effort_instance = await model_selector_instance.act().switch_reasoning_effort();

        await switch_reasoning_effort_instance.switch_reasoning_effort(effort);
    }
}

export const switch_reasoning_effort = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_reasoning_effort());
