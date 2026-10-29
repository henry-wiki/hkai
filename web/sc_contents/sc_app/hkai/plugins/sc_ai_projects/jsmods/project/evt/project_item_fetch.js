// jsmods/project/evt/project_item_fetch.js —— sidebar 側 click 綁定（factory 單例——唔使開 modal；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（project_item_fetch——換 URL（無痕）＋交 act 換主）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_panel_click 動態 import 解構後呼叫 init()）

class _project_item_fetch {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 切換專案（清單連結）
        jQuery(document)
            .off("click.sc_ai_projects_item_fetch")
            .on("click.sc_ai_projects_item_fetch", ".sc_project_card", this.project_item_fetch.bind(this));
    }

    // 切換專案（只換 URL——換主交 URL 鏈 detect_project_slug_change，避免重複請求）
    project_item_fetch(event) {
        event.preventDefault();

        const j_project_link = jQuery(event.currentTarget);

        // 換 URL（無痕轉向——加 history 記錄；換完由劫持派 on_url_change → detect_project_slug_change 接手換主）
        sc_url.silent_redirect_url(j_project_link.attr("href"));
    }
}

export const project_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_item_fetch());
