// jsmods/bind/clicked_status_item.js —— status item 點擊綁定與分派（bind 層——一檔一事）
// 職責：clicked_status_item（.sc_statusbar__item[data-click] 點擊 → 依 data-click 觸發既有 UI）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _clicked_status_item {
    constructor() {}

    // 綁定 item 點擊（document 根層委派——三條 bar 共用；只處理有 data-click 的 item）
    init() {
        jQuery(document)
            .off("click.sc_ai_status_bar_clicked_status_item")
            .on("click.sc_ai_status_bar_clicked_status_item", ".sc_statusbar__item[data-click]", this.clicked_status_item.bind(this));
    }

    // item 點擊分派（依 data-click 型別觸發既有 UI——不重寫既有邏輯）
    async clicked_status_item(event) {
        const j_item = event.target.closest(".sc_statusbar__item[data-click]");
        if (!j_item) {
            return;
        }

        const click_type = j_item.dataset.click;

        if (click_type === "settings") {
            const click_tab = j_item.dataset.clickTab || "";

            // 無指定分頁 → 沿用既有 settings 按鈕（行為不變）
            if (click_tab === "") {
                this.trigger_click("[data-btn='modal_open_settings']");
                return;
            }

            // 有指定分頁 → 同 settings.js 同一條鏈（modal 域 → settings_modal → 指定 section）
            const modal_domain = await (await registry().sc_ai_settings()).modal();
            const settings_modal_instance = await modal_domain.act().settings_modal();

            settings_modal_instance.open_modal(function (j_modal) {
                settings_modal_instance.render_setting_data(j_modal, click_tab);
            });
            return;
        }
        if (click_type === "project_edit") {
            this.trigger_click(".sc_project_list .sc_project_card--active [data-btn='project_edit']");
            return;
        }
        if (click_type === "project_git") {
            // 動態載入（action 模組——用先載）
            const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
            const open_project_git_modal_instance = await status_bar_domain.act().open_project_git_modal();

            open_project_git_modal_instance.open_project_git_modal();
            return;
        }
    }

    // 觸發既有元素點擊（沿用既有事件處理——如 settings／log viewer／process viewer）
    trigger_click(selector) {
        const j_target = document.querySelector(selector);
        if (!j_target) {
            return;
        }
        j_target.click();
    }
}

export const clicked_status_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _clicked_status_item());
