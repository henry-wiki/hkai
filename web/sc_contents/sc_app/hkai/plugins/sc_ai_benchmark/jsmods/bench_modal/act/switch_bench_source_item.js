// jsmods/bench_modal/act/switch_bench_source_item.js —— bench_source 項目切換（action 層——一 action 一檔）
// 職責：switch_bench_source_item（高亮目標 entry 按鈕（來源＋entry）＋載入該 entry model list；callbacks 回報；切換過程單一入口）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _switch_bench_source_item {
    constructor() {}

    // 切換 entry 項目（高亮目標按鈕＋載入該頁；callbacks 回報——切換過程單一入口）
    async switch_bench_source_item(bench_source_id, entry, callbacks) {
        const thisObject = this;

        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        const j_side_bench_source_tab_buttons = bench_modal_instance.get_side_bench_source_tab_buttons();

        j_side_bench_source_tab_buttons.filter(".active").removeClass("active");
        j_side_bench_source_tab_buttons.filter("[data-bench_source_id='" + bench_source_id + "'][data-entry='" + entry + "']").addClass("active");

        // 載入該 bench_source model list（右邊出 loading 再入頁——同設定 modal 一 nav 一頁）
        // 無 bench_source（空資料）→ 不動作
        if (!bench_source_id) {
            return;
        }

        const j_modal = bench_modal_instance.get_j_modal();

        // 右邊單一內容位——loading 遮罩（樣式／文字皆預設——sc_ai_benchmark.js 及語系字典）
        const loading = registry().sc_loading();
        const j_table_wrap = j_modal.find("#model_selector_table_wrap");

        loading.start_panel_loading(j_table_wrap);

        // 橋接駁（同域 req 橋——新方式）
        const fetch_models_req_instance = await bench_modal_instance.req().fetch_models();

        await fetch_models_req_instance.fetch_models(bench_source_id, entry, {
            success: async function (response_data) {
                loading.stop_loading(j_table_wrap);

                thisObject.render_bench_source_page(response_data.html);

                // 判定：有 callbacks.success → 回報成功
                if (typeof callbacks.success === "function") {
                    callbacks.success(response_data);
                }
            },
            error: function (response_data) {
                // 失敗 → 收 loading（維持舊頁——等下一撳）
                loading.stop_loading(j_table_wrap);

                // 判定：有 callbacks.error → 回報失敗
                if (typeof callbacks.error === "function") {
                    callbacks.error(response_data);
                }
            }
        });
    }

    // 渲染 bench_source 頁（後端 HTML——前端只 insert：header 掛標題列；body 掛右欄內容位）
    render_bench_source_page(html) {
        const j_modal = bench_modal_instance.get_j_modal();

        // 標題列（清走舊 → 掛新；bench_source 名由後端出）
        const j_modal_header_content = j_modal.find(".modal__header-content");
        j_modal_header_content.empty();
        j_modal_header_content.append(html.header);

        // 右邊單一內容位——清走上一頁，掛新頁
        const j_table_wrap = j_modal.find("#model_selector_table_wrap");
        j_table_wrap.empty();
        j_table_wrap.append(html.body);
    }
}

export const switch_bench_source_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_bench_source_item());
