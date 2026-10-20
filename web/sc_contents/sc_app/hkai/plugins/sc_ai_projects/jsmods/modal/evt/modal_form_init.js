// jsmods/modal/evt/modal_form_init.js —— modal 表單事件匯總（factory 單例——開咗 modal 先有）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（input_form_fields＝指定欄位輸入 → 排程驗證）
// 檔名＝DevTools Event Listeners 面板顯示嘅字；表單事件集中處——分唔出獨立檔嘅表單事件一律上呢度
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _modal_form_init {
    constructor() {
        // 表單驗證排程 timer（debounce——輸入停咗先驗）
        this.project_form_validation_timer_id = null;
    }

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 指定欄位更新（後端會驗嘅先綁：路徑＝存在？）→ 排程驗證（每個目標改動＝一次呼叫）
        jQuery(document)
            .off("input.project_modal_path change.project_modal_path")
            .on("input.project_modal_path change.project_modal_path", ".modal__overlay [data-field='project_path']"
                , this.input_form_fields.bind(this));
    }

    // 指定欄位更新：驗證（debounce 300ms——輸入停咗先驗：正確？存在？後台接受？）
    async input_form_fields(event) {
        const j_field = jQuery(event.currentTarget);
        const j_modal = j_field.closest(".modal__overlay");

        // 橋接駁（同域 act 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const verify_project_data_instance = await tab_general_form_instance.act().verify_project_data();

        // 清掉上一個排程 → 重新排
        if (this.project_form_validation_timer_id) {
            clearTimeout(this.project_form_validation_timer_id);
        }

        this.project_form_validation_timer_id = setTimeout(function () {
            verify_project_data_instance.verify_project_data(j_modal);
        }, 300);
    }
}

export const modal_form_init = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_form_init());
