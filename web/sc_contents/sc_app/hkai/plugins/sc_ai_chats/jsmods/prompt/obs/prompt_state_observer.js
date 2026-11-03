// jsmods/prompt/obs/prompt_state_observer.js —— 主按鈕狀態觀察（obs 層——只報變化，判斷交 act）
// 職責：盯住三個狀態來源（viewport 的 data-working／data-chat_sid、排隊清單子節點、送出按鈕 data-state）＋輸入事件；有變即叫 prompt_send_button_state().refresh()
// 上游：sc_ai_chats prompt_hook_register（開頁一次 init）
// 下游：prompt/act/prompt_send_button_state.js
// 結構：eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_state_observer {
    constructor() {
        // viewport 觀察器（訊息卡狀態變）
        this.viewport_observer = null;

        // 排隊清單觀察器（chips 增減）
        this.queue_observer = null;

        // 送出按鈕觀察器（data-state 變）
        this.button_observer = null;

        // 已綁定（防重複）
        this.bound = false;
    }

    // 綁定（開頁一次；重複呼叫只 refresh）
    init() {
        // 判定：已綁定 → 只做一次 refresh
        if (this.bound === true) {
            this.refresh();

            return this;
        }

        this.bound = true;

        const thisObject = this;

        // 輸入事件（打字即判）
        jQuery(document)
            .off("input.sc_prompt_state_observer")
            .on("input.sc_prompt_state_observer", "#sc_input", function () {
                thisObject.refresh();
            });

        this._observe_viewport();
        this._observe_queue_list();
        this._observe_send_button();

        this.refresh();

        return this;
    }

    // 盯 viewport（子樹＋data-working／data-chat_sid／data-status）
    _observe_viewport() {
        const viewport = document.querySelector(".sc_thread__viewport");

        // 判定：冇 viewport → 唔綁
        if (viewport === null) {
            return;
        }

        const thisObject = this;

        this.viewport_observer = new MutationObserver(function () {
            thisObject.refresh();
        });

        this.viewport_observer.observe(viewport, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["data-working", "data-chat_sid", "data-status"],
        });
    }

    // 盯排隊清單（chips 增減）
    _observe_queue_list() {
        const queue_list = document.querySelector(".sc_composer__queue_list");

        // 判定：冇排隊清單 → 唔綁
        if (queue_list === null) {
            return;
        }

        const thisObject = this;

        this.queue_observer = new MutationObserver(function () {
            thisObject.refresh();
        });

        this.queue_observer.observe(queue_list, { childList: true });
    }

    // 盯送出按鈕（data-state：loading／清除）
    _observe_send_button() {
        const send_button = document.querySelector("[data-action='send']");

        // 判定：冇按鈕 → 唔綁
        if (send_button === null) {
            return;
        }

        const thisObject = this;

        this.button_observer = new MutationObserver(function () {
            thisObject.refresh();
        });

        this.button_observer.observe(send_button, {
            attributes: true,
            attributeFilter: ["data-state"],
        });
    }

    // 叫唯一決策者重判（async 失敗唔影響其餘流程）
    async refresh() {
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const send_button_state_instance = await prompt_instance.act().prompt_send_button_state();

        await send_button_state_instance.refresh();
    }
}

export const prompt_state_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_state_observer());
