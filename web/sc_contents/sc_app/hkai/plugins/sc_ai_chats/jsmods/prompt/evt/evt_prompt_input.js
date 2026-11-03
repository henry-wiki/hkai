// jsmods/prompt/evt/evt_prompt_input.js —— 輸入框打字綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_input）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _evt_prompt_input {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 輸入框打字（input／change 兩事件——IME 等非鍵盤改值都經呢度）
        jQuery(document)
            .off("input.sc_ai_chats_prompt_input change.sc_ai_chats_prompt_input", "#sc_input")
            .on("input.sc_ai_chats_prompt_input change.sc_ai_chats_prompt_input", "#sc_input", this.prompt_input.bind(this));
    }

    // 輸入事件（主按鈕兩態已交 prompt_state_observer 監 input——本檔唔再判斷）
    async prompt_input() {
    }
}

export const prompt_input = (function (instance) {
    return function () {

        return instance;
    };
})(new _evt_prompt_input());
