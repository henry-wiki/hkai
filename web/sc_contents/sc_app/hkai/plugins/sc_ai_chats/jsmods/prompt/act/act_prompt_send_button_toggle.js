// jsmods/prompt/act/act_prompt_send_button_toggle.js —— 主按鈕兩態（act 層——一檔一事；唯一決策者）
// 職責：prompt_send_button_toggle（掃三源 → 有字＝Send；無字＋進行中＝Stop；無字＋閒置＝Send）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _act_prompt_send_button_toggle {
    constructor() {}

    // 有冇可送內容（輸入框有字 或 有附件 chip——唯一判準，Enter 與撳掣共用）
    has_sendable_input() {
        const input_value = jQuery("#sc_input").val();

        // 判定：輸入框有字 → 有
        if (
            input_value !== undefined
            &&  input_value !== null
            &&  input_value.trim() !== ""
        ) {
            return true;
        }

        const j_attachments = jQuery(".sc_composer__attachments");

        // 判定：容器存在＋未隱藏＋有 chip → 有
        if (
            j_attachments.length
            &&  j_attachments.is("[hidden]") === false
            &&  j_attachments.children().length > 0
        ) {
            return true;
        }

        return false;
    }

    prompt_send_button_toggle() {
        const j_btn_send = jQuery("[data-action='send']");
        const j_btn_stop = jQuery("[data-action='stop']");

        // 判定：按鈕唔存在（未選專案等）→ 不做
        if (!j_btn_send.length) {
            return;
        }
        if (!j_btn_stop.length) {
            return;
        }

        // 條件①：有可送內容（輸入框有字 或 有附件 chip）→ Send（輸入優先）
        const has_input = this.has_sendable_input();

        if (has_input === true) {
            j_btn_send.prop("hidden", false);
            j_btn_stop.prop("hidden", true);

            return;
        }

        // 條件②：無字——逐源判有冇工作中
        // 源①：送出中（data-state=loading——撳咗未收後端回應；撳即翻靠佢）
        let is_working = j_btn_send.attr("data-state") === "loading";

        // 源②：等待列有排隊中（DOM 為準）
        if (is_working === false) {
            is_working = jQuery(".sc_composer__queue_list .queue_item").length > 0;
        }

        // 源③：最後一條 wrapper 工作中（data-working 由後端畀——JS 零 status 清單）
        if (is_working === false) {
            const j_last_wrapper = jQuery(".sc_thread__viewport .message_wrapper").last();
            is_working = j_last_wrapper.attr("data-working") === "1";
        }

        // 決定（無字＋工作中＝stop；無字＋閒置＝send）
        j_btn_send.prop("hidden", is_working === true);
        j_btn_stop.prop("hidden", is_working === false);
    }
}

export const prompt_send_button_toggle = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_prompt_send_button_toggle());
