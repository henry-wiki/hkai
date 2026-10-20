// jsmods/tab_general_form/evt/project_add.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（project_add——建立專案（footer；送出））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _project_add {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 建立專案（表單 footer）
        jQuery(document)
            .off("click.sc_ai_projects_project_add")
            .on("click.sc_ai_projects_project_add", "[data-btn='project_add']", this.project_add.bind(this));
    }

    // 建立專案：整包表單 → 交 project_add() 送出（驗證一律後端）
    async project_add(event) {
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
        const project_add_instance = await tab_general_form_instance.act().project_add();

        project_add_instance.project_add(form_data, j_modal);
    }
}

export const project_add = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_add());
