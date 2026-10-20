// jsmods/chat_option/act/refresh_chat_mode_active.js —— chat mode active 重刷（act 層——一檔一事）
// 職責：refresh_chat_mode_active（全按鈕 active 重刷：值相符 → 加；其餘 → 去——純 UI）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _refresh_chat_mode_active {
    constructor() {}

    // chat mode 全按鈕 active 重刷（值相符 → 加；其餘 → 去）
    refresh_chat_mode_active(chat_mode_value) {
        // 逐個 mode 按鈕重刷
        jQuery("[data-btn='chat_mode']").each(function (button_index, button_dom) {
            const j_button = jQuery(button_dom);

            // 判定：按鈕值與目前模式相符 → 加 active
            if (j_button.data("value") === chat_mode_value) {
                j_button.addClass("sc_btn--active");
                return;
            }

            j_button.removeClass("sc_btn--active");
        });
    }
}

export const refresh_chat_mode_active = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_chat_mode_active());
