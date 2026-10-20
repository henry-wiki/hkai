// jsmods/tab_general_form/act/project_add.js —— 新增專案協調（action 層——一 action 一檔）
// 職責：project_add（連線交 req；本機側程序同檔——送出＋回呼）
// 結構同 evt 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_add {
    constructor() {}

    // 新增協調（由 project_add evt 呼叫——連線交 req；本機側同檔）
    async project_add(form_data, j_modal) {
        const j_save_button = j_modal.find("[data-btn='project_add']");


        // 橋接駁（同域 req 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const project_add_handler_instance = await tab_general_form_instance.req().project_add();

        const thisObject = this;

        project_add_handler_instance.project_add(form_data, {
            before_save: function () {
                thisObject.project_before_add({
                    j_save_button : j_save_button
                });
            },
            success: function (result) {
                thisObject.project_add_success({
                    j_save_button : j_save_button,
                    j_modal       : j_modal
                }, result);
            },
            error: function (result) {
                thisObject.project_add_error({
                    j_save_button : j_save_button,
                    j_modal       : j_modal
                }, result);
            }
        });
    }

// 送出前程序：儲存鈕顯示載入中（文字保留）
    async project_before_add(ui_context) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_save_button = ui_context.j_save_button;

        loading.start_basic_loading(j_save_button);
    }

    // 本機側成功程序：通知「專案已建立」（撳建立嘅一刻——顯示時機＝呢個 callback）＋關 modal＋切去新專案頁（silent——零重整）
    async project_add_success(ui_context, result) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 橋接駁（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_fetch_instance = await project_instance.act().project_list_fetch();

        const j_save_button = ui_context.j_save_button;
        const j_modal       = ui_context.j_modal;

        loading.stop_loading(j_save_button);

        // 建立成功嘅一刻：通知（唔經 URL、唔喺頁面載入時出）
        toastr.success("專案已建立");

        // 由 modal 觸發 → 關閉 modal（跟 delete 側慣例）
        if (j_modal) {
            j_modal[0].modal_close();
        }

        // 切去新專案頁（清單重繪——同側欄點擊切換同一條路）
        await project_list_fetch_instance.switch(result.project_slug);
    }

    // 本機側失敗程序：按鈕復原＋標準表單錯誤套用（modal 保持開啟）＋toast 提示
    async project_add_error(ui_context, result) {
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

export const project_add = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_add());
