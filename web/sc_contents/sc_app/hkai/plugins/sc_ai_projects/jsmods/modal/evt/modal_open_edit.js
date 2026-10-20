// jsmods/modal/evt/modal_open_edit.js —— sidebar 側 click 綁定（factory 單例——唔使開 modal；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_open_edit——編輯專案（一開頁就喺度））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_panel_click 動態 import 解構後呼叫 init()）

class _modal_open_edit {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 編輯專案
        jQuery(document)
            .off("click.sc_ai_projects_modal_open_edit")
            .on("click.sc_ai_projects_modal_open_edit", "[data-btn='modal_open_edit']", this.modal_open_edit.bind(this));
    }

    // 編輯專案（帶入現有資料——交 modal_open()；同 add 共用同一函數）
    async modal_open_edit(event) {
        // 卡根係 <a> —— 掣 click 要截斷（唔觸發導航／切換）
        event.preventDefault();
        event.stopPropagation();

        const j_edit_button = jQuery(event.currentTarget);

        // 只取 project_slug，後端根據 slug 查 project_id
        const project_slug = j_edit_button.closest(".sc_project_card").attr("data-project_slug");

        // 使用 modal_open 打開編輯表單（傳 project_slug → 後端查 project_id）
        // 橋接駁（同域 act 橋——新方式）
        const modal_instance = await registry().sc_ai_projects().modal();
        const modal_open_instance = await modal_instance.act().modal_open();

        modal_open_instance.modal_open(project_slug, null, {
            success: function () {
                // 按鈕相關動作（按鈕復原）
            },
            error: function (ui_context, result) {
                const error_slots_html = result.html ? result.html.slots : "";

                // 判定：有錯誤內容（後端砌）→ 顯示喺 modal 內（用家視線所在）
                if (error_slots_html) {
                    ui_context.j_modal.find(".modal__body").html(error_slots_html);

                    return;
                }

                // 冇錯誤內容（如網絡錯）→ 退用 toast
                toastr.error(result.error || "cannot load modal form");
            }
        });
    }
}

export const modal_open_edit = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_open_edit());
