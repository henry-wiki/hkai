// jsmods/project/act/project_restore.js —— 還原專案協調（action 層——一 action 一檔）
// 職責：project_restore（callbacks 組裝 → req 橋 project_restore；本機側同檔）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_restore {
    constructor() {}

    // 還原協調（由 project_restore evt 呼叫——連線交 req；本機側程序喺本檔）
    async project_restore(project_slug, j_modal) {
        const j_restore_button = j_modal.find("[data-btn='project_restore']");

        // 橋接駁（同域 req 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_restore_handler_instance = await project_instance.req().project_restore();

        const thisObject = this;

        project_restore_handler_instance.project_restore({ project_slug : project_slug }, {
            before_restore: function () {
                thisObject.project_before_restore({
                    j_restore_button : j_restore_button
                });
            },
            success: function (result) {
                thisObject.project_restore_success({
                    j_modal      : j_modal,
                    project_slug : project_slug
                }, result);
            },
            error: function (result) {
                thisObject.project_restore_error({
                    j_restore_button : j_restore_button
                }, result);
            }
        });
    }

    // 送出前程序：還原鈕顯示載入中
    async project_before_restore(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_restore_button = ui_context.j_restore_button;

        loading.start_basic_loading(j_restore_button);
    }

    // 本機側成功程序：關閉 modal（由 modal 觸發）＋清單更新；URL 仲係該專案歸檔形 → 無痕轉純形
    async project_restore_success(ui_context, result) {
        // 橋接接（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        const j_modal = ui_context.j_modal;

        // 由 modal 觸發 → 關閉 modal
        if (j_modal) {
            j_modal[0].modal_close();
        }

        // 判定：URL 第一段係該專案嘅歸檔形（archive@<project_slug>——還原後 URL 形跟返狀態）→ 無痕轉純形
        if (project_instance.get_project_slug() === "archive@" + ui_context.project_slug) {
            sc_url.silent_redirect_url("/" + ui_context.project_slug + "/");
        }

        // 清單更新（重繪後 select slug＝URL 現況）
        project_list_fetch_instance.project_list_fetch();
    }

    // 本機側失敗程序：按鈕復原＋提示（非表單欄位錯誤）
    async project_restore_error(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();
        const i18n    = registry().sc_i18n();

        const j_restore_button = ui_context.j_restore_button;

        loading.stop_loading(j_restore_button);
        toastr.error(i18n.get("project.restore_failed"));
    }
}

export const project_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_restore());
