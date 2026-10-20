// jsmods/tab_git/evt/git_commit.js —— 提交綁定（evt 層——事件入口）
// 職責：git_commit（[data-btn='git_commit'] 撳掣 → 交 act 層開單）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _git_commit {
    constructor() {}

    // 綁定（委派——獨立 namespace，DevTools 一眼辨識；.off 先清再綁、零重複）
    init() {
        jQuery(document)
            .off("click.sc_ai_projects_git_commit")
            .on("click.sc_ai_projects_git_commit", "[data-btn='git_commit']", this.git_commit.bind(this));
    }

    // 提交準備區（Commit Now 掣——git commit；取 modal → 橋接駁 → 交 act 層）
    async git_commit(event) {
        const j_git_commit_button = jQuery(event.currentTarget);
        const j_modal = j_git_commit_button.closest(".modal__overlay");

        // 橋接駁（同域 act 橋——新方式）
        const tab_git_instance = await registry().sc_ai_projects().tab_git();
        const git_commit_instance = await tab_git_instance.act().git_commit();

        git_commit_instance.git_commit(j_modal, j_git_commit_button);
    }
}

export const git_commit = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_commit());
