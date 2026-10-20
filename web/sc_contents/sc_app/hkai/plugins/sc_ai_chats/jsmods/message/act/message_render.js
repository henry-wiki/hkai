// jsmods/message/act/message_render.js —— 訊息渲染（act 層——一檔一事）
// 職責：message_contains（渲染前快速掃描——條訊息包含啲咩：純文字（markdown 步驟——無論如何都行）／mermaid（或者有）；原文 indexOf——零 parse）／render_single_markdown（渲染一條訊息嘅內容槽——交 sc_markdown 通用原語；已完結即派 rendered_message_markdown 勾）／render_all_markdown（全窗掃描——逐條交 render_single_markdown；掃描完派 rendered_message_markdown_all 勾）／render_single_mermaid（渲染一條訊息嘅 mermaid——排隊制〔每條完即派 rendered_message_mermaid 勾〕；視窗 data-mermaid＝靜止（idle）／處理中（processing）；處理中嘅呼叫入排隊）／refresh_render_status（狀態判定——冇 mermaid＝ready；有 mermaid＝markdown（等 mermaid）；對齊 render_status 軸——唔撈後端 message_status 生命週期）／finish_render_status（狀態收結——mermaid 渲染完落 ready）
// 對照：sc_markdown 只管「拋入嘅嘢點處理、回咩」——零訊息知識；訊息知識／接駁一律住本檔
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

// 訊息內容選擇器（單一來源——message_markdown 出口；本檔掃描共用）
import { MESSAGE_CONTENT_SELECTOR } from "./message_markdown.js";

class _message_render {
    constructor() {
        // mermaid 排隊（視窗同一時間做一條——處理中嘅呼叫排隊等）
        this.mermaid_queue = [];
    }

    // 快速掃描（渲染前——一條訊息外殼）：原文搵 "```mermaid"（純字串搜尋——最快；唔 parse 唔建 DOM）
    message_contains(j_message_wrapper) {
        const j_content_elements = j_message_wrapper.find(MESSAGE_CONTENT_SELECTOR);

        let contains_mermaid = false;

        // 逐個內容槽掃（原文優先 sc_raw_text——渲染後唔可以從 HTML 反推 fence）
        for (let index = 0; index < j_content_elements.length; index++) {
            const content_element = j_content_elements[index];
            const raw_text = content_element.sc_raw_text || jQuery(content_element).text();

            // 判定：搵到 mermaid fence（原文）→ 記住
            if (raw_text.indexOf("```mermaid") !== -1) {
                contains_mermaid = true;
            }
        }

        return {
            markdown: true,
            mermaid: contains_mermaid
        };
    }

    // 渲染一條訊息嘅內容（外殼內逐個內容區塊——交 message_editblock〔編輯區塊轉顯示形〕→ sc_markdown 通用原語）
    async render_single_markdown(j_message_wrapper) {
        const message_instance = await registry().sc_ai_chats().message();
        const message_editblock_instance = await message_instance.act().message_editblock();
        const j_content_elements = j_message_wrapper.find(MESSAGE_CONTENT_SELECTOR);

        // 逐個內容區塊渲染（交 message_editblock——平常文字佢會直過通用原語）
        for (let index = 0; index < j_content_elements.length; index++) {
            await message_editblock_instance.render_element(jQuery(j_content_elements[index]));
        }

        // 判定：未完成（working ≠ '0'）→ 唔派勾（交 poll 終態）
        if (j_message_wrapper.attr("data-working") !== "0") {
            return;
        }

        // 逐條勾（單條渲染完——即派；該條標記交 rendered_message_markdown 掛鈎做）
        registry().sc_ai_chats().execute_hook_after("rendered_message_markdown", j_message_wrapper);
    }

    // 渲染全部訊息嘅內容（窗口內所有外殼——全窗掃描；逐條交 render_single_markdown；掃描完派 rendered_message_markdown_all 勾）
    async render_all_markdown() {
        const message_wrapper_elements = jQuery(".sc_thread__viewport").children(".message_wrapper");

        // 逐條渲染（一條處理完先落下一條——唔一次過掃全份）
        for (let index = 0; index < message_wrapper_elements.length; index++) {
            await this.render_single_markdown(jQuery(message_wrapper_elements[index]));
        }

        // 全窗掃描完畢（rendered_message_markdown_all——結構先備；消費端待定）
        registry().sc_ai_chats().execute_hook_after("rendered_message_markdown_all");
    }

    // 渲染一條訊息嘅 mermaid（排隊制——視窗同一時間做一條；處理中再叫＝入排隊）
    render_single_mermaid(j_message_wrapper) {
        // 判定：視窗處理中（data-mermaid＝processing）→ 入排隊（輪到先做）
        if (jQuery(".sc_thread__viewport").attr("data-mermaid") === "processing") {
            this.mermaid_queue.push(j_message_wrapper);
            return;
        }

        this.run_mermaid_queue(j_message_wrapper);
    }

    // 開工（視窗標處理中 → 逐條做 → 排隊清空＝復位靜止）
    async run_mermaid_queue(j_message_wrapper) {
        jQuery(".sc_thread__viewport").attr("data-mermaid", "processing");

        // 接駁（sc_markdown 域——即時渲染單件）
        const markdown = await registry().sc_markdown();

        let j_current_wrapper = j_message_wrapper;

        // 逐條做（做完一條 → 拉排隊下一條；冇 → 收工）
        while (j_current_wrapper) {
            await markdown.render_mermaid(j_current_wrapper);

            // 逐條勾（單條 mermaid 渲染完——即派；該條收結交 rendered_message_mermaid 掛鈎做）
            registry().sc_ai_chats().execute_hook_after("rendered_message_mermaid", j_current_wrapper);

            j_current_wrapper = this.mermaid_queue.shift();
        }

        jQuery(".sc_thread__viewport").attr("data-mermaid", "idle");
    }

    // 狀態判定（渲染後——按掃描結果）：落該條 render_status——冇 mermaid＝ready（冇後續步驟）；有 mermaid＝markdown（等 mermaid）
    async refresh_render_status(j_message_wrapper) {
        const contains = this.message_contains(j_message_wrapper);

        // 判定：有 mermaid → markdown（等 mermaid 做——暫停期保持）；否則 → ready（當前啟用管線全完）
        if (contains.mermaid) {
            j_message_wrapper.attr("data-render_status", "markdown");
        } else {
            j_message_wrapper.attr("data-render_status", "ready");
        }
    }

    // 狀態收結（mermaid 渲染完——該條 render_status 落 ready；管線全完）
    finish_render_status(j_message_wrapper) {
        j_message_wrapper.attr("data-render_status", "ready");
    }
}

export const message_render = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_render());
