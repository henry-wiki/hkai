// jsmods/message/act/message_render.js —— 訊息渲染（act 層——一檔一事）
// 職責：message_contains（渲染前快速掃描——條訊息包含啲咩：純文字（markdown 步驟——無論如何都行）／mermaid（或者有）；原文 indexOf——零 parse）＋message_render_status（狀態判定——冇 mermaid＝ready；有 mermaid＝markdown（等 mermaid）；對齊 render_status 軸——唔撈後端 message_status 生命週期）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { MESSAGE_CONTENT_SELECTOR } from "./message_markdown.js";

class _message_render {
    constructor() {}

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

    // 狀態判定（渲染後——按掃描結果）：冇 mermaid＝ready（冇後續步驟）；有 mermaid＝markdown（等 mermaid）
    message_render_status(j_message_wrapper) {
        const contains = this.message_contains(j_message_wrapper);

        // 判定：有 mermaid → markdown（等 mermaid 做）；否則 → ready
        if (contains.mermaid) {
            return "markdown";
        }

        return "ready";
    }
}

export const message_render = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_render());
