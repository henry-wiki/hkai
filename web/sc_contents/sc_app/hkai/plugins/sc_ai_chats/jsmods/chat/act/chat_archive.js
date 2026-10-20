// jsmods/chat/act/chat_archive.js —— 歸檔對話協調（act 層——一檔一事）
// 職責：chat_archive（流程組裝：before_archive／success／error 三個回呼——連線交 req；本機側程序同檔）／chat_before_archive（送出前：按鈕載入中）／chat_archive_success（清單移除／作用中無痕切走）／chat_archive_error（復原＋提示）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_archive {
    constructor() {}

    // 歸檔協調（由 clicked_chat_archive 呼叫——連線交 req；本機側程序喺本檔）
    async chat_archive(chat_id, j_chat_item) {
        const j_archive_button = j_chat_item.find("[data-action='chat_archive']");

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_archive_instance = await chat_instance.req().chat_archive();

        const thisObject = this;

        chat_archive_instance.chat_archive({ chat_id : chat_id }, {
            before_archive: function () {
                thisObject.chat_before_archive({
                    j_archive_button : j_archive_button
                });
            },
            success: function (result) {
                thisObject.chat_archive_success({
                    j_chat_item : j_chat_item
                }, result);
            },
            error: function (result) {
                thisObject.chat_archive_error({
                    j_archive_button : j_archive_button
                }, result);
            }
        });
    }

    // 送出前程序：歸檔鈕顯示載入中
    async chat_before_archive(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_archive_button = ui_context.j_archive_button;

        loading.start_basic_loading(j_archive_button);
    }

    // 本機側成功程序：清單移除該列；作用中對話已歸檔 → 無痕切走，URL 同畫面一齊換
    async chat_archive_success(ui_context, result) {
        const j_chat_item = ui_context.j_chat_item;

        // 判定：被歸檔嘅係作用中對話 → 清單移除該列＋無痕切走（唔可以留喺已歸檔嘅對話上面）
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
    async chat_archive_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();
        const i18n    = registry().sc_i18n();

        const j_archive_button = ui_context.j_archive_button;

        loading.stop_loading(j_archive_button);
        toastr.error(i18n.get("chat.archive_failed"));
    }
}

export const chat_archive = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_archive());
