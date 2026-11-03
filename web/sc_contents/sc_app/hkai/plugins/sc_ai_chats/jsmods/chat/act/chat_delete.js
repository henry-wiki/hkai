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

        const target_chat_sid = String(j_chat_item.attr("data-chat_sid") || "");

        // 橋接駁（同域 act——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();

        // 判定：目標就係目前瀏覽緊嘅對話 → 轉去專案根（清單移除＋訊息窗清空＋URL 換）
        if (target_chat_sid !== "" && target_chat_sid === chat_instance.get_chat_sid()) {
            j_chat_item.remove();

            const project_instance = await registry().sc_ai_projects().project();
            const project_slug = project_instance.get_project_slug();

            sc_url.shallow_replace_url("/" + project_slug + "/");

            const chat_item_switch_instance = await chat_instance.act().chat_item_switch();
            await chat_item_switch_instance.chat_item_switch("");

            const message_instance = await registry().sc_ai_chats().message();
            const message_list_fetch_instance = await message_instance.act().message_list_fetch();
            await message_list_fetch_instance.message_list_fetch();

            const prompt_instance = await registry().sc_ai_chats().prompt();
            const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();
            await prompt_fetch_instance.prompt_fetch();

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
