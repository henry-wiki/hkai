// jsmods/message/act/message_frame_apply.js —— 訊息套用原語（act 層——一檔一事）
// 職責：message_frame_apply（由最底（最新）向上逐條套用——成品節點按 message_id 準確配對換入同名框；一條一幀等畫、完成先返）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_frame_apply {
    constructor() {
        // 每幀套用條數（1＝逐條一幀；調大＝一幀落多條）
        this.messages_per_frame = 1;
    }

    // 逐條套用（message_html＝窗口成品——同 message_frame_build 同一份；回傳套用條數）
    async message_frame_apply(message_html) {
        const j_message_nodes = jQuery(message_html);

        // 配對表（message_id → 成品節點——準確配對全靠佢）
        const message_node_map = new Map();

        for (let index = 0; index < j_message_nodes.length; index++) {
            const j_message_node = jQuery(j_message_nodes[index]);

            message_node_map.set(j_message_node.attr("data-message_id"), j_message_nodes[index]);
        }

        // 框清單（DOM 順序＝最舊 → 最新——由最底行返上去）
        const j_frames = jQuery(".sc_thread__viewport").children(".message_frame");

        let applied_count = 0;

        for (let index = j_frames.length - 1; index >= 0; index--) {
            const j_frame = jQuery(j_frames[index]);

            // 準確配對（同名框換入成品——位置不變）
            const message_id = j_frame.attr("data-message_id");
            j_frame.replaceWith(message_node_map.get(message_id));

            applied_count += 1;

            // 判定：滿一幀 → 讓出一幀（畀瀏覽器畫——逐條套用可見）
            if (applied_count % this.messages_per_frame === 0) {
                await this.wait_next_frame();
            }
        }

        return applied_count;
    }

    // 讓出一幀（requestAnimationFrame——畫完先繼續）
    wait_next_frame() {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                resolve();
            });
        });
    }
}

export const message_frame_apply = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_frame_apply());
