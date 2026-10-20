// jsmods/prompt/act/prompt_send_button_toggle.js —— 主按鈕兩態（act 層——一檔一事；唯一決策者）
// 職責：prompt_send_button_toggle（掃三源 → 有字＝Send；無字＋進行中＝Stop；無字＋閒置＝Send）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_send_button_toggle {
    constructor() {}

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

        // 條件①：輸入框有字 → Send（輸入優先）
        const input_value = jQuery("#sc_input").val();
        let has_input = false;

        // 判定：取唔到值 → 當冇內容
        if (
            input_value !== undefined
            &&  input_value !== null
        ) {
            has_input = input_value.trim() !== "";
        }

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
})(new _prompt_send_button_toggle());
