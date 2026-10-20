// jsmods/message/evt/message_copy.js —— 複製 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（message_copy——原文讀取→寫剪貼簿→圖示回饋）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_copy {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 複製訊息（使用者訊息與回答共用同一按鈕）
        jQuery(document)
            .off("click.sc_ai_chats_message_copy")
            .on("click.sc_ai_chats_message_copy", "[data-action='message_copy']", this.message_copy.bind(this));
    }

    // 複製（message_copy——data-copy_scope 先領範圍、data-copy_target 範圍內目標；全部按鈕共用）
    async message_copy(event) {
        const j_copy_button = jQuery(event.currentTarget);

        const scope_selector  = j_copy_button.attr("data-copy_scope");
        const target_selector = j_copy_button.attr("data-copy_target");
        const j_copy_scope    = j_copy_button.closest(scope_selector);
        const j_source        = j_copy_scope.find(target_selector);

        // 判定：找不到原文區塊 → 不動作
        if (!j_source.length) {
            return;
        }

        // 逐個目標收原文（可能多個——如 summarize 兩段）；原文優先（串流已渲染 HTML → 從 .text() 反推會丟失 markdown 結構；歷史／使用者訊息無此屬性 → 退回 .text()）
        const raw_texts = j_source.map(function () {
            return this.sc_raw_text || jQuery(this).text();
        }).get();
        const non_empty_texts = raw_texts.filter(function (raw_piece) {
            return raw_piece !== "";
        });
        const raw_text = non_empty_texts.join("\n\n");

        // 判定：複製成功 → 才顯示勾（失敗不誤導使用者）
        const copy_ok = await this.write_to_clipboard(raw_text);
        if (!copy_ok) {
            return;
        }

        this.flash_copy_icon(j_copy_button);
    }

    // 寫入剪貼簿（安全上下文用 Async Clipboard API；HTTP 非安全上下文 → textarea + execCommand 後援）
    // 回傳：Promise<boolean>（成功 true）
    async write_to_clipboard(text) {
        // 判定：安全上下文（HTTPS／localhost）→ 現代 Async Clipboard API
        if (
            navigator.clipboard
            &&  window.isSecureContext
        ) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (copy_error) {
                console.error("clipboard write failed:", copy_error.message);
                return false;
            }
        }

        // 後援：非安全上下文（HTTP）→ textarea + execCommand
        const textarea_element = document.createElement("textarea");
        textarea_element.value = text;
        textarea_element.setAttribute("readonly", "");
        textarea_element.style.position = "fixed";
        textarea_element.style.left = "-9999px";
        document.body.appendChild(textarea_element);

        let copy_ok = false;
        try {
            textarea_element.focus();
            textarea_element.select();
            copy_ok = document.execCommand("copy");
        } catch (copy_error) {
            console.error("clipboard write failed:", copy_error.message);
        } finally {
            document.body.removeChild(textarea_element);
        }

        return copy_ok;
    }

    // 複製回饋：圖示短暫換成勾（1.2 秒後還原）
    flash_copy_icon(j_button) {
        const j_icon = j_button.find("i");
        j_icon.removeClass("fa-regular fa-copy").addClass("fa-solid fa-check");

        setTimeout(function () {
            j_icon.removeClass("fa-solid fa-check").addClass("fa-regular fa-copy");
        }, 1200);
    }
}

export const message_copy = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_copy());
