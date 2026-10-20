// jsmods/model_selector/act/model_context.js —— 模型情境讀寫（act 層——一檔一事）
// 職責：model_context（模型情境單一讀寫——composer hidden fields：get_context_* 讀／set_* 寫、get_model_info_from_prompt 三欄組合讀；modal 選取讀取：get_modal_selected_provider_id／get_modal_selected_model_id／get_actived_reasoning_effort_button）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _model_context {
    constructor() {}

    // 讀 provider_id（hidden field data-model_field=provider_id）
    get_context_provider_id() {
        return jQuery("[data-model_field=provider_id]").val();
    }

    // 讀 model_id（hidden field data-model_field=model_id）
    get_context_model_id() {
        return jQuery("[data-model_field=model_id]").val();
    }

    // 讀 reasoning_effort（hidden field data-model_field=reasoning_effort）
    get_context_reasoning_effort() {
        return jQuery("[data-model_field=reasoning_effort]").val();
    }

    // 讀 chat_mode（hidden field data-model_field=chat_mode）
    get_context_chat_mode() {
        return jQuery("[data-model_field=chat_mode]").val();
    }

    // 讀當前 model info（prompt composer hidden fields 即讀——provider_id／model_id／reasoning_effort；三欄組合讀）
    get_model_info_from_prompt() {
        const model_info = {};
        model_info.provider_id      = this.get_context_provider_id();
        model_info.model_id         = this.get_context_model_id();
        model_info.reasoning_effort = this.get_context_reasoning_effort();

        return model_info;
    }

    // 取 modal 內選中 provider（selected 列 data attr——無選中／modal 未開 → null）
    get_modal_selected_provider_id() {
        const j_modal = model_selector_instance.get_j_modal();

        if (!j_modal)
            return null;

        if (!j_modal.length)
            return null;

        const j_model_selector_model_row = j_modal.find(".model_selector_model_row.selected");

        return j_model_selector_model_row.attr("data-provider_id");
    }

    // 取 modal 內選中 model（selected 列 data attr——無選中／modal 未開 → null）
    get_modal_selected_model_id() {
        const j_modal = model_selector_instance.get_j_modal();

        if (!j_modal)
            return null;

        if (!j_modal.length)
            return null;

        const j_model_selector_model_row = j_modal.find(".model_selector_model_row.selected");

        return j_model_selector_model_row.attr("data-model_id");
    }

    // 取當前 active effort 按鈕（modal 未開 → null）
    get_actived_reasoning_effort_button() {
        const j_modal = model_selector_instance.get_j_modal();

        if (!j_modal)
            return null;

        if (!j_modal.length)
            return null;

        return j_modal.find(".model_selector_effort_button.active");
    }

    // 寫 provider_id（hidden field；寫 value 屬性——model_info 觀察器（chats chat_option）靠屬性變觸發提交）
    set_provider_id(provider_id) {
        jQuery("[data-model_field=provider_id]").attr("value", provider_id);
    }

    // 寫 model_id（hidden field；寫 value 屬性——觀察器靠屬性變觸發提交）
    set_model_id(model_id) {
        jQuery("[data-model_field=model_id]").attr("value", model_id);
    }

    // 寫 reasoning_effort（hidden field；寫 value 屬性——觀察器靠屬性變觸發提交）
    set_reasoning_effort(reasoning_effort) {
        jQuery("[data-model_field=reasoning_effort]").attr("value", reasoning_effort);
    }

    // 寫 chat_mode（hidden field；寫 value 屬性——model_info 觀察器（chats chat_option）靠屬性變觸發提交）
    set_chat_mode(chat_mode_value) {
        jQuery("[data-model_field=chat_mode]").attr("value", chat_mode_value);
    }
}

export const model_context = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_context());
