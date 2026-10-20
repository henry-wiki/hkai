// jsmods/message/act/message_item_render.js —— 訊息項目渲染（act 層——一檔一事）
// 職責：message_item_render（一批 message_entries（後台兩層資料）→ 逐條插 template 外殼（render_status＝frame）＋即灌 slots 內容（同檔 message_slot_apply——一條一幀等畫）；回傳渲染條數）
// 同檔第二門：message_slot_apply（內容階段——load_more 插頂後單獨引用；按 message_id 搵外殼灌入、標 render_status＝raw）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_item_render {
    constructor() {
        // 每幀套用條數（1＝逐條一幀；調大＝一幀落多條）
        this.messages_per_frame = 1;
    }

    // 逐條渲染（外殼階段＋內容階段——一個入口做齊；回傳渲染條數）
    async message_item_render(message_entries) {
        // 窗口容器＋模板錨點（固定結構——唯一來源＝chat/view.php；外殼插喺錨點前）
        const j_messages = jQuery(".sc_thread__viewport");
        const j_scroll_anchor = j_messages.children("#sc_scroll_anchor");

        // 外殼階段（逐條 template 外殼——JS 零砌；狀態標記 frame＝內容未灌）
        for (let index = 0; index < message_entries.length; index++) {
            const message_entry = message_entries[index];

            const j_message_wrapper = jQuery(message_entry.template.message);

            // 狀態標記：骨架空殼（render_status——state 1 frame；內容未灌）
            j_message_wrapper.attr("data-render_status", "frame");

            j_message_wrapper.insertBefore(j_scroll_anchor);
        }

        // 內容階段（同檔——按 message_id 配對灌入；一條一幀等畫；完成先返）
        const rendered_count = await this.message_slot_apply(message_entries);

        return rendered_count;
    }

    // 訊息內容套用（由最底（最新）向上逐條灌 slots——按 message_id 搵 template 外殼、[data-slot] 空容器換入真內容；灌完標 wrapper render_status＝raw；一條一幀等畫、完成先返）
    async message_slot_apply(message_entries) {
        // 槽位替換工具（共用——_common 桶）
        const common_instance = await registry().sc_ai_chats()._common();
        const replace_slots_instance = await common_instance.act().replace_slots();

        const j_messages = jQuery(".sc_thread__viewport");

        let applied_count = 0;

        // 由最底（最新）向上逐條（DOM 順序＝最舊 → 最新——由尾行返上去）
        for (let index = message_entries.length - 1; index >= 0; index--) {
            const message_entry = message_entries[index];

            // 準確配對（message_id → 同名外殼——位置不變）
            const message_id = jQuery(message_entry.template.message).attr("data-message_id");
            const j_message_wrapper = j_messages.find(".message_wrapper[data-message_id=" + message_id + "]");

            replace_slots_instance.replace_slots(j_message_wrapper, message_entry.slots);

            // 狀態標記：內容灌入完成（render_status——state 2 raw；markdown 未渲染）
            j_message_wrapper.attr("data-render_status", "raw");

            applied_count += 1;

            // 判定：滿一幀 → 讓出一幀（畀瀏覽器畫——逐條灌入可見）
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

export const message_item_render = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_item_render());
