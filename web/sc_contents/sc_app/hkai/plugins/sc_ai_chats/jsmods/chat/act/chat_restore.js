// jsmods/chat/act/chat_restore.js —— 還原對話協調（act 層——一檔一事）
// 職責：chat_restore（流程組裝：before_restore／success／error 三個回呼——連線交 req；本機側程序同檔）／chat_before_restore（送出前：按鈕載入中）／chat_restore_success（清單移除／瀏覽中被還原＝無痕轉向歸檔列面＋清單重取）／chat_restore_error（復原＋提示）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_restore {
    constructor() {}

    // 還原協調（由 clicked 呼叫——連線交 req；本機側程序喺本檔）
    async chat_restore(chat_id, j_chat_item) {
        const j_restore_button = j_chat_item.find("[data-action='chat_restore']");

        // 橋接駁（同域 req 橋——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_restore_instance = await chat_instance.req().chat_restore();

        const thisObject = this;

        chat_restore_instance.chat_restore({ chat_id : chat_id }, {
            before_restore: function () {
                thisObject.chat_before_restore({
                    j_restore_button : j_restore_button
                });
            },
            success: function (result) {
                thisObject.chat_restore_success({
                    j_chat_item : j_chat_item
                }, result);
            },
            error: function (result) {
                thisObject.chat_restore_error({
                    j_restore_button : j_restore_button
                }, result);
            }
        });
    }

    // 送出前程序：還原鈕顯示載入中
    async chat_before_restore(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_restore_button = ui_context.j_restore_button;

        loading.start_basic_loading(j_restore_button);
    }

    // 本機側成功程序：清單移除該列；瀏覽中嗰條被還原 → 無痕轉向歸檔列面（URL 由後端砌）＋清單重取（重畫高亮）
    async chat_restore_success(ui_context, result) {
        const j_chat_item = ui_context.j_chat_item;

        // 判定：被還原嘅係瀏覽中對話 → 列面移除該列＋無痕轉向歸檔列面＋清單重取
        if (j_chat_item.hasClass("sc_chat_card--active")) {
            j_chat_item.remove();

            sc_url.silent_redirect_url(result.next_url);

            // 橋接駁（同域 act——清單重取：URL 鏈語境冇換（archive→archive）唔會重取，呢度自己取）
            const chat_instance = await registry().sc_ai_chats().chat();
            const chat_list_fetch_instance = await chat_instance.act().chat_list_fetch();

            await chat_list_fetch_instance.chat_list_fetch();

            return;
        }

        j_chat_item.remove();
    }

    // 本機側失敗程序：按鈕復原＋提示
    async chat_restore_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();
        const i18n    = registry().sc_i18n();

        const j_restore_button = ui_context.j_restore_button;

        loading.stop_loading(j_restore_button);
        toastr.error(i18n.get("chat.restore_failed"));
    }
}

export const chat_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_restore());
