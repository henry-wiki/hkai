// jsmods/prompt/evt/prompt_draft_save.js —— 輸入草稿存底綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_draft_save——交 action）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_draft_save {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 輸入框內容有變（input 打字／change 失焦——即時存草稿底）
        jQuery(document)
            .off("input.sc_ai_chats_prompt_draft_save change.sc_ai_chats_prompt_draft_save", "#sc_input")
            .on("input.sc_ai_chats_prompt_draft_save change.sc_ai_chats_prompt_draft_save", "#sc_input", this.prompt_draft_save.bind(this));
    }

    // 輸入有變（input／change → 交 act 存底；change＝即寫、input＝節流）
    async prompt_draft_save(event) {
        // 橋接駁（同域 act——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_draft_save_instance = await prompt_instance.act().prompt_draft_save();

        prompt_draft_save_instance.prompt_draft_save(event.type === "change");
    }
}

export const prompt_draft_save = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_draft_save());
