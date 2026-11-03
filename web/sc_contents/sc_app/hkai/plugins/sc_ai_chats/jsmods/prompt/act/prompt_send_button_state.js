// jsmods/prompt/act/prompt_send_button_state.js —— 主按鈕兩態（act 層——一檔一事；唯一決策者）
// 職責：show_start／show_stop 切換顯示；refresh 依「送出中／有輸入／本對話有回應中」決定邊個出
// 上游：prompt_state_observer（DOM／輸入變 → refresh）、act_prompt_send（發送、成功、失敗後）
// 下游：jQuery 直接切 [data-action='send'] 同 [data-action='stop'] 嘅 hidden
// 結構：eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_send_button_state {
    constructor() {}

    // 顯示開始（Play）——藏停止
    show_start() {
        jQuery("[data-action='send']").prop("hidden", false);
        jQuery("[data-action='stop']").prop("hidden", true);
    }

    // 顯示停止（Stop）——藏開始
    show_stop() {
        jQuery("[data-action='send']").prop("hidden", true);
        jQuery("[data-action='stop']").prop("hidden", false);
    }

    // 有冇可送內容（輸入框有字 或 有附件 chip）
    has_sendable_input() {
        const input_value = jQuery("#sc_input").val();

        // 判定：輸入框有字 → 有
        if (input_value !== undefined && input_value !== null && input_value.trim() !== "") {
            return true;
        }

        const j_attachments = jQuery(".sc_composer__attachments");

        // 判定：容器存在＋未隱藏＋有 chip → 有
        if (j_attachments.length && j_attachments.is("[hidden]") === false && j_attachments.children().length > 0) {
            return true;
        }

        return false;
    }

    // 送出中（撳咗未收後端回應——撳即翻靠佢）
    _is_loading() {
        return jQuery("[data-action='send']").attr("data-state") === "loading";
    }

    // 本對話有冇回應中（只看當前 chat_sid 嘅 viewport）
    async _is_working() {
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const active_chat_sid = await prompt_instance.get_active_chat_sid();

        const viewport = document.querySelector(".sc_thread__viewport");

        // 判定：冇 viewport → 當冇
        if (viewport === null) {
            return false;
        }

        const dom_chat_sid = viewport.getAttribute("data-chat_sid") || "";

        // 判定：兩個 sid 都有值但唔一致（換對話中）→ 當冇
        if (dom_chat_sid !== "" && active_chat_sid !== "" && dom_chat_sid !== active_chat_sid) {
            return false;
        }

        return viewport.querySelector(".message_wrapper[data-working='1']") !== null;
    }

    // 依狀態決定顯示邊個按鈕（唯一決策點）
    async refresh() {
        // 判定：送出中 → 停止（撳即翻，唔等後端）
        if (this._is_loading() === true) {
            this.show_stop();

            return;
        }

        // 判定：有輸入內容 → 開始（輸入優先）
        if (this.has_sendable_input() === true) {
            this.show_start();

            return;
        }

        // 判定：本對話有回應中 → 停止
        if (await this._is_working() === true) {
            this.show_stop();

            return;
        }

        this.show_start();
    }
}

export const prompt_send_button_state = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_send_button_state());
