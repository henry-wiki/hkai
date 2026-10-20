// jsmods/model_selector/evt/clear_selection.js —— clear selection 連結綁定（evt 層——一檔一事）
// 職責：clear_selection（#model_selector_clear_selection 點擊 → 清空 modal 選取；唔閂 modal）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _clear_selection {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document；連結隨選取重掛，故用委派）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_providers_clear_selection")
            .on("click.sc_ai_providers_clear_selection", "#model_selector_clear_selection", this.clear_selection.bind(this));
    }

    // 清空選取（高亮列／footer 資訊／efforts 帶全清——唔閂 modal、唔寫 server）
    clear_selection() {
        const j_modal = model_selector_instance.get_j_modal();

        const j_model_rows_selected = j_modal.find(".model_selector_model_row.selected");
        j_model_rows_selected.removeClass("selected");

        // footer 資訊（連 clear selection 連結——無選取＝無連結）
        const j_content_footer_info = j_modal.find(".modal__content-footer-info");
        j_content_footer_info.empty();

        // efforts 帶
        const j_model_selector_efforts = j_modal.find("#model_selector_efforts");
        j_model_selector_efforts.empty();
    }
}

export const clear_selection = (function (instance) {
    return function () {

        return instance;
    };
})(new _clear_selection());
