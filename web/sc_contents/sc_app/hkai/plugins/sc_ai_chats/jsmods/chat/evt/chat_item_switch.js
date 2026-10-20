// jsmods/chat/evt/chat_item_switch.js —— 切換對話 click 綁定（factory 單例——AJAX 切換不整頁重導；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（chat_item_switch——讀 data-chat_sid 交 action）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _chat_item_switch {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 切換對話（.sc_chat_list .sc_chat_card__link——不整頁重導）
        jQuery(document)
            .off("click.sc_ai_chats_chat_item_switch")
            .on("click.sc_ai_chats_chat_item_switch", ".sc_chat_list .sc_chat_card", this.chat_item_switch.bind(this));
    }

    // 切換對話（交 act/chat——換 URL＋換主）
    async chat_item_switch(event) {
        event.preventDefault();

        const j_chat_link = jQuery(event.currentTarget);

        // 目標（卡帶 data-chat_sid——歸檔＝archive@{sid}／正常＝純值；由後台出，前端零拆解連結）
        const chat_sid = j_chat_link.attr("data-chat_sid");

        // 換 URL（無痕轉向——加 history 記錄；換完由劫持派 on_url_change → 交 URL 鏈）
        sc_url.silent_redirect_url(j_chat_link.attr("href"));

        // 派發 chat_item_switch hook（before 相位——切換前；跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_before("chat_item_switch", [chat_sid]);

        // 橋接駁（同域 act——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_switch_instance = await chat_instance.act().chat_item_switch();

        chat_item_switch_instance.chat_item_switch(chat_sid);

        // 切換完成 → 派發 chat_item_switch hook（after 相位——跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_after("chat_item_switch", [chat_sid]);
    }
}

export const chat_item_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_item_switch());
