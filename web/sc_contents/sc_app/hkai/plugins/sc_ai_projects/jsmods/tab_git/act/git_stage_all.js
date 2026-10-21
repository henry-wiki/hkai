// jsmods/tab_git/act/git_stage_all.js —— 執行加入準備區（action 層——一 action 一檔）
// 職責：git_stage_all（按鈕載入中 → req git_stage_all 出街 → 成功重載當前 tab）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _git_stage_all {
    constructor() {}

    // 加入準備區（走 git 域 git_stage_all 端點＝git add -A；成功＝同「重新整理」一樣——觸發一次當前 tab 切換）
    async git_stage_all(j_modal, j_button) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 準備資料（project_slug 由 modal DOM 帶——同 git_init 來源一致）
        const project_slug = j_modal.attr("data-project_slug") || "";

        // 橋接駁（同域 req 橋——新方式）
        const tab_git_instance = await registry().sc_ai_projects().tab_git();
        const git_stage_all_instance = await tab_git_instance.req().git_stage_all();

        // 標準：fetch 期間按鈕顯示載入中（文字保留）
        loading.start_basic_loading(j_button);

        await git_stage_all_instance.git_stage_all(project_slug, {
            before_load: function () {

            },
            success: async function () {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 成功 → 按鈕復原＋重載當前 tab（狀態即刻反映）
                loading.stop_loading(j_button);
                const tab_id = j_modal.find(".modal_tab.on").attr("data-tab_id") || "git";

                // 直接重載當前 tab（唔靠合成 click——合成事件喺 modal_open 內未必接到）
                const modal_instance = await registry().sc_ai_projects().modal();
                const modal_open_instance = await modal_instance.act().modal_open();

                await modal_open_instance.modal_open(project_slug, tab_id, {});
            },
            error: async function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 失敗 → 按鈕復原＋報錯（後端訊息優先）
                loading.stop_loading(j_button);

                let error_message = "git stage all failed";

                // 判定：後端帶咗訊息 → 用後端嗰句
                if (result && result.error) {
                    error_message = result.error;
                }

                toastr.error(error_message);
            }
        });
    }
}

export const git_stage_all = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_stage_all());
