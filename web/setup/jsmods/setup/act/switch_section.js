// jsmods/setup/act/switch_section.js —— 切格（act 層——一 action 一檔）
// 職責：switch_section（大區域遮罩（sc_loading）→ fetch 一件〔左選單＋右欄〕→ 整件換上；唔改 URL；後端砌好——前端只 insert）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _switch_section {
    constructor() {}

    // 切格（入口——遮罩上；取件收；唔改 URL）
    async switch_section(section_id) {
        // 大區域遮罩（sc_loading——未指定＝只留圖案、不顯示文字）
        const j_section_main = jQuery(".setup_section-main");

        register().sc_loading().start_panel_loading(j_section_main);

        // spawn 取 req 組（switch_section——連線側）
        const switch_section_instance = await setup_instance.req().switch_section();

        await switch_section_instance.switch_section(section_id, {
            success: async function (result) {
                register().sc_loading().stop_loading(j_section_main);

                // 換左選單（active 由後端砌——前端零狀態判斷）
                const j_left_pane = jQuery(".setup_modal__left");

                j_left_pane.replaceWith(result.html.left);

                // 換右欄（header／body／footer 一件帶返——舊遮罩隨舊件離場）
                const j_right_pane = jQuery(".setup_modal__right");

                j_right_pane.replaceWith(result.html.right);

                // 開場逐項查（新格 init 項——唔等：背景逐項查、漸進換卡）
                const refresh_check_item_instance = await setup_instance.act().refresh_check_item();

                refresh_check_item_instance.start_batch_check();
            },
            error: function () {
                // 網絡／失敗 → 拆遮罩（保留原狀——最安全）
                register().sc_loading().stop_loading(j_section_main);
            }
        });
    }
}

export const switch_section = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_section());
