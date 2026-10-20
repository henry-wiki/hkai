// jsmods/model_selector/act/render_modal_footer.js —— footer 選中 model 資訊渲染（action 層——一 action 一檔）
// 職責：render_modal_footer（footer 左側＝選中 model 名＋id 兩行＋clear selection 連結；無選中 → 清空）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _render_modal_footer {
    constructor() {}

    // render footer 左側選中 model 資訊（名＋id 兩行——格式跟 model 列 name cell；＋clear selection 連結）
    async render_modal_footer() {
        // footer 左側＝當前選中 model（名＋id 兩行——格式跟 model 列 name cell）；無選中 → 清空
        const j_content_footer_info = model_selector_instance.get_j_modal().find(".modal__content-footer-info");
        j_content_footer_info.empty();

        // 選中列（無選中 → 不再渲染）
        const j_tr_model_row_selected = model_selector_instance.get_tr_model_row_selected();
        // 判定：無選中列 → 結束
        if (!j_tr_model_row_selected.length) {
            return;
        }

        const model_context_instance = await model_selector_instance.act().model_context();

        const model_name = j_tr_model_row_selected.find(".model_selector_model_name").text();
        const model_id   = model_context_instance.get_modal_selected_model_id();
        const j_footer_model = jQuery("<div class='modal__content-footer-model'></div>");
        const j_footer_model_name = jQuery("<div class='model_selector_model_name'></div>").text(model_name);
        const j_footer_model_mid = jQuery("<div class='model_selector_model_mid'></div>").text(model_id);
        j_footer_model_name.appendTo(j_footer_model);
        j_footer_model_mid.appendTo(j_footer_model);
        j_footer_model.appendTo(j_content_footer_info);

        // clear selection＝純文字連結（暗淡、唔搶眼——只清選取、唔閂 modal）
        const j_clear_selection = jQuery("<button id='model_selector_clear_selection' type='button' class='model_selector_clear_selection'>clear selection</button>");
        j_clear_selection.appendTo(j_content_footer_info);
    }
}

export const render_modal_footer = (function (instance) {
    return function () {

        return instance;
    };
})(new _render_modal_footer());
