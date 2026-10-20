// jsmods/tab_general_form/act/project_archive.js —— 專案歸檔協調（action 層——一 action 一檔）
// 職責：project_archive（callbacks 組裝 → req 橋 project_archive；本機側同檔）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_archive {
    constructor() {}

    // 歸檔協調（由 project_archive evt 呼叫——連線交 req；本機側程序喺本檔）
    async project_archive(project_slug, j_modal) {
        const j_archive_button = j_modal.find("[data-btn='project_archive']");

        // 橋接駁（同域 req 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_archive_handler_instance = await tab_general_form_instance.req().project_archive();

        const thisObject = this;

        project_archive_handler_instance.project_archive(project_slug, {
            before_archive: function () {
                thisObject.project_before_archive({
                    j_archive_button : j_archive_button
                });
            },
            success: function (result) {
                thisObject.project_archive_success({
                    j_modal      : j_modal,
                    project_slug : project_slug
                }, result);
            },
            error: function (result) {
                thisObject.project_archive_error({
                    j_archive_button : j_archive_button
                }, result);
            }
        });
    }

    // 送出前程序：歸檔鈕顯示載入中（文字保留）
    async project_before_archive(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_archive_button = ui_context.j_archive_button;

        loading.start_basic_loading(j_archive_button);
    }

    // 本機側成功程序：關閉 modal（由 modal 觸發）＋清單更新；歸檔嘅係當前聚焦專案 → 無痕轉 home
    async project_archive_success(ui_context, result) {
        // 橋接駁（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        const j_modal = ui_context.j_modal;

        // 由 modal 觸發 → 關閉 modal
        if (j_modal) {
            j_modal[0].modal_close();
        }

        // 判定：歸檔嘅＝當前聚焦專案（URL slug 相同——URL 為唯一真相）→ 無痕轉 home（唔留喺已歸檔專案嘅頁）
        if (ui_context.project_slug === project_instance.get_project_slug()) {
            sc_url.silent_redirect_url("/");
        }

        // 清單更新（DOM 項目移除——兩路皆行；重繪後 select slug＝URL 現況）
        project_list_fetch_instance.project_list_fetch();
    }

    // 本機側失敗程序：按鈕復原＋提示（非表單欄位錯誤）
    async project_archive_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_archive_button = ui_context.j_archive_button;

        loading.stop_loading(j_archive_button);
        toastr.error(result.error || "archive failed");
    }
}

export const project_archive = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_archive());
