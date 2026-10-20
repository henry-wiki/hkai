// jsmods/sc_sidebar/act/opened_dbviewer.js —— 資料庫 lightbox（act 層——一檔一事）
// 職責：opened_dbviewer（rail 資料庫掣嘅工作——sc_modal 框＋iframe 全填；連結同標題由後台掣上出）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _opened_dbviewer {
    constructor() {}

    // 開 lightbox（sc_modal 框＋iframe → /dbviewer；Adminer 原版照跑）
    async opened_dbviewer(j_button) {
        // 動態載入（registry——用先載）
        const sc_modal = registry().sc_modal();

        const dbviewer_src   = j_button.attr("data-src");
        const dbviewer_title = j_button.attr("title");

        // 建立 modal（header 只填標題——保留關閉掣；body＝iframe）
        const j_modal = await sc_modal.create_modal({
            modal_id: "dbviewer_modal",
            header: function (j_modal_header) {
                j_modal_header.find(".modal__title").text(dbviewer_title);
            },
            body: "<iframe class='sc_dbviewer__frame' src='" + dbviewer_src + "'></iframe>",
        });

        j_modal.addClass("visible");
    }
}

export const opened_dbviewer = (function (instance) {
    return function () {

        return instance;
    };
})(new _opened_dbviewer());
