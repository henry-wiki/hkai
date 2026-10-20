// jsmods/model_selector/evt/refresh_model_list.js —— provider Refresh 按鈕綁定（evt 層——一檔一事）
// 職責：refresh_model_list（.model_selector_provider_refresh 點擊 → 按鈕 disabled → rebuild_provider_models → 復原）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _refresh_model_list {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_refresh_model_list")
            .on("click.sc_ai_providers_refresh_model_list", ".model_selector_provider_refresh", this.refresh_model_list.bind(this));
    }

    // Refresh 按鈕（載入中 disabled——成敗兩路都復原）
    async refresh_model_list(event) {
        const j_element = jQuery(event.currentTarget);

        j_element.prop("disabled", true);

        const provider_id = j_element.attr("data-provider_id");

        // spawn 取組（modal_action——重生＋重繪）
        const modal_action = await model_selector_instance.act().modal_action();

        modal_action.rebuild_provider_models(provider_id, {
            on_complete: () => j_element.prop("disabled", false),
            on_fail:     () => j_element.prop("disabled", false),
        });
    }
}

export const refresh_model_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_model_list());
