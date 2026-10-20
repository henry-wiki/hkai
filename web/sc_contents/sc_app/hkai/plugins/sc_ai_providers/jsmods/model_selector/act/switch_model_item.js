// jsmods/model_selector/act/switch_model_item.js —— model 列切換（action 層——一 action 一檔）
// 職責：switch_model_item（高亮目標 model 列——先清再上 selected；選取落定→重繪＋寫記憶；選取過程單一入口）＋ refresh_reasoning_efforts（footer info／efforts 兩支分繪）＋ scroll_to_selected_model（捲至選中列；無選中 → 捲返頂）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_model_item {
    constructor() {}

    // 切換 model 項目（高亮目標列＋重繪 footer＋寫記憶——選取過程單一入口）
    async switch_model_item(provider_id, model_id) {
        const j_model_rows = model_selector_instance.get_tr_model_rows();

        j_model_rows.filter(".selected").removeClass("selected");
        j_model_rows.filter("[data-provider_id='" + provider_id + "'][data-model_id='" + model_id + "']").addClass("selected");

        // 重繪 footer（選取落定——兩支分繪）
        await this.refresh_reasoning_efforts();

        // 寫活動記憶（選取落定——讀當前選取打包寫入；非 render）
        const model_activity_instance = await model_selector_instance.act().model_activity();
        await model_activity_instance.update_activity();
    }

    // 重繪 footer（選取落定——footer info＋efforts 兩支分繪）
    async refresh_reasoning_efforts() {
        // spawn 取組（render_modal_footer＋render_modal_reasoning_efforts）
        const render_modal_footer_instance = await model_selector_instance.act().render_modal_footer();
        const render_modal_reasoning_efforts_instance = await model_selector_instance.act().render_modal_reasoning_efforts();

        await render_modal_footer_instance.render_modal_footer();
        await render_modal_reasoning_efforts_instance.render_modal_reasoning_efforts();
    }

    // 捲動 table 至當前選中 model 列（容器中央——無選中 → 捲返頂；jQuery animate 0.3s——stepped scrollTop）
    scroll_to_selected_model() {
        const j_table_wrap              = model_selector_instance.get_table_wrap();
        const j_tr_model_row_selected   = model_selector_instance.get_tr_model_row_selected();

        let scroll_target_top = 0;

        // 判定：有選中列 → 目標＝列喺容器中央
        if (j_tr_model_row_selected.length) {
            const offsetTop = j_tr_model_row_selected[0].offsetTop;
            const clientHeight = j_table_wrap[0].clientHeight;

            scroll_target_top = offsetTop - clientHeight / 2;
        }

        // 捲動 0.3s 完成（jQuery animate——屬性 scrollTop）
        j_table_wrap.animate({ "scrollTop": scroll_target_top }, 90);
    }
}

export const switch_model_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_model_item());
