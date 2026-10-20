// jsmods/model_selector/evt/filter_model_list.js —— 表內 search 綁定（evt 層——一檔一事）
// 職責：filter_model_list（.model_selector_table_search 輸入 → 過濾當前 provider 表嘅 model 列 DOM show/hide）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _filter_model_list {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(model_selector_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("input.sc_ai_providers_filter_model_list")
            .on("input.sc_ai_providers_filter_model_list", ".model_selector_table_search", this.filter_model_list.bind(this));
    }

    // 表內 search（輸入關鍵字 → 唔中嘅 model 列收埋；清空 → 全出）
    async filter_model_list(event) {
        const j_search = jQuery(event.currentTarget);

        let keyword = j_search.val();
            keyword = keyword.toLowerCase();
            keyword = keyword.trim();

        const j_model_rows = model_selector_instance.get_tr_model_rows();

        if (keyword === "") {
            j_model_rows.show();
            return;
        }

        j_model_rows.hide();
        j_model_rows.filter(function () {

            const haystack = jQuery(this).text().toLowerCase();
            if (haystack.indexOf(keyword) !== -1) {
                return true;
            }
            return false;
        }).show();
    }
}

export const filter_model_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _filter_model_list());
