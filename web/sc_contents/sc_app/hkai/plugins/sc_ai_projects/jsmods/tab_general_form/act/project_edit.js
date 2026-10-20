// jsmods/tab_general_form/act/project_edit.js —— 編輯專案協調（action 層——一 action 一檔）
// 職責：project_edit（連線交 req；本機側程序同檔——送出＋回呼）
// 結構同 evt 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_edit {
    constructor() {}

    // 編輯協調（由 project_edit evt 呼叫——連線交 req；本機側同檔）
    async project_edit(form_data, j_modal) {
        const j_save_button = j_modal.find("[data-btn='project_edit']");


        // 橋接駁（同域 req 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_edit_handler_instance = await tab_general_form_instance.req().project_edit();

        const thisObject = this;

        project_edit_handler_instance.project_edit(form_data, {
            before_save: function () {
                thisObject.project_before_edit({
                    j_save_button : j_save_button
                });
            },
            success: function (result) {
                thisObject.project_edit_success({
                    j_save_button : j_save_button
                }, result);
            },
            error: function (result) {
                thisObject.project_edit_error({
                    j_save_button : j_save_button,
                    j_modal       : j_modal
                }, result);
            }
        });
    }

// 送出前程序：儲存鈕顯示載入中（文字保留）
    async project_before_edit(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_save_button = ui_context.j_save_button;

        loading.start_basic_loading(j_save_button);
    }

    // 本機側成功程序：按鈕復原＋toast；不關 modal；局部更新清單
    async project_edit_success(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 橋接駁（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        const j_save_button = ui_context.j_save_button;

        loading.stop_loading(j_save_button);
        project_list_fetch_instance.project_list_fetch();
        toastr.success("已儲存");
    }

    // 本機側失敗程序：按鈕復原＋標準表單錯誤套用（modal 保持開啟）＋toast 提示
    async project_edit_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();
        const shared = registry().shared();

        const j_save_button = ui_context.j_save_button;
        const j_modal       = ui_context.j_modal;

        loading.stop_loading(j_save_button);
        shared.sc_web_utils().apply_form_errors(j_modal, result.errors);
        toastr.error(result.error || "儲存失敗，請檢查欄位");
    }

}

export const project_edit = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_edit());
