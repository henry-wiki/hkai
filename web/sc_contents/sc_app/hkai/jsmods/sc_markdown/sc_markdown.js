// jsmods/sc_markdown/sc_markdown.js —— Markdown 渲染模組（shared module——供各 plugin 使用）
// 職責：純文字 → markdown → 消毒 → HTML；code block 高亮；mermaid 圖表（委託 sc_markdown_mermaid.js）——通用原語：只管「拋入嘅嘢點處理、回咩」；零訊息／業務知識（訊息側接駁住各業務側——例：sc_ai_chats/message/act/message_render.js）
// 來源：CDN 傳統 script（header.php 宣告）→ 全域 window.marked / window.DOMPurify / window.hljs

import sc_markdown__mermaid from "./sc_markdown_mermaid.js";

// == 一次性設定（模組載入執行一次；config 層——唔屬任何實例）==
// 為什麼：marked 預設放 raw HTML 直接過 DOM——訊息內嘅 <style> 等會變全頁樣式
//   （2026-10-04 實案：css-intro 訊息嘅 body { margin: 2em } 漏出，全頁多咗一圈空白框）
// 做法：raw HTML 一律唔執行——block 級 → 顯示成 html 代碼框（hljs 高亮）；行內 → 純文字
// token 形狀（marked v18 實測）：{ block: true／false, raw, text }
function escape_markdown_html(text) {
    // 借用文字節點自動轉義
    const j_escape = jQuery("<div>").text(text);
    return j_escape.html();
}

window.marked.use({
    renderer: {
        // raw HTML 代碼化：block 級出代碼框；行內出純文字
        html(token) {
            let raw_html_text = "";

            // 判定：舊式字串 token → 直接用
            if (typeof token === "string") {
                raw_html_text = token;
            // 判定：v18 token 物件 → 取 raw 原文
            } else if (typeof token.raw === "string") {
                raw_html_text = token.raw;
            // 判定：冇 raw → 退回 text 欄位
            } else if (typeof token.text === "string") {
                raw_html_text = token.text;
            }

            // 判定：行內 html（b／br 等）→ 純文字顯示（唔入代碼框）
            if (
                typeof token !== "string"
                &&  token.block !== true
            ) {
                return escape_markdown_html(raw_html_text);
            }

            return "<pre><code class=\"language-html\">" + escape_markdown_html(raw_html_text) + "</code></pre>\n";
        }
    }
});

// 超長 inline code 升格門檻（字元——單 backtick 長文≈源代碼 dump；未到＝保持行內）
const INLINE_CODE_BLOCK_MIN_LENGTH = 200;

class _sc_markdown {
    // 單一元素：讀純文字 → markdown → 消毒 → 寫回 HTML（並高亮 code ＋ 渲染 mermaid）
    render_element(j_element) {
        // 原始文字優先取元素上保留的 markdown（串流已渲染過 HTML → 從 .text() 反推會丟失結構）
        const raw_text = j_element[0].sc_raw_text || j_element.text();

        // 判定：無內容 → 不動（避免清空既有結構）
        if (raw_text === "") {
            return;
        }

        // 保留原文（歷史訊息首次渲染時存下——之後複製／再渲染都只讀它，不再從已渲染 DOM 反推）
        j_element[0].sc_raw_text = raw_text;

        const raw_html  = window.marked.parse(raw_text);
        const safe_html = window.DOMPurify.sanitize(raw_html);

        j_element.addClass("sc_markdown_body");
        j_element.html(safe_html);

        // 超長 inline code 升格（落塊狀 class——同 fence 出嘅塊一致）
        this.upgrade_long_inline_code(j_element);

        // code block 高亮（highlight.js）——先檢查 hljs 是否認得該語言，不認得就跳過
        // == 為什麼 ==
        //   hljs 內建語言有限；mermaid（以及 AI 偶爾吐出的奇怪語言）都不在其中，
        //   直接 highlightElement() 會噴 "Could not find the language 'xxx'" 警告
        // == 不做會怎樣 ==
        //   console 每塊噴一次警告（純噪音；功能不受影響，但白做一次高亮嘗試）
        j_element.find("pre code, code.sc_markdown_code_block").each(function (index, code_element) {
            const language_class = Array.from(code_element.classList).find(function (item) {
                return item.indexOf("language-") === 0;
            });
            const language_name  = language_class ? language_class.replace("language-", "") : "";

            // 判定：hljs 不認得該語言（含 mermaid）→ 跳過，避免噴警告
            if (
                language_name !== ""
                &&  !window.hljs.getLanguage(language_name)
            ) {
                return;
            }

            window.hljs.highlightElement(code_element);
        });

        // mermaid 圖表（最終才渲染）
        // 渲染側已上移訊息層排隊（message_render.render_single_mermaid——登記觀察＋完結掛鈎收結 render_status）；本原語唔自行登記（免同排隊路徑雙重觀察、雙重渲染）
    }

    // 超長 inline code 升格做塊狀（單 backtick 長文≈源代碼——落 class 交 CSS；短嘅保持行內）
    upgrade_long_inline_code(j_element) {
        j_element.find("code").each(function (index, code_element) {
            const j_code_element = jQuery(code_element);

            // 判定：喺 pre 內（fence 出嘅塊）→ 唔郁
            if (j_code_element.closest("pre").length > 0) {
                return;
            }

            // 判定：未到門檻（真係行內短用）→ 唔郁
            if (code_element.textContent.length < INLINE_CODE_BLOCK_MIN_LENGTH) {
                return;
            }

            j_code_element.addClass("sc_markdown_code_block");
        });
    }

    // 串流渲染：只渲染已收尾的完整行（fence 外）；未完成尾巴保持純文字（避免半截語法誤判／閃爍）
    // 回傳：{ boundary_index（完整行邊界）, html }——邊界供呼叫端判斷「是否前進」以決定要不要重建 DOM
    render_streaming(raw_text) {
        const boundary_index = this.get_complete_boundary_index(raw_text);
        const complete_text  = raw_text.substring(0, boundary_index);
        const tail_text      = raw_text.substring(boundary_index);

        let stream_html = "";
        // 判定：有完整內容 → 渲染為消毒後 HTML
        if (complete_text !== "") {
            stream_html += window.DOMPurify.sanitize(window.marked.parse(complete_text));
        }
        // 判定：有未完成尾巴 → 以純文字附在後方
        if (tail_text !== "") {
            stream_html += "<span class='sc_markdown_tail'>" + this.escape_html(tail_text) + "</span>";
        }

        return { boundary_index: boundary_index, html: stream_html };
    }

    // 取安全邊界索引：最後一個「fence 外換行」的結束位置（其後視為未完成）
    get_complete_boundary_index(raw_text) {
        const lines = raw_text.split("\n");

        let line_start_index = 0;
        let boundary_index   = 0;
        let in_fence         = false;

        // 逐行掃描（最後一行未收尾 → 不列入安全邊界）
        for (let line_index = 0; line_index < lines.length - 1; line_index++) {
            const line_text      = lines[line_index];
            const line_end_index = line_start_index + line_text.length;
            const is_fence_line  = (line_text.trim().indexOf("```") === 0);

            // 判定：本行是 fence 標記 → 切換 fence 狀態
            if (is_fence_line) {
                in_fence = !in_fence;
            }
            // 判定：非 fence 內 → 此行換行可作為安全邊界
            if (!in_fence) {
                boundary_index = line_end_index + 1;
            }

            line_start_index = line_end_index + 1;
        }

        return boundary_index;
    }

    // HTML 轉義（尾巴以純文字顯示——不可當 HTML 解析）——實作單一來源：escape_markdown_html
    escape_html(text) {
        return escape_markdown_html(text);
    }

    // mermaid：委託 sc_markdown_mermaid 模組（懶渲染：進入視窗才處理）
    render_mermaid(j_element) {
        sc_markdown__mermaid().render_all(j_element);
    }

    // mermaid（即時——單個元素）：元素內所有 mermaid 區塊即場逐個渲染（唔登記觀察；一個做完先一個）
    async render_mermaid_now(j_element) {
        const mermaid_instance = sc_markdown__mermaid();

        // 一次性 setup（self-guard——首次自動；此後零成本）
        mermaid_instance.init();

        const j_mermaid_blocks = j_element.find("code.language-mermaid");

        // 逐個區塊渲染（一個做完先落一區塊——mermaid.render 本身串行）
        for (let index = 0; index < j_mermaid_blocks.length; index++) {
            await mermaid_instance.render_block(jQuery(j_mermaid_blocks[index]));
        }
    }
}

export const sc_markdown = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_markdown());
