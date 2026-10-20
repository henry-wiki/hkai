// jsmods/chat/act/chat_delete.js —— 刪除對話協調（act 層——一檔一事）
// 職責：chat_delete（流程組裝：before_delete／success／error 三個回呼——連線交 handler；本機側程序同檔）／chat_before_delete（送出前：按鈕載入中）／chat_delete_success（清單移除／作用中無痕切走）／chat_delete_error（復原＋提示）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_delete {
    constructor() {}

    // 刪除協調（由 clicked_chat_delete 呼叫——連線交 handler；本機側程序喺本檔）
    async chat_delete(chat_id, j_chat_item) {
        const j_delete_button = j_chat_item.find("[data-action='chat_delete']");

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_delete_instance = await chat_instance.req().chat_delete();

        const thisObject = this;

        chat_delete_instance.chat_delete({ chat_id : chat_id }, {
            before_delete: function () {
                thisObject.chat_before_delete({
                    j_delete_button : j_delete_button
                });
            },
            success: function (result) {
                thisObject.chat_delete_success({
                    j_chat_item : j_chat_item
                }, result);
            },
            error: function (result) {
                thisObject.chat_delete_error({
                    j_delete_button : j_delete_button
                }, result);
            }
        });
    }

    // 送出前程序：刪除鈕顯示載入中
    async chat_before_delete(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_delete_button = ui_context.j_delete_button;

        loading.start_basic_loading(j_delete_button);
    }

    // 本機側成功程序：清單移除該列；作用中對話已刪 → 無痕切走，URL 同畫面一齊換
    async chat_delete_success(ui_context, result) {
        const j_chat_item = ui_context.j_chat_item;

        // 判定：被刪嘅係作用中對話 → 清單移除該列＋無痕切走（唔可以留喺已刪嘅對話上面）
        if (j_chat_item.hasClass("sc_chat_card--active")) {
            // 清單移除該列（切換唔再重繪清單——本機自己落手）
            j_chat_item.remove();

            // 橋接駁（同域 act——新方式）
            const chat_instance = await registry().sc_ai_chats().chat();
            const chat_item_switch_instance = await chat_instance.act().chat_item_switch();

            await chat_item_switch_instance.chat_item_switch(result.next_chat_sid);

            return;
        }

        j_chat_item.remove();
    }

    // 本機側失敗程序：按鈕復原＋提示
    async chat_delete_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();
        const i18n    = registry().sc_i18n();

        const j_delete_button = ui_context.j_delete_button;

        loading.stop_loading(j_delete_button);
        toastr.error(i18n.get("chat.delete_failed"));
    }
}

export const chat_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_delete());
