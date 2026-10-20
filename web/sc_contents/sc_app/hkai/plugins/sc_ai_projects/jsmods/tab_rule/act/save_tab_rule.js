// jsmods/tab_rule/act/save_tab_rule.js —— 儲存規則（action 層——一 action 一檔）
// 職責：save_tab_rule（按鈕載入中 → req save_tab_rule 出街 → 成功重載當前 tab）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _save_tab_rule {
    constructor() {}

    // 儲存規則（編輯器現值 → 寫專案根 AGENTS.md；成功＝同「重新整理」一樣——觸發一次當前 tab 切換）
    async save_tab_rule(j_modal, j_button) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 準備資料（project_slug 由 modal DOM 帶——同 git_init 來源一致）
        const project_slug = j_modal.attr("data-project_slug") || "";

        // 開啟版本更新時間（由規則 pane 帶——送出比對基準；缺＝空字串）
        const opened_update_time = j_modal.find(".modal_pane[data-pane='rule'] .instructions-editor").attr("data-rule_update_time") || "";

        // 橋接駁（同域 act 橋＋req 橋——新方式）
        const tab_rule_instance          = await registry().sc_ai_projects().tab_rule();
        const rule_editor_instance       = await tab_rule_instance.act().rule_editor();
        const save_tab_rule_req_instance = await tab_rule_instance.req().save_tab_rule();

        const content = await rule_editor_instance.read_rule_content();

        // 標準：fetch 期間按鈕顯示載入中（文字保留）
        loading.start_basic_loading(j_button);

        await save_tab_rule_req_instance.save_tab_rule(project_slug, content, opened_update_time, {
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

                let error_message = "save rule failed";

                // 判定：後端帶咗訊息 → 用後端嗰句
                if (result && result.error) {
                    error_message = result.error;
                }

                toastr.error(error_message);
            }
        });
    }
}

export const save_tab_rule = (function (instance) {
    return function () {

        return instance;
    };
})(new _save_tab_rule());
