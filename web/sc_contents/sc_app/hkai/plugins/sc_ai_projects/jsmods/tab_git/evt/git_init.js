// jsmods/tab_git/evt/git_init.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（git_init——git init（Git tab））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _git_init {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 執行 git init（Git tab）
        jQuery(document)
            .off("click.sc_ai_projects_git_init")
            .on("click.sc_ai_projects_git_init", "[data-btn='git_init']", this.git_init.bind(this));
    }

    // 執行 git init（交 git_init()）
    async git_init(event) {
        const j_git_init_button = jQuery(event.currentTarget);
        const j_modal = j_git_init_button.closest(".modal__overlay");

        // 橋接駁（同域 act 橋——新方式）
        const tab_git_instance = await registry().sc_ai_projects().tab_git();
        const git_init_instance = await tab_git_instance.act().git_init();

        git_init_instance.git_init(j_modal, j_git_init_button);
    }
}

export const git_init = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_init());
