// jsmods/prompt/evt/prompt_enter_send.js —— Enter 發送綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_enter_send）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_enter_send {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 輸入框撳鍵（Enter 發送；Shift+Enter 換行——唔攔）
        jQuery(document)
            .off("keydown.sc_ai_chats_prompt_enter_send", "#sc_input")
            .on("keydown.sc_ai_chats_prompt_enter_send", "#sc_input", this.prompt_enter_send.bind(this));
    }

    // Enter 發送（走撳掣同一條路——[data-action='send'] 單一發送入口）
    prompt_enter_send(event) {
        // 判定：輸入法組字中（選字確認嗰下 Enter）→ 唔攔（唔好打一半就送出去）
        if (event.isComposing) {
            return;
        }

        // 判定：唔係 Enter → 唔攔
        if (event.key !== "Enter") {
            return;
        }

        // 判定：帶 Shift（Shift+Enter）→ 唔攔（留返換行）
        if (event.shiftKey) {
            return;
        }

        // 攔落（發送鍵唔出換行——唔插入字符）
        event.preventDefault();

        const content = jQuery("#sc_input").val().trim();

        // 判定：空內容（含淨空白）→ 唔送
        if (content === "") {
            return;
        }

        // 走撳掣同一條路（撳掣綁定喺 evt/prompt_send.js——兩個觸發、一個入口）
        jQuery("[data-action='send']").trigger("click");
    }
}

export const prompt_enter_send = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_enter_send());
