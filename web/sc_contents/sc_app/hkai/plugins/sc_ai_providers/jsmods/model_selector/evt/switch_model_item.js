// jsmods/model_selector/evt/switch_model_item.js —— model 列點擊綁定（evt 層——一檔一事）
// 職責：switch_model_item（.model_selector_model_row 點擊 → 選中高亮 → efforts 重繪）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_model_item {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_switch_model_item")
            .on("click.sc_ai_providers_switch_model_item", ".model_selector_model_row", this.switch_model_item.bind(this));
    }

    // model 列點擊（元素上帶 data-provider_id + data-model_id——選取＋efforts 續行）
    async switch_model_item(event) {
        const j_element = jQuery(event.currentTarget);

        const provider_id = j_element.attr("data-provider_id");
        const model_id = j_element.attr("data-model_id");

        // spawn 取組（switch_model_item——選列＋重繪 efforts）
        const switch_model_item_instance = await model_selector_instance.act().switch_model_item();

        await switch_model_item_instance.switch_model_item(provider_id, model_id);
    }
}

export const switch_model_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_model_item());
