// jsmods/bench_modal/evt/refresh_model_list.js —— bench_source Refresh 按鈕綁定（evt 層——一檔一事）
// 職責：refresh_model_list（.model_selector_bench_source_refresh 點擊 → 按鈕 disabled → rebuild_bench_source_models → 復原）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _refresh_model_list {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(bench_modal_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_benchmark_refresh_model_list")
            .on("click.sc_ai_benchmark_refresh_model_list", ".model_selector_bench_source_refresh", this.refresh_model_list.bind(this));
    }

    // Refresh 按鈕（載入中 disabled——成敗兩路都復原）
    async refresh_model_list(event) {
        const j_element = jQuery(event.currentTarget);

        j_element.prop("disabled", true);

        const bench_source_id = j_element.attr("data-bench_source_id");

        // 當前 entry（左欄 active 掣——重生後重繪返呢頁）
        const entry = bench_modal_instance.get_side_bench_source_tab_buttons().filter(".active").attr("data-entry");

        // spawn 取組（modal_action——重生＋重繪）
        const modal_action = await bench_modal_instance.act().modal_action();

        modal_action.rebuild_bench_source_models(bench_source_id, entry, {
            on_complete: () => j_element.prop("disabled", false),
            on_fail:     () => j_element.prop("disabled", false),
        });
    }
}

export const refresh_model_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_model_list());
