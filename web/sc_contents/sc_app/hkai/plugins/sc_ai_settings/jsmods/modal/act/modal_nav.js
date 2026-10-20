// jsmods/action/modal_nav.js —— settings modal 左側選單（nav）動作（action 層——一 action 一檔）
// 職責：modal_nav（切頁請求／active 切換／搜尋過濾／父項開合——事件綁定在 bind/ 各檔）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _modal_nav {
    constructor() {}

    // 動作：切換 section（父項無 data-section → 不切頁）
    switch_section(j_button) {
        const section = j_button.attr("data-section");

        // 判定：父項（無 data-section）→ 不切頁
        if (!section) {
            return;
        }

        this.show_section(section);
    }

    // 切頁編排（nav 職責——設 active_section → nav active → 叫 modal 載入內容）
    async show_section(section) {
        // 動態載入（registry——用先載）

        // spawn 取 modal 組（狀態 owner）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        modal_module.active_section = section;

        this.set_active_section(modal_module.j_modal, section);

        await modal_module.reload_section(section, function () {
            return modal_module.fetch_section_data(section);
        });
    }

    // 動作：active 切換（依目前顯示的 section）
    set_active_section(j_modal, section) {
        j_modal.find(".settings_nav_button").removeClass("active");
        j_modal.find(".settings_nav_button[data-section='" + section + "']").addClass("active");
    }

    // 動作：搜尋過濾（依 keyword 隱藏不匹配的 nav 按鈕）
    filter_sections(j_modal, keyword) {
        const normalized = keyword.toLowerCase();
        const j_buttons = j_modal.find(".settings_nav_button");

        j_buttons.each(function (index, button) {
            const j_button = jQuery(button);
            const text = j_button.text().toLowerCase();

            j_button.toggle(text.includes(normalized));
        });

        // 判定：active section 被隱藏 → 切到第一個可見的
        const j_active = j_modal.find(".settings_nav_button.active");
        if (j_active.length && j_active.is(":hidden")) {
            const j_first_visible = j_buttons.filter(":visible").first();

            // 判定：有可見項 → 切頁
            if (j_first_visible.length) {
                this.show_section(j_first_visible.attr("data-section"));
            }
        }
    }

    // 動作：父項開合
    toggle_nav_parent(j_element) {
        j_element.toggleClass("open");
    }
}

export const modal_nav = (function (instance) {
    return function () {

        return instance;
    };
})(new _modal_nav());
