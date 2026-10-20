// jsmods/bench_modal/act/model_picker.js —— benchmark modal 全流程（action 層——一 action 一檔）
// 職責：model_picker（即開 modal（body 中央 loading）→ 載 bench_source 灌內容 → 交 render_model_list 續開場（載第一個 bench_source 頁））
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { bench_modal } from "../bench_modal.js";

// 域例項（頂層 import 單例——流程內域存取用）
const bench_modal_instance = bench_modal();

class _model_picker {
    constructor() {}

    // 開 modal（入口——即開（body 中央 loading）→ 載 available 榜 → 返嚟灌內容＋續 display 流程；唔等 fetch 先開）
    async open_modal() {
        // 即開 modal（框架替換式建立——header 標題「Benchmark」；body 先空——內容返嚟先灌）
        const modal = registry().sc_modal();

        const j_modal = await modal.create_modal({
            modal_id        : "benchmark",
            modal_title     : "Benchmark",
        });
        // 顯示（create 後即開——移除式關閉由 ✕ / mask / modal_close 觸發）
        j_modal.addClass("visible");

        // modal 參照交域共用（各組經域例項取——頁面刷新靠佢）
        bench_modal_instance.set_j_modal(j_modal);

        // modal body 中央 loading（開場形——跟家族 modal_open：mask opacity 0（唔黑閃）；文字＝語系字典 common.loading）
        const loading = registry().sc_loading();
        const j_modal_body = j_modal.find(".modal__body");

        loading.start_panel_loading(j_modal_body, { "mask": { "opacity": 0 } });

        // 載 bench_source 資料（成功 → 收 loading → 灌 body → 續 display 全流程；失敗 → 收 loading ＋ alert）
        // spawn 取 fetch 組（req——fetch_bench_source）
        const fetch_bench_source_instance = await bench_modal_instance.req().fetch_bench_source();

        fetch_bench_source_instance.fetch_bench_source({
            success: async function (result) {
                loading.stop_loading(j_modal_body);

                // 內容灌入（後端砌——前端只 insert；tabs＝來源切換列、body＝左側欄＋右內容）
                j_modal.find(".modal__tabs").html(result.html.tabs);
                j_modal_body.html(result.html.modal_body);

                // 續開場初始化（交 render_model_list——載第一個 bench_source 頁）
                const render_model_list_instance = await bench_modal_instance.act().render_model_list();

                render_model_list_instance.render_model_list();
            },
            error: function () {
                // 失敗 → 收 loading ＋ alert（modal 開住——等重試）
                loading.stop_loading(j_modal_body);
                alert("cannot load bench_source: request failed");
            },
        });
    }

}

export const model_picker = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_picker());
