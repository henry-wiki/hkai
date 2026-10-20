// jsmods/tab_environment/act/update_tab_environment.js —— 更新環境（action 層——一 action 一檔）
// 職責：update_tab_environment（按鈕載入中 → req update_tab_environment 出街 → 成功重載當前 tab）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _update_tab_environment {
    constructor() {}

    // 更新環境（後端強制重新偵測＋寫專案根 ENVIRONMENT.md；成功＝同「重新整理」一樣——觸發一次當前 tab 切換）
    async update_tab_environment(j_modal, j_button) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 準備資料（project_slug 由 modal DOM 帶——同 save_tab_rule 來源一致）
        const project_slug = j_modal.attr("data-project_slug") || "";

        // 橋接駁（同域 req 橋——新方式）
        const tab_environment_instance = await registry().sc_ai_projects().tab_environment();
        const update_tab_environment_req_instance = await tab_environment_instance.req().update_tab_environment();

        // 標準：fetch 期間按鈕顯示載入中（文字保留）
        loading.start_basic_loading(j_button);

        await update_tab_environment_req_instance.update_tab_environment(project_slug, {
            before_load: function () {

            },
            success: async function () {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 成功 → 按鈕復原＋同「重新整理」一樣：當前 tab 再觸發一次 click（自己載返自己）
                loading.stop_loading(j_button);
                j_modal.find(".modal_tab.on").trigger("click");
            },
            error: async function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 失敗 → 按鈕復原＋報錯（後端訊息優先）
                loading.stop_loading(j_button);

                let error_message = "update environment failed";

                // 判定：後端帶咗訊息 → 用後端嗰句
                if (result && result.error) {
                    error_message = result.error;
                }

                toastr.error(error_message);
            }
        });
    }
}

export const update_tab_environment = (function (instance) {
    return function () {

        return instance;
    };
})(new _update_tab_environment());
