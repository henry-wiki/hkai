// jsmods/model_selector/act/render_model_list.js —— 開場初始化（action 層——一 action 一檔）
// 職責：render_model_list（開場續行——讀活動記憶→切換＋載目標頁；空資料 → 自動揀第一個 provider 項；揀返目標／捲至選中列喺 switch_provider_item 鏈內）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _render_model_list {
    constructor() {}

    // 灌內容後續行（⑤ 讀活動記憶（prepared model info）→ ⑥ 切換＋載該 provider 頁（揀返目標／捲至選中列喺 switch_provider_item 內）；事件交 evt 層）
    async render_model_list() {
        // spawn 取組（活動記憶組）
        const model_activity_instance = await model_selector_instance.act().model_activity();

        // 讀頁面活動記憶（model_selector——開場已同步 prepared model info；切換目標 provider 由呢度嚟）
        const model_info = await model_activity_instance.get_activity();

        let selected_provider_id = "";

        // 判定：讀到活動記憶 → 取 provider 做切換目標（model 揀返交 switch_provider_item）
        if (model_info) {
            selected_provider_id = model_info.provider_id;
        }

        // 判定：無 provider（空資料）→ 自動揀第一個 provider 項
        if (!selected_provider_id) {
            const j_side_provider_tab_buttons = model_selector_instance.get_side_provider_tab_buttons();
            const j_first_side_provider_tab_button = j_side_provider_tab_buttons.first();

            selected_provider_id = j_first_side_provider_tab_button.attr("data-provider_id");
        }

        // 判定：一個 provider 都無 → 不動作（空表——等用戶撳）
        if (!selected_provider_id) {
            return;
        }

        // 切換 provider＋載入該頁（單一入口——入面按活動記憶揀返 model＋捲至選中列）
        const switch_provider_item_instance = await model_selector_instance.act().switch_provider_item();

        switch_provider_item_instance.switch_provider_item(selected_provider_id);
    }
}

export const render_model_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _render_model_list());
