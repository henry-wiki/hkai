// jsmods/bench_modal/evt/filter_bench_source_list.js —— 左欄 bench_source search 綁定（evt 層——一檔一事）
// 職責：filter_bench_source_list（#model_selector_search 點擊 → 即場綁 input 過濾——輸入 → 左欄 bench_source 項目 DOM show/hide）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _filter_bench_source_list {
    constructor() {}

    // 綁定註冊（域召集呼叫——開 modal 後重掛；錨 modal 根——唔用 document）
    init() {
        const j_modal = jQuery(bench_modal_instance.get_j_modal());

        // 判定：modal 唔存在（未開）→ 唔綁
        if (!j_modal.length) {
            return;
        }

        j_modal
            .off("click.sc_ai_benchmark_filter_bench_source_list")
            .on("click.sc_ai_benchmark_filter_bench_source_list", "#model_selector_search", this.filter_bench_source_list.bind(this));
    }

    // search input（特殊——onclick 即場綁定 input 事件；off 先清，防重複；過濾對象＝左欄 bench_source 項目）
    async filter_bench_source_list(event) {
        const j_search = jQuery(event.currentTarget);

        const j_side_bench_source_items = bench_modal_instance.get_side_bench_source_items();

        j_search
            .off("input.sc_ai_benchmark_filter_bench_source_list")
            .on("input.sc_ai_benchmark_filter_bench_source_list", function (event) {
                let keyword = jQuery(this).val();
                    keyword = keyword.toLowerCase();
                    keyword = keyword.trim();

                if (keyword === "") {
                    j_side_bench_source_items.show();
                }
                else {
                    j_side_bench_source_items.hide();
                    j_side_bench_source_items.filter(function () {

                        const haystack = jQuery(this).text().toLowerCase();
                        if (haystack.indexOf(keyword) !== -1) {
                            return true;
                        }
                        return false;
                    }).show();
                }

            });
    }
}

export const filter_bench_source_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _filter_bench_source_list());
