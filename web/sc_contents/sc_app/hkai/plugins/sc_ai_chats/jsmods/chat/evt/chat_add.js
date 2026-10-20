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

        // 橋接駁（兩域——目標 URL 即場由域 getter 砌，唔靠按鈕 href：切換專案後側欄 header 唔重繪，href 會過期）
        const project_instance = await registry().sc_ai_projects().project();
        const chat_instance    = await registry().sc_ai_chats().chat();

        const project_slug = project_instance.get_project_slug();
        const chat_sid     = "";
        const chat_url     = chat_instance.build_chat_url(project_slug, chat_sid);

        // 換 URL（無痕轉向——加 history 記錄；換完由劫持派 on_url_change → 交 URL 鏈）
        sc_url.silent_redirect_url(chat_url);

        // 派發 chat_item_switch hook（before 相位——切換前；跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_before("chat_item_switch", [chat_sid]);

        // 橋接駁（同域 act——新方式）
        const chat_start_new_instance = await chat_instance.act().chat_start_new();

        chat_start_new_instance.chat_start_new();

        // 切換完成 → 派發 chat_item_switch hook（after 相位——跨 plugin 用家各自嘅反應）
        registry().sc_ai_chats().execute_hook_after("chat_item_switch", [chat_sid]);
    }
}

export const chat_add = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_add());
