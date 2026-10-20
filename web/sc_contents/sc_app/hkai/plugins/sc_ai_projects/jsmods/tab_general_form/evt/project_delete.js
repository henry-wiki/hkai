// jsmods/tab_general_form/evt/project_delete.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（project_delete——移除專案（先確認））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）


class _project_delete {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 移除專案（刪除 tab footer）
        jQuery(document)
            .off("click.sc_ai_projects_delete")
            .on("click.sc_ai_projects_delete", "[data-btn='project_delete']", this.project_delete.bind(this));
    }

    // 移除專案（表單內——先確認，讀 data-confirm_msg，[[project_name]] 替換）
    async project_delete(event) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_delete_button = jQuery(event.currentTarget);
        const j_modal = j_delete_button.closest(".modal__overlay");

        // 專案名（掣上 data-project_name——供確認訊息 [[project_name]] 替換）
        const replacements = {};
        replacements["project_name"] = j_delete_button.attr("data-project_name");

        // 判定：使用者未確認 → 不動作
        if (!await shared.sc_web_utils().is_confirmed(j_delete_button, "data-confirm_msg", replacements)) {
            return;
        }

        // 直接讀 modal 上的 data-project_slug
        const project_slug = j_modal.attr("data-project_slug");

        // 橋接駁（同域 act 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_delete_instance = await tab_general_form_instance.act().project_delete();

        project_delete_instance.project_delete(project_slug, j_modal);
    }
}

export const project_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_delete());
