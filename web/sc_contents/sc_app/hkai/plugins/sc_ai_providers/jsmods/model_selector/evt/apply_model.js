// jsmods/model_selector/evt/apply_model.js —— Apply 按鈕綁定（evt 層——一檔一事）
// 職責：apply_model（#model_selector_apply 點擊 → act/apply_model 全流程；未揀 model 之警告由 act 出）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _apply_model {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_apply_model")
            .on("click.sc_ai_providers_apply_model", "#model_selector_apply", this.apply_model.bind(this));
    }

    // Apply 按鈕（modal DOM → hidden fields → 關 modal → fetch 寫入 server）
    async apply_model() {
        // spawn 取組（apply_model——Apply 流程）
        const apply_model_instance = await model_selector_instance.act().apply_model();

        await apply_model_instance.apply_model();
    }
}

export const apply_model = (function (instance) {
    return function () {

        return instance;
    };
})(new _apply_model());
