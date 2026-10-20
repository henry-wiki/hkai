// jsmods/prompt/evt/prompt_gen_random.js —— 隨機範例問題綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（prompt_gen_random）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _prompt_gen_random {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // generate sample message（sample question 填入輸入框——不發送）
        jQuery(document)
            .off("click.sc_ai_chats_prompt_gen_random")
            .on("click.sc_ai_chats_prompt_gen_random", "[data-action='generate_sample']", this.prompt_gen_random.bind(this))
    }

    // generate sample message：取得 sample question → 填入 user_prompt＋聚焦輸入框（只填入——不發送、不啟動 poll）
    async prompt_gen_random(event) {
        // fetch sample questions（GET——免 CSRF；後端已 shuffle——取第一個即隨機）
        let sample_question = "";
        try {
            const response = await fetch(
                "/request/hkai/prompt_gen_random/",
            );
            const result = await response.json();
            if (
                result
                &&  result.questions
                &&  result.questions.length > 0
            ) {
                sample_question = result.questions[0].question;
            }
        } catch (fetch_error) {
            console.error("prompt_gen_random failed:", fetch_error);
            return;
        }

        if (!sample_question) {
            return;
        }

        // 填入輸入框（handle_send_prompt 內部讀 #sc_input）
        jQuery("#sc_input").val(sample_question);

        // 通知 input handlers（主按鈕同步／autoGrow——val() 本身不觸發事件）
        jQuery("#sc_input").trigger("input");

        // 聚焦輸入框（填入後即 focus——方便直接改字／發送）
        jQuery("#sc_input").focus();
    }
}

export const prompt_gen_random = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_gen_random());
