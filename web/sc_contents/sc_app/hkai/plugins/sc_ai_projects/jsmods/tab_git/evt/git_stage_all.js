// jsmods/tab_git/evt/git_stage_all.js —— 加入準備區綁定（evt 層——事件入口）
// 職責：git_stage_all（[data-btn='git_stage_all'] 撳掣 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _git_stage_all {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_git_stage_all")
            .on("click.sc_ai_projects_git_stage_all", "[data-btn='git_stage_all']", this.git_stage_all.bind(this));
    }

    // 加入準備區（Pack Changes 掣——git add -A；取 modal → 橋接駁 → 交 act 層）
    async git_stage_all(event) {
        const j_git_stage_all_button = jQuery(event.currentTarget);
        const j_modal = j_git_stage_all_button.closest(".modal__overlay");

        // 橋接駁（同域 act 橋——新方式）
        const tab_git_instance = await registry().sc_ai_projects().tab_git();
        const git_stage_all_instance = await tab_git_instance.act().git_stage_all();

        git_stage_all_instance.git_stage_all(j_modal, j_git_stage_all_button);
    }
}

export const git_stage_all = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_stage_all());
