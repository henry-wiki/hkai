// jsmods/chat_option/act/refresh_chat_mode_active.js —— chat mode active 重刷（act 層——一檔一事）
// 職責：refresh_chat_mode_active（全按鈕 active 重刷：值相符 → 加；其餘 → 去——純 UI）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _refresh_chat_mode_active {
    constructor() {}

    // chat mode 全按鈕 active 重刷（值相符 → 加；其餘 → 去）
    refresh_chat_mode_active(chat_mode_value) {
        const j_buttons = jQuery("[data-btn='chat_mode']");

        // 空字串＝「有提交但清空」→ 回第一粒（與伺服器同一規則）
        if (chat_mode_value === "") {
            chat_mode_value = j_buttons.first().data("value");
        }

        // 判定：冇任何按鈕值相符（未知值）→ 保持現狀（唔清走 active）
        if (j_buttons.filter(function () {
            return jQuery(this).data("value") === chat_mode_value;
        }).length < 1) {
            return;
        }

        // 逐個 mode 按鈕重刷
        j_buttons.each(function (button_index, button_dom) {
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
