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

    // 切換專案（換 URL（無痕）——換完由劫持派 on_url_change → 交 URL 鏈；換主交 act/project）
    async project_item_fetch(event) {
        event.preventDefault();

        const j_project_link = jQuery(event.currentTarget);

        // 目標（卡帶 data-project_slug——歸檔＝archive@{project_slug}／正常＝純值；由後台出，前端零拆解連結）
        const project_slug = j_project_link.attr("data-project_slug");

        // 換 URL（無痕轉向——加 history 記錄；換完由劫持派 on_url_change → 交 URL 鏈）
        sc_url.silent_redirect_url(j_project_link.attr("href"));

        // 橋接駁（同域 act——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_item_fetch_instance = await project_instance.act().project_item_fetch();

        project_item_fetch_instance.switch_project(project_slug);
    }
}

export const project_item_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_item_fetch());
