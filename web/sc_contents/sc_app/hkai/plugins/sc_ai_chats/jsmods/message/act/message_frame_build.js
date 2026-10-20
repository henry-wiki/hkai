// jsmods/message/act/message_frame_build.js —— 訊息框架鋪設原語（act 層——一檔一事）
// 職責：message_frame_build（成品 HTML → 逐條建骨架框 .message_frame[data-message_id]（固定基礎高度、灰色空框）→ 插喺錨點前；載入側先鋪位——內容稍後由 message_frame_apply 逐條換入）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_frame_build {
    constructor() {}

    // 鋪框架（message_html＝窗口成品；j_scroll_anchor＝底部錨點——框插喺佢前面）
    message_frame_build(message_html, j_scroll_anchor) {
        const j_message_nodes = jQuery(message_html);

        // 逐條成品 → 一個同名框（順序跟成品＝最舊 → 最新——配對鍵 data-message_id）
        for (let index = 0; index < j_message_nodes.length; index++) {
            const j_message_node = jQuery(j_message_nodes[index]);

            const j_frame = jQuery("<div class='message_frame'></div>");
            j_frame.attr("data-message_id", j_message_node.attr("data-message_id"));

            j_frame.insertBefore(j_scroll_anchor);
        }
    }
}

export const message_frame_build = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_frame_build());
