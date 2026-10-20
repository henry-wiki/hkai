// jsmods/model_selector/act/model_picker.js —— model selection modal 全流程（action 層——一 action 一檔）
// 職責：model_picker（即開 modal（body 中央 loading）→ 預備 model info（composer hidden fields）→ 載 providers 灌內容 → 交 render_model_list 續開場（讀活動記憶→載目標頁→揀返目標＋efforts）；狀態 model_info／modal 參照交域共用（域單一持有））
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _model_picker {
    constructor() {}

    // 開 modal（入口——即開（body 中央 loading）→ 載 available models → 返嚟灌內容＋續 display 流程；唔等 fetch 先開）
    async open_modal() {
        // 即開 modal（框架替換式建立——header 標題「Model」；body 先空——內容返嚟先灌）
        const modal = registry().sc_modal();

        const j_modal = await modal.create_modal({
            modal_id        : "model_selector",
            modal_title     : "Model",
        });
        // 顯示（create 後即開——移除式關閉由 ✕ / mask / modal_close 觸發）
        j_modal.addClass("visible");

        // modal 參照交域共用（各組經域例項取——apply／顯示刷新靠佢）
        model_selector_instance.set_j_modal(j_modal);

        // modal body 中央 loading（開場形——跟家族 modal_open：mask opacity 0（唔黑閃）；文字＝語系字典 common.loading）
        const loading = registry().sc_loading();
        const j_modal_body = j_modal.find(".modal__body");

        loading.start_panel_loading(j_modal_body, { "mask": { "opacity": 0 } });

        // 讀當前 model info（model_context 代讀 prompt composer hidden fields——存域共用；display 揀返目標＋effort 校正靠佢；fetch 前先讀定）
        const model_activity_instance = await model_selector_instance.act().model_activity();
        const model_context_instance = await model_selector_instance.act().model_context();

        const model_info = model_context_instance.get_model_info_from_prompt();
        model_selector_instance.set_model_info(model_info);

        // 載 providers 資料（成功 → 收 loading → 灌 body → 同步活動 → 續 display 全流程；失敗 → 收 loading ＋ alert）
        // spawn 取 fetch 組（req——fetch_providers）
        const fetch_providers_instance = await model_selector_instance.req().fetch_providers();

        fetch_providers_instance.fetch_providers({
            success: async function (result) {
                loading.stop_loading(j_modal_body);

                // 內容灌入（後端砌——前端只 insert；先收 loading 再換 body——遮罩唔會被換走）
                j_modal_body.html(result.html.modal_body);

                // 頁面活動 scope（fetch 帶返——{project_id, chat_id}；存 model_activity）
                model_activity_instance.set_activity_scope(result.activity_scope);

                // 活動同步（prepared model info 全件直送——等佢落地先續 display）
                const model_info = model_selector_instance.get_model_info();

                await model_activity_instance.set_activity(model_info);

                // 續開場初始化（交 render_model_list——讀活動記憶→載目標頁→揀返目標＋efforts＋scroll）
                const render_model_list_instance = await model_selector_instance.act().render_model_list();

                render_model_list_instance.render_model_list();
            },
            error: function () {
                // 失敗 → 收 loading ＋ alert（modal 開住——等重試）
                loading.stop_loading(j_modal_body);
                alert("cannot load providers: request failed");
            },
        });
    }

}

export const model_picker = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_picker());
