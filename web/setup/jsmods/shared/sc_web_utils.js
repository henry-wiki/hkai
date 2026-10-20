// sc_app/shared/jsmods/sc_web_utils.js —— app 級共用前端工具（ESM 模組＋掛全域 sc_web_utils）
// 載入：hkai/jsmods/bootstrap.js → registry.js → shared/jsmods/shared.js（ESM 鏈；同時掛全域——既有呼叫點零改動）
// 取用：共用域經 shared().sc_web_utils()；既有 plugin 仍直接 sc_web_utils.xxx()
// 職責：跨 app／跨 plugin 的零狀態工具函數（避免每個 module 各自重複實作）

// sc_alert 單例（is_confirmed 確認框用——同域直接 import；唔經 shared.js 免循環）
import _sc_alert from "./sc_alert/sc_alert.js";

const sc_web_utils = {
    // 讀取 CSRF token（頁面 meta——header 輸出；無 meta 回空字串）
    get_csrf_token() {
        const meta_token = document.querySelector('meta[name="csrf-token"]');
        return meta_token ? meta_token.getAttribute("content") : "";
    },

    // 通用確認框（讀 data-confirm_msg；支援 [[key]] 佔位替換；無屬性 → 直接放行 true；行 sc_alert UI——Promise 化）
    async is_confirmed(j_trigger, attr_name, replacements) {
        const confirm_attr = attr_name ? attr_name : "data-confirm_msg";
        const confirm_msg  = j_trigger.attr(confirm_attr);

        // 判定：無確認訊息 → 直接放行
        if (!confirm_msg) {
            return true;
        }

        let replaced_msg = confirm_msg;

        // 佔位替換（[[key]] → 對應值；未傳 replacements → 原樣）
        jQuery.each(replacements || {}, (replace_key, replace_value) => {
            replaced_msg = replaced_msg.split("[[" + replace_key + "]]").join(replace_value);
        });

        // sc_alert UI 確認框（取消鈕明給——預設係唔出；resolve 值＝關閉原因）
        const close_reason = await _sc_alert().show({
            message      : replaced_msg,
            confirm_text : "確定 · OK",
            cancel_text  : "取消 · Cancel",
        });

        return close_reason === "confirm";
    },

    // 標準表單錯誤套用（errors = {field_key: message}——後端驗證失敗的標準回傳）
    // DOM 慣例：欄位 [data-field='key']；錯誤槽＝同一 .form_row 內的 .form_err
    // 狀態：.form_row.show-err（顯示錯誤）＋ 欄位 .invalid（紅框）
    apply_form_errors(j_scope, errors) {
        // 無錯誤物件 → 不處理
        if (!errors) {
            return;
        }

        // 先清舊錯誤（全部 row／input／err）
        j_scope.find(".form_row").removeClass("show-err");
        j_scope.find("input,textarea").removeClass("invalid");
        j_scope.find(".form_err").text("");

        // 逐欄套用錯誤訊息
        jQuery.each(errors, (field_key, error_message) => {
            const j_field = j_scope.find("[data-field='" + field_key + "']");

            // 找不到對應欄位 → 跳過
            if (!j_field.length) {
                return;
            }

            j_field.addClass("invalid");
            j_field.closest(".form_row").addClass("show-err").find(".form_err").text(error_message);
        });
    },

    // 複製文字到剪貼簿（安全上下文用 Async Clipboard API；HTTP 非安全上下文 → textarea + execCommand 後援）
    // 回傳：Promise<boolean>（成功 true）
    async copy_text(text) {
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
    },

    // 複製回饋：按鈕內圖示短暫轉勾（1.2 秒後還原——複製成功先呼叫）
    flash_copy_icon(j_button) {
        const j_icon = j_button.find("i");
        j_icon.removeClass("fa-regular fa-copy").addClass("fa-solid fa-check");

        setTimeout(function () {
            j_icon.removeClass("fa-solid fa-check").addClass("fa-regular fa-copy");
        }, 1200);
    },

    // 格式化 context length（token 數 → 縮寫字串：達千 → 128k；達百萬 → 1M；其餘原樣）
    get_format_context_length(context_length) {
        const numeric_length = Number(context_length);
        // 非數字 → 原樣回傳
        if (isNaN(numeric_length)) {
            return String(context_length);
        }
        // 未達 1000 → 原樣回傳（無縮寫）
        if (numeric_length < 1000) {
            return String(context_length);
        }
        let divisor = 1000;
        let suffix = "k";
        // 達百萬 → 改用 M 縮寫
        if (numeric_length >= 1000000) {
            divisor = 1000000;
            suffix = "M";
        }
        const display_number = numeric_length / divisor;
        // 去小數尾零（128000 → 128；1500000 → 1.5）
        const trimmed_number = parseFloat(display_number.toFixed(1));

        return trimmed_number + suffix;
    },
};

// 掛全域（既有呼叫點 sc_web_utils.xxx() 零改動）
window.sc_web_utils = sc_web_utils;

// ESM 出口（factory——同 hkai/jsmods/registry.js 模式；shared().sc_web_utils() 取單例）
export default (function (instance) {
    return function () {
        return instance;
    };
})(sc_web_utils);
