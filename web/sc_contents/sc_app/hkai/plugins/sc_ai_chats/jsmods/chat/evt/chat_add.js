// jsmods/chat/evt/chat_add.js —— 新增對話 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派 [data-btn='chat_add']）② 動作（chat_add——進新對話模式）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _chat_add {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 新增對話
        jQuery(document)
            .off("click.sc_ai_chats_add_chat")
            .on("click.sc_ai_chats_add_chat", "[data-btn='chat_add']", this.chat_add.bind(this));
    }

    // 新增對話（data-btn='chat_add'（側欄 header ＋ 掣）→ 進新對話模式：切去空 chat_sid（URL＝專案根）；首次送出時才建立新對話）
    async chat_add(event) {
        event.preventDefault();

        // 橋接駁（同域——新對話流程交 chat_start_new；URL 由後端派 sid 後才寫）
        const chat_instance = await registry().sc_ai_chats().chat();

        const chat_sid = "";

        // 派發 chat_item_switch hook（before 相位——切換前；跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_before("chat_item_switch", [chat_sid]);

        // 新對話（切空語境 → 向後端取新 prompt → URL 跟隨後端 sid）
        const chat_start_new_instance = await chat_instance.act().chat_start_new();

        await chat_start_new_instance.chat_start_new();

        // 切換完成 → 派發 chat_item_switch hook（after 相位——跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_after("chat_item_switch", [chat_sid]);
    }
}

export const chat_add = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_add());
