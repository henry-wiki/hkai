// jsmods/model_selector/act/switch_provider_item.js —— provider 項目切換（action 層——一 action 一檔）
// 職責：switch_provider_item（高亮目標 provider 按鈕＋載入該 provider model list＋按活動記憶揀返 model＋捲至選中列；callbacks 回報；切換過程單一入口）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _switch_provider_item {
    constructor() {}

    // 切換 provider 項目（高亮目標按鈕＋載入該頁＋按活動記憶揀返 model＋捲至選中列；callbacks 回報——切換過程單一入口）
    async switch_provider_item(provider_id, callbacks) {
        const thisObject = this;

        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        const j_side_provider_tab_buttons = model_selector_instance.get_side_provider_tab_buttons();

        j_side_provider_tab_buttons.filter(".active").removeClass("active");
        j_side_provider_tab_buttons.filter("[data-provider_id='" + provider_id + "']").addClass("active");

        // 載入該 provider model list（右邊出 loading 再入頁——同設定 modal 一 nav 一頁）
        // 無 provider（空資料）→ 不動作
        if (!provider_id) {
            return;
        }

        const j_modal = model_selector_instance.get_j_modal();

        // 右邊單一內容位——loading 遮罩（樣式／文字皆預設——sc_ai_providers.js 及語系字典）
        const loading = registry().sc_loading();
        const j_table_wrap = j_modal.find("#model_selector_table_wrap");

        loading.start_panel_loading(j_table_wrap);

        // 橋接駁（同域 req 橋——新方式）
        const fetch_models_req_instance = await model_selector_instance.req().fetch_models();

        await fetch_models_req_instance.fetch_models(provider_id, {
            success: async function (response_data) {
                loading.stop_loading(j_table_wrap);

                thisObject.render_provider_page(response_data.html);

                // 讀活動記憶 → 對正本 provider → 揀返記住嘅 model（高亮＋footer／efforts——switch_model_item 內）
                const model_activity_instance = await model_selector_instance.act().model_activity();
                const model_info = await model_activity_instance.get_activity();

                // spawn 取組（switch_model_item——揀返／捲列）
                const switch_model_item_instance = await model_selector_instance.act().switch_model_item();

                // 判定：有記憶且對正本 provider → 揀返
                if (model_info && model_info.provider_id === provider_id) {
                    await switch_model_item_instance.switch_model_item(provider_id, model_info.model_id);
                }

                // 捲至選中列（容器中央；無選中 → 捲返頂——切換頁後定位）
                switch_model_item_instance.scroll_to_selected_model();

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

    // 渲染 provider 頁（後端 HTML——前端只 insert：header 掛標題列；body 掛右欄內容位；footer＝內容底兩條）
    render_provider_page(html) {
        const j_modal = model_selector_instance.get_j_modal();

        // 標題列（清走舊 → 掛新；provider 名由後端出）
        const j_modal_header_content = j_modal.find(".modal__header-content");
        j_modal_header_content.empty();
        j_modal_header_content.append(html.header);

        // 右邊單一內容位——清走上一頁，掛新頁
        const j_table_wrap = j_modal.find("#model_selector_table_wrap");
        j_table_wrap.empty();
        j_table_wrap.append(html.body);

        // 內容底兩條（每頁自帶——清舊 → 掛新；同設定 modal 的 .modal__content-footer）
        const j_modal_content = j_table_wrap.parent();
        j_modal_content.find(".modal__content-model_selector_efforts").remove();
        j_modal_content.find(".modal__content-footer").remove();
        j_modal_content.append(html.footer);
    }
}

export const switch_provider_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_provider_item());
