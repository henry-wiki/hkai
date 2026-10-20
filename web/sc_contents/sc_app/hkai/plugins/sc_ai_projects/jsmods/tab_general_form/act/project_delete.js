// jsmods/tab_general_form/act/project_delete.js —— 專案刪除協調（action 層——一 action 一檔）
// 職責：project_delete（callbacks 組裝 → project_delete_handler_instance.project_delete）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_delete {
    constructor() {}

    // 刪除協調（由 project_delete evt 呼叫——連線交 req；本機側同檔）
    async project_delete(project_slug, j_modal) {
        const j_delete_button = j_modal.find("[data-btn='project_delete']");


        // 橋接駁（同域 req 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_delete_handler_instance = await tab_general_form_instance.req().project_delete();

        const thisObject = this;

        project_delete_handler_instance.project_delete(project_slug, {
            before_delete: function () {
                thisObject.project_before_delete({
                    j_delete_button : j_delete_button
                });
            },
            success: function (result) {
                thisObject.project_delete_success({
                    j_modal      : j_modal,
                    project_slug : project_slug
                }, result);
            },
            error: function (result) {
                thisObject.project_delete_error({
                    j_delete_button : j_delete_button
                }, result);
            }
        });
    }

// 送出前程序：刪除鈕顯示載入中（文字保留）
    async project_before_delete(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_delete_button = ui_context.j_delete_button;

        loading.start_basic_loading(j_delete_button);
    }

    // 本機側成功程序：關閉 modal（由 modal 觸發）＋清單更新；移除嘅係當前聚焦專案 → 無痕轉 home
    async project_delete_success(ui_context, result) {
        // 橋接駁（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        const j_modal = ui_context.j_modal;

        // 由 modal 觸發 → 關閉 modal
        if (j_modal) {
            j_modal[0].modal_close();
        }

        // 判定：移除嘅＝當前聚焦專案（URL slug 相同——URL 為唯一真相）→ 無痕轉 home（唔留喺已刪專案嘅頁）
        if (ui_context.project_slug === project_instance.get_project_slug()) {
            sc_url.silent_redirect_url("/");
        }

        // 清單更新（DOM 項目移除——兩路皆行；重繪後 select slug＝URL 現況）
        project_list_fetch_instance.project_list_fetch();
    }

    // 本機側失敗程序：按鈕復原＋提示（非表單欄位錯誤）
    async project_delete_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_delete_button = ui_context.j_delete_button;

        loading.stop_loading(j_delete_button);
        toastr.error(result.error || "delete failed");
    }
}

export const project_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_delete());
