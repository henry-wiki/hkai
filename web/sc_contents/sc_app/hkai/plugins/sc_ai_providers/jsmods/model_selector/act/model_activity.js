// jsmods/model_selector/act/model_activity.js —— model 選擇活動記憶（act 層——一檔一事）
// 職責：model_activity（頁面活動 model_selector 讀寫——開場讀返記住嘅 model、揀 model 寫入；活動名單一來源）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _model_activity {
    constructor() {
        // 頁面活動 scope（{project_id, chat_id}——fetch_providers 帶返；活動讀寫用）
        this.activity_scope = {};
    }

    // set 頁面活動 scope（fetch_providers 帶返——先存；活動讀寫靠佢）
    set_activity_scope(activity_scope) {
        this.activity_scope = activity_scope;
    }

    // 讀頁面活動 scope（{project_id, chat_id}——fetch_providers 帶返；活動讀寫用）
    get_activity_scope() {
        return this.activity_scope;
    }

    // 讀取 model selector 活動（成功回 model info bundle、查無回 null；scope 用當前存低嗰個）
    async get_activity() {
        // 動態載入（registry——用先載）
        const sc_page_activity_instance = await registry().sc_page_activity();

        return await sc_page_activity_instance.get_activity("model_selector", this.activity_scope);
    }

    // 寫入 model selector 活動（model info 全件直送——傳咩寫咩；scope 用當前存低嗰個）
    async set_activity(model_info) {
        // 動態載入（registry——用先載）
        const sc_page_activity_instance = await registry().sc_page_activity();

        await sc_page_activity_instance.set_activity("model_selector", model_info, this.activity_scope);
    }

    // 更新頁面活動記憶（由當前選取砌包——讀 modal 選取（provider／model／active effort）打包交核心；無選取＝唔寫）
    async update_activity() {
        const model_context_instance = await model_selector_instance.act().model_context();

        const provider_id = model_context_instance.get_modal_selected_provider_id();
        const model_id    = model_context_instance.get_modal_selected_model_id();

        // 判定：無選取（空）→ 唔寫
        if (!provider_id || !model_id) {
            return;
        }

        // effort（active 按鈕 data-effort——無 active（不支援／未揀）＝空）
        const j_reasoning_effort_button = model_context_instance.get_actived_reasoning_effort_button();
        let reasoning_effort = "";
        // 判定：有 active effort 按鈕 → 取 effort
        if (j_reasoning_effort_button.length) {
            reasoning_effort = j_reasoning_effort_button.attr("data-effort");
        }

        // 選取打包（model info——整包交核心寫入）
        const model_info = {};
        model_info.provider_id      = provider_id;
        model_info.model_id         = model_id;
        model_info.reasoning_effort = reasoning_effort;

        await this.set_activity(model_info);
    }
}

export const model_activity = (function (instance) {
    return function () {

        return instance;
    };
})(new _model_activity());
