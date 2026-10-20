// jsmods/modal/evt/modal_tab_switch.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_tab_switch——切換 tab（header））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _modal_tab_switch {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 切換 modal tab（header 三粒）
        jQuery(document)
            .off("click.sc_ai_projects_tab")
            .on("click.sc_ai_projects_tab", ".modal_tab[data-tab_id]", this.modal_tab_switch.bind(this));
    }

    // 切換 modal tab（三粒 tab——切 tab 態＋載入該 tab 內容）
    async modal_tab_switch(event) {
        const j_tab_button = jQuery(event.currentTarget);
        const j_modal      = j_tab_button.closest(".modal__overlay");
        const tab_id       = j_tab_button.attr("data-tab_id");

        // 判定：modal 未開（搵唔到 modal 元素）→ 唔切
        if (j_modal.length === 0) {
            return;
        }

        // tab 態即時切換
        // 橋接駁（同域 act 橋——新方式）
        const modal_instance = await registry().sc_ai_projects().modal();
        const modal_open_instance = await modal_instance.act().modal_open();
        const modal_tab_switch_instance = await modal_instance.act().modal_tab_switch();

        modal_tab_switch_instance.modal_tab_switch(j_modal, tab_id);

        // 內容載入（modal 已開——交 modal_open() 換 body：內判定唔重開外框；slug 由 modal 元素直接讀）
        modal_open_instance.modal_open(j_modal.attr("data-project_slug") || "", tab_id, {
            success: function () {
                // 按鈕相關動作（按鈕復原）
            },
            error: function (ui_context, result) {
                toastr.error(result.error || "cannot load tab");
            }
        });
    }
}

export const modal_tab_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_tab_switch());
