// jsmods/tab_git/act/git_init.js —— 執行 git init（action 層——一 action 一檔）
// 職責：git_init（按鈕載入中 → handler git_init → 成功重載當前 tab）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _git_init {
    constructor() {}

    // 執行 git init（走 git 域 git_init 端點；成功＝同「重新整理」一樣——觸發一次當前 tab 切換）
    async git_init(j_modal, j_button) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const project_slug = j_modal.attr("data-project_slug") || "";

        // 橋接駁（同域 req 橋——新方式）
        const tab_git_instance = await registry().sc_ai_projects().tab_git();
        const git_init_instance = await tab_git_instance.req().git_init();

        // 標準：fetch 期間按鈕顯示載入中（文字保留）
        loading.start_basic_loading(j_button);

        await git_init_instance.git_init(project_slug, {
            before_load: function () {},
            success: async function () {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 成功 → 按鈕復原＋同「重新整理」一樣：當前 tab 再觸發一次 click（自己載返自己）
                loading.stop_loading(j_button);
                j_modal.find(".modal_tab.on").trigger("click");
            },
            error: async function () {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                loading.stop_loading(j_button);
                toastr.error("git init failed");
            }
        });
    }
}

export const git_init = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_init());
