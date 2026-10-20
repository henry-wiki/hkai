// jsmods/tab_general_form/evt/project_archive.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（project_archive——歸檔專案（先確認））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）


class _project_archive {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 歸檔專案（表單底）
        jQuery(document)
            .off("click.sc_ai_projects_archive")
            .on("click.sc_ai_projects_archive", "[data-btn='project_archive']", this.project_archive.bind(this));
    }

    // 歸檔專案（表單內——先確認，讀 data-confirm_msg，[[project_name]] 替換）
    async project_archive(event) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_archive_button = jQuery(event.currentTarget);
        const j_modal = j_archive_button.closest(".modal__overlay");

        // 專案資料取自表單欄位（供確認訊息替換）
        const replacements = {};
        replacements["project_name"] = j_modal.find("[data-field='project_name']").val();

        // 判定：使用者未確認 → 不動作
        if (!await shared.sc_web_utils().is_confirmed(j_archive_button, "data-confirm_msg", replacements)) {
            return;
        }

        // 直接讀 modal 上的 data-project_slug
        const project_slug = j_modal.attr("data-project_slug");

        // 橋接駁（同域 act 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_archive_instance = await tab_general_form_instance.act().project_archive();

        project_archive_instance.project_archive(project_slug, j_modal);
    }
}

export const project_archive = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_archive());
