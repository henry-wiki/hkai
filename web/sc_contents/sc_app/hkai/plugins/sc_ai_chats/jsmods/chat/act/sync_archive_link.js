// jsmods/chat/act/sync_archive_link.js —— 歸檔連結同步（本機側動作——act 層；一檔一事）
// 職責：sync_archive_link（URL 變化後——依現時語境翻面 status bar 歸檔連結：歸檔語境＝離開歸檔（去專案根）／普通＝入歸檔）
// 連結兩向 url／標題／圖示都由後端砌（data 對）——本檔只揀邊一對，唔自組
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _sync_archive_link {
    constructor() {}

    // 同步歸檔連結（href／title 由 data 對取值——入／離歸檔兩向）
    async sync_archive_link(goto_url) {
        const j_archive_link = jQuery(".sc_statusbar__archive");

        // 判定：status bar 冇歸檔連結（無專案語境時後端唔渲染）→ 唔做
        if (j_archive_link.length === 0) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();

        const curr_context = chat_instance.get_chat_list_context_from_url(goto_url);

        // 判定：歸檔語境（archive／archive@<chat_sid>）→ 翻去「離開歸檔」向（箭頭圖示）
        if (curr_context === "archive") {
            j_archive_link.attr("href", j_archive_link.attr("data-href_leave"));
            j_archive_link.attr("title", j_archive_link.attr("data-title_leave"));
            j_archive_link.find("i").attr("class", j_archive_link.attr("data-icon_leave"));
            return;
        }

        // 普通語境 → 翻去「入歸檔」向（盒仔圖示）
        j_archive_link.attr("href", j_archive_link.attr("data-href_enter"));
        j_archive_link.attr("title", j_archive_link.attr("data-title_enter"));
        j_archive_link.find("i").attr("class", j_archive_link.attr("data-icon_enter"));
    }
}

export const sync_archive_link = (function (instance) {
    return function () {

        return instance;
    };
})(new _sync_archive_link());
