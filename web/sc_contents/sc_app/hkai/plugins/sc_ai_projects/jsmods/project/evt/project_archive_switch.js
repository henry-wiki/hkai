// jsmods/project/evt/project_archive_switch.js —— 歸檔語境切換 click 綁定（factory 單例——AJAX 無痕轉向；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派 .sc_statusbar--project .sc_statusbar__archive）② 動作（project_archive_switch——攞 href 交 sc_url 無痕轉向）
// 換 URL 由劫持派 on_url_change → 交 URL 鏈（清單語境重取／status bar 區重渲——全部喺鏈）
// 結構：eager 單例 closure factory（具名 export——域 init 動態 import 解構後呼叫 init()）

class _project_archive_switch {
    constructor() {}

    // 綁定註冊（由域 init 呼叫——一次；selector 委派——收窄 project 區，唔會搶 chat 區嘅歸檔掣）
    init() {
        // 切換歸檔語境（.sc_statusbar--project .sc_statusbar__archive——入歸檔／離開歸檔；不整頁重導）
        jQuery(document)
            .off("click.sc_ai_projects_archive_switch")
            .on("click.sc_ai_projects_archive_switch", ".sc_statusbar--project .sc_statusbar__archive", this.project_archive_switch.bind(this));
    }

    // 切換歸檔語境（換 URL——無痕轉向加 history 記錄；反應交由 URL 鏈統一處理）
    project_archive_switch(event) {
        event.preventDefault();

        const j_archive_link = jQuery(event.currentTarget);

        sc_url.silent_redirect_url(j_archive_link.attr("href"));
    }
}

export const project_archive_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_archive_switch());
