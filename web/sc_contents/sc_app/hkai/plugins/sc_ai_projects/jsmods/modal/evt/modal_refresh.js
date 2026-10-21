// jsmods/modal/evt/modal_refresh.js —— modal 內 click 綁定（factory 單例——開咗 modal 先有；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_refresh——重新整理（footer 左側共用鈕））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_modal_click 動態 import 解構後呼叫 init()）

class _modal_refresh {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 重新整理（footer 共用鈕——當前 tab 內容重載；規則頁＝右側同儲存並列）
        jQuery(document)
            .off("click.sc_ai_projects_refresh")
            .on("click.sc_ai_projects_refresh", "[data-btn='project_refresh']", this.modal_refresh.bind(this));
    }

    // 重新整理（footer 共用鈕——直接重載當前 tab＝load self again）
    async modal_refresh(event) {
        const j_refresh_button = jQuery(event.currentTarget);
        const j_modal = j_refresh_button.closest(".modal__overlay");
        const tab_id = j_modal.find(".modal_tab.on").attr("data-tab_id") || "general_form";

        // 直接重載當前 tab（唔靠合成 click——合成事件喺 modal_open 內未必接到）
        const modal_instance = await registry().sc_ai_projects().modal();
        const modal_open_instance = await modal_instance.act().modal_open();

        await modal_open_instance.modal_open(j_modal.attr("data-project_slug") || "", tab_id, {
            success: function () {
            },
            error: function (ui_context, result) {
                toastr.error(result.error || "cannot load tab");
            }
        });
    }
}

export const modal_refresh = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_refresh());
