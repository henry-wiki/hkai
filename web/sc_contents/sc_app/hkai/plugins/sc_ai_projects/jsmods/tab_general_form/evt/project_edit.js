// jsmods/tab_general_form/evt/project_edit.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（project_edit——儲存（footer；送出））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _project_edit {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 儲存（表單 footer）
        jQuery(document)
            .off("click.sc_ai_projects_project_edit")
            .on("click.sc_ai_projects_project_edit", "[data-btn='project_edit']", this.project_edit.bind(this));
    }

    // 儲存：整包表單 → 交 project_edit() 送出（驗證一律後端）
    async project_edit(event) {
        const j_save_button = jQuery(event.currentTarget);
        const j_modal = j_save_button.closest(".modal__overlay");

        // 判定：modal 未開（搵唔到 modal 元素）→ 唔讀 slug、唔送
        if (j_modal.length === 0) {
            return;
        }

        // 表單整包（project_slug＝由 modal 元素直接讀 attr；serialize＝name='formdata[x]' 字串——後台收到再解）
        const form_data = {};
        form_data["project_slug"] = j_modal.attr("data-project_slug") || "";
        form_data["serialize"] = j_modal.find("*").serialize();

        // 橋接駁（同域 act 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_edit_instance = await tab_general_form_instance.act().project_edit();

        project_edit_instance.project_edit(form_data, j_modal);
    }
}

export const project_edit = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_edit());
