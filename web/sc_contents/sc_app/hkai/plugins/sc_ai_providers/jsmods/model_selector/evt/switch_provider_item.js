// jsmods/model_selector/evt/switch_provider_item.js —— 左欄 provider 按鈕點擊綁定（evt 層——一檔一事）
// 職責：switch_provider_item（.side_provider_tab_button 點擊 → 切換右邊成該 provider 嗰頁）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_provider_item {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_switch_provider_item")
            .on("click.sc_ai_providers_switch_provider_item", ".side_provider_tab_button", this.switch_provider_item.bind(this));
    }

    // 選 provider（左欄按鈕——選擇過程：切換右邊頁）
    async switch_provider_item(event) {
        const j_element = jQuery(event.currentTarget);

        const provider_id = j_element.attr("data-provider_id");

        // spawn 取組（switch_provider_item——切換過程）
        const switch_provider_item_instance = await model_selector_instance.act().switch_provider_item();

        switch_provider_item_instance.switch_provider_item(provider_id);
    }
}

export const switch_provider_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_provider_item());
