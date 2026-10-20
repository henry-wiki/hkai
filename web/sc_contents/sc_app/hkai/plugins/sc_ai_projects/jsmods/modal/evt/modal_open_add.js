// jsmods/modal/evt/modal_open_add.js —— sidebar 側 click 綁定（factory 單例——唔使開 modal；一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（modal_open_add——新增專案（一開頁就喺度））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——host bind_panel_click 動態 import 解構後呼叫 init()）

class _modal_open_add {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派）
    init() {
        // 新增專案
        jQuery(document)
            .off("click.sc_ai_projects_modal_open_add")
            .on("click.sc_ai_projects_modal_open_add", "[data-btn='modal_open_add']", this.modal_open_add.bind(this));
    }

    // 新增專案（無既有資料——交 modal_open()）
    async modal_open_add(event) {
        // 只傳 project_slug（空字串 = 新增），後端會回傳 project_slug=空字串
        // 橋接駁（同域 act 橋——新方式）
        const modal_instance = await registry().sc_ai_projects().modal();
        const modal_open_instance = await modal_instance.act().modal_open();

        modal_open_instance.modal_open("", null, {
            success: function () {
                // 按鈕相關動作（按鈕復原——載入開始暫無，保留骨架位）
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

export const modal_open_add = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_open_add());
