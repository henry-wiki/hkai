// jsmods/prompt/obs/prompt_input_height_observer.js —— 輸入框高度觀察綁定（factory 單例——盯輸入管道：用戶打字＋程式改值（既定 trigger("input") 管道）；同一位置決定高度：內容長→長、縮／清空→縮；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——input／change selector 委派）② 動作（refresh_height——autoGrow 重算，上限 160px）
// 備註：textarea 值變冇 DOM 訊號（實測）——所以監察源＝輸入事件管道（程式改值一律經既定 trigger("input") 交返管道——全專案既有 6 處同款）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_input_height_observer {
    constructor() {}

    // 綁定註冊（由 prompt 載入收結呼叫——重掛安全：先 off 再 on）
    init() {
        // 輸入框高度（input／change 兩事件——IME 等非鍵盤改值都經呢度；綁 .sc_composer 容器——唔用 document）
        jQuery(".sc_composer")
            .off("input.sc_ai_chats_prompt_input_height_observer change.sc_ai_chats_prompt_input_height_observer", "#sc_input")
            .on("input.sc_ai_chats_prompt_input_height_observer change.sc_ai_chats_prompt_input_height_observer", "#sc_input", this.refresh_height.bind(this));
    }

    // 高度重算（先 auto 再按內容高（上限 160px）——長／縮／清空同一支）
    refresh_height(event) {
        const input_element = event.currentTarget;

        input_element.style.height = "auto";
        input_element.style.height = Math.min(input_element.scrollHeight, 160) + "px";
    }
}

export const prompt_input_height_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_input_height_observer());
