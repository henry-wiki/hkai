// jsmods/prompt/evt/prompt_input.js —— 輸入框打字綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_input）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_input {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 輸入框打字（input／change 兩事件——IME 等非鍵盤改值都經呢度）
        jQuery(document)
            .off("input.sc_ai_chats_prompt_input change.sc_ai_chats_prompt_input", "#sc_input")
            .on("input.sc_ai_chats_prompt_input change.sc_ai_chats_prompt_input", "#sc_input", this.prompt_input.bind(this));
    }

    // 輸入事件（主按鈕兩態交 prompt 域 prompt_send_button_toggle()；打字／停止／發送後對賬都經呢度）
    async prompt_input() {
        // 主按鈕兩態刷新（單一決策者＝prompt 域 prompt_send_button_toggle——本檔唔自己判）
        // 橋接駁（同域 act——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_toggle_instance = await prompt_instance.act().prompt_send_button_toggle();

        prompt_send_button_toggle_instance.prompt_send_button_toggle();
    }
}

export const prompt_input = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_input());
