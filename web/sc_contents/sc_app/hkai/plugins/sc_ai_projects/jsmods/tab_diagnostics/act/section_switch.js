// jsmods/tab_diagnostics/act/section_switch.js —— 診斷 section 切換（action 層——一 action 一檔）
// 職責：section_switch（active 即時切換 → req fetch_section 出街 → 成功換 .diagnostics_section）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _section_switch {
    constructor() {}

    // 切換診斷 section（每 tab 獨立——只載所選嗰份；載入期間內容欄遮罩）
    async section_switch(j_modal, j_button) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        // 準備資料（project_slug 由 modal DOM 帶——同 save_tab_rule 來源一致；section id 由撳掣帶）
        const project_slug = j_modal.attr("data-project_slug") || "";
        const section_id   = j_button.attr("data-section") || "";

        const j_pane = j_modal.find(".modal_pane[data-pane='diagnostics']");
        const j_content = j_pane.find(".modal__content");

        // 切態（即時——active 一換、CSS 放行）
        j_pane.find(".settings_nav_button").removeClass("active");
        j_pane.find(".settings_nav_button[data-section='" + section_id + "']").addClass("active");

        // 標準：fetch 期間內容欄顯示載入中
        loading.start_panel_loading(j_content, {
            mask: {
                bgcolor: "#000000",
                opacity: .5
            },
            text: "Loading..."
        });

        // 橋接駁（同域 req 橋——新方式）
        const tab_diagnostics_instance   = await registry().sc_ai_projects().tab_diagnostics();
        const fetch_section_req_instance = await tab_diagnostics_instance.req().fetch_section();

        await fetch_section_req_instance.fetch_section(project_slug, section_id, {
            before_load: function () {

            },
            success: async function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 成功 → 換 section（後端 HTML 原樣插入——前端零砌）＋遮罩收起
                j_content.find(".diagnostics_section").replaceWith(result.html);

                loading.stop_loading(j_content);
            },
            error: async function (result) {
                // 動態載入（registry——用先載）
                const loading = registry().sc_loading();

                // 失敗 → 遮罩收起＋報錯（後端訊息優先）
                loading.stop_loading(j_content);

                let error_message = "load diagnostics section failed";

                // 判定：後端帶咗訊息 → 用後端嗰句
                if (result && result.error) {
                    error_message = result.error;
                }

                toastr.error(error_message);
            }
        });
    }
}

export const section_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _section_switch());
