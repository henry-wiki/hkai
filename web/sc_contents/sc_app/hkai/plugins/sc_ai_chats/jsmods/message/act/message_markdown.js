// jsmods/message/act/message_markdown.js —— 訊息 markdown 接駁（act 層——一檔一事）
// 職責：訊息側 markdown 入口（唯一知訊息內容槽（result／error）嘅地方）——兩門：message_markdown（單條——完結派 rendered_message_markdown 勾）／message_markdown_all（全窗——掃描完派 rendered_message_markdown_all 勾）
// 對照：sc_markdown 只管「拋入嘅嘢點處理、回咩」——零訊息知識；訊息知識／接駁一律住本檔
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

// 訊息內容選擇器（單一來源——result 內容做 markdown；thinking／summarize 一律純文字；error＝錯誤視為 result，同一管線；唯一知訊息內容槽嘅地方——message_render 掃描共用）
export const MESSAGE_CONTENT_SELECTOR = "[data-text='result'], [data-text='error']";

class _message_markdown {
    constructor() {}

    // 渲染一條訊息嘅內容（外殼內逐個內容區塊——交 sc_markdown.render_element 通用原語）
    async message_markdown(j_message_wrapper) {
        const markdown = await registry().sc_markdown();
        const j_content_elements = j_message_wrapper.find(MESSAGE_CONTENT_SELECTOR);

        // 逐個內容區塊渲染（元素傳入通用原語——HTML 寫返）
        for (let index = 0; index < j_content_elements.length; index++) {
            markdown.render_element(jQuery(j_content_elements[index]));
        }

        // 判定：未完成（working ≠ '0'）→ 唔派勾（交 poll 終態）
        if (j_message_wrapper.attr("data-working") !== "0") {
            return;
        }

        // 逐條勾（單條渲染完——即派；該條標記交 rendered_message_markdown 掛鈎做）
        registry().sc_ai_chats().execute_hook_after("rendered_message_markdown", j_message_wrapper);
    }

    // 渲染全部訊息嘅內容（窗口內所有外殼——全窗掃描；逐條交單條；掃描完派 rendered_message_markdown_all 勾）
    async message_markdown_all() {
        const message_wrapper_elements = jQuery(".sc_thread__viewport").children(".message_wrapper");

        // 逐條渲染（一條處理完先落下一條——唔一次過掃全份）
        for (let index = 0; index < message_wrapper_elements.length; index++) {
            await this.message_markdown(jQuery(message_wrapper_elements[index]));
        }

        // 全窗掃描完畢（rendered_message_markdown_all——結構先備；消費端待定）
        registry().sc_ai_chats().execute_hook_after("rendered_message_markdown_all");
    }
}

export const message_markdown = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_markdown());
