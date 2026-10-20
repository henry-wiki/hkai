// jsmods/project/evt/project_restore.js —— 還原專案 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派 [data-btn='project_restore']）② 動作（project_restore——交 act）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——域 init 動態 import 解構後呼叫 init()）

class _project_restore {
    constructor() {}

    // 綁定註冊（由域 init 呼叫——一次；selector 委派）
    init() {
        // 還原專案（歸檔清單卡片——API → 清單移除）
        jQuery(document)
            .off("click.sc_ai_projects_restore")
            .on("click.sc_ai_projects_restore", "[data-btn='project_restore']", this.project_restore.bind(this));
    }

    // 還原（表單內——先確認，讀 data-confirm_msg，[[project_name]] 替換）
    async project_restore(event) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_restore_button = jQuery(event.currentTarget);
        const j_modal = j_restore_button.closest(".modal__overlay");

        // 專案資料取自表單欄位（供確認訊息替換）
        const replacements = {};
        replacements["project_name"] = j_modal.find("[data-field='project_name']").val();

        // 判定：使用者未確認 → 不動作
        if (!await shared.sc_web_utils().is_confirmed(j_restore_button, "data-confirm_msg", replacements)) {
            return;
        }

        // 直接讀 modal 上的 data-project_slug
        const project_slug = j_modal.attr("data-project_slug");

        // 橋接駁（同域 act 橋——新方式）
        const project_instance = await registry().sc_ai_projects().project();
        const project_restore_instance = await project_instance.act().project_restore();

        project_restore_instance.project_restore(project_slug, j_modal);
    }
}

export const project_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_restore());
