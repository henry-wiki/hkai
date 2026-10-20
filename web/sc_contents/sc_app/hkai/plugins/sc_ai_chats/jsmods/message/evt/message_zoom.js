// jsmods/message/evt/message_zoom.js —— 段放大 click 綁定（factory 單例——thinking／summarize／response／user 共用；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（message_zoom——全文開 modal 檢視）
// 範圍同目標由按鈕自己聲明（data-zoom_scope 先領範圍、data-zoom_target 範圍內目標、data-zoom_title 標題）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_zoom {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 放大檢視（thinking／summarize／response 工具列）
        jQuery(document)
            .off("click.sc_ai_chats_message_zoom")
            .on("click.sc_ai_chats_message_zoom", "[data-action='thinking_zoom'], [data-action='summarize_zoom'], [data-action='response_zoom'], [data-action='user_zoom']", this.message_zoom.bind(this));
    }

    // 放大檢視（全文開 modal——sc_modal 框架；95% 寬、長內容捲動）
    async message_zoom(event) {
        // 點擊元素（selector 委派已命中——currentTarget）
        const j_zoom_button = jQuery(event.currentTarget);

        const scope_selector  = j_zoom_button.attr("data-zoom_scope");
        const target_selector = j_zoom_button.attr("data-zoom_target");
        const modal_title     = j_zoom_button.attr("data-zoom_title");
        const j_zoom_scope    = j_zoom_button.closest(scope_selector);
        const j_content_elements = j_zoom_scope.find(target_selector);

        // 判定：找不到內容 → 不動作
        if (!j_content_elements.length) {
            return;
        }

        // 逐個目標收原文（可能多個——如 summarize 兩段；原文優先——answer 帶 sc_raw_text＝markdown 來源；空段剔除、空行接合）
        const content_texts = j_content_elements.map(function () {
            return this.sc_raw_text || jQuery(this).text().trim();
        }).get();
        const non_empty_texts = content_texts.filter(function (content_piece) {
            return content_piece !== "";
        });
        const modal_text = non_empty_texts.join("\n\n");

        // 內容（基座捲動區 .modal__content > .modal__content-main；文字 .text() 注入——安全）
        const j_zoom_text = jQuery("<div class='zoom_content'></div>").text(modal_text);
        const j_zoom_content_main = jQuery("<div class='modal__content-main'></div>").append(j_zoom_text);
        const j_zoom_content = jQuery("<div class='modal__content'></div>").append(j_zoom_content_main);

        // 動態載入（registry——用先載）
        const sc_modal = registry().sc_modal();

        // 建立 modal（標題＋全文；95% 寬由 CSS 上［data-modal='zoom_content']）
        const j_modal = await sc_modal.create_modal({
            modal_id: "zoom_content",
            header: function (j_modal_header) {
                j_modal_header.find(".modal__title").text(modal_title);
            },
            body: j_zoom_content,
        });

        j_modal.addClass("visible");

        // 動態載入（registry——用先載）
        const message_instance_editblock = await registry().sc_ai_chats().message();
        const message_editblock_instance = await message_instance_editblock.act().message_editblock();

        // 內容 markdown 化（交 message_editblock〔編輯區塊轉顯示形〕——同訊息管線）
        await message_editblock_instance.render_element(j_zoom_text);

        // mermaid 圖表（最終——即時渲染；一次性 modal 唔登記觀察）
        const markdown = await registry().sc_markdown();
        await markdown.render_mermaid_now(j_zoom_text);
    }
}

export const message_zoom = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_zoom());
