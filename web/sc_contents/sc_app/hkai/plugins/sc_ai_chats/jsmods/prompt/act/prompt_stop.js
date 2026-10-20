// jsmods/prompt/act/prompt_stop.js —— 取消查詢流程（act 層——一檔一事）
// 職責：prompt_stop（接線 handler——取消＋回呼；目標 id 由畫面讀）／prompt_stop_success（已取消→畫面回收：標記 cancelled／對話已清切走）／prompt_stop_error（取消失敗→不動作）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_stop {
    constructor() {}

    // 取消目前查詢（目標 id 由畫面讀——bind 已備好 request_data）
    async prompt_stop(request_data, ui_context) {
        // 橋接駁（同域 req 橋——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_stop_instance = await prompt_instance.req().prompt_stop();

        const thisObject = this;

        return prompt_stop_instance.prompt_stop(request_data, {
            // 已取消（removed／已標記）——畫面回收：無痕剔 chip／標記 cancelled／對話已清切走
            success: function (response_data) {
                thisObject.prompt_stop_success(ui_context, request_data, response_data);
            },
            // 取消失敗（網路／後端拒）——不動作（維持原狀態）
            error: function (response_data) {
                thisObject.prompt_stop_error(ui_context, request_data, response_data);
            }
        });
    }

    // 本機側停止成功程序：畫面回收（標記 cancelled 氣泡；對話已清 → 無痕切走）
    async prompt_stop_success(ui_context, request_data, response_data) {
        // mini loading 收尾（按鈕還原——後續顯隱交按鈕對賬）
        ui_context.j_stop_button.removeClass("loading");

        const j_target_message = jQuery(".sc_thread__viewport .message_wrapper[data-message_id='" + request_data.message_id + "']");

        // 已派工取消 → 標記 cancelled 氣泡
        j_target_message.attr("data-status", "cancelled");

        jQuery("#sc_input").trigger("input");

        // 判定：整個對話已被清（最後一則被回收）→ 無痕導向專案根（/project_slug/）
        if (response_data.chat_deleted) {
            // 橋接駁（chat 域 act 橋——跨域新方式）
            const chat_instance = await registry().sc_ai_chats().chat();
            const chat_item_switch_instance = await chat_instance.act().chat_item_switch();

            await chat_item_switch_instance.chat_item_switch("");
        }
    }

    // 本機側停止失敗程序：取消失敗 → 不動作（維持原狀態；等 poll 反映實況）
    prompt_stop_error(ui_context, request_data, response_data) {
        // mini loading 收尾（失敗還原——狀態維持交 poll）
        ui_context.j_stop_button.removeClass("loading");
    }
}

export const prompt_stop = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_stop());
