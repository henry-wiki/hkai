// jsmods/model_selector/evt/bind_model_picker.js —— 模型選擇器觸發綁定（evt 層——一檔一事）
// 職責：bind_model_picker（.sc_composer__model_picker 點擊 → 開 model selection modal）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { model_selector } from "../model_selector.js";

// 域例項（頂層 import 單例——流程內域存取用）
const model_selector_instance = model_selector();

class _bind_model_picker {
    constructor() {}

    // 綁定註冊（開機一次——重掛安全：先 off 再 on；錨 .sc_composer 固定根——唔用 document）
    init() {
        jQuery(".sc_composer")
            .off("click.sc_ai_providers_bind_model_picker")
            .on("click.sc_ai_providers_bind_model_picker", ".sc_composer__model_picker", this.bind_model_picker.bind(this));
    }

    // 顯示 div 觸發（modal 全流程在子模組——動態載入 action 開 modal）
    async bind_model_picker() {
        // 橋接駁（同域 act 橋——新方式）
        const model_picker_instance = await model_selector_instance.act().model_picker();

        await model_picker_instance.open_modal();

        // 開 modal 後重掛事件（域開域召集——evt／act／req 橋逐條 re-init；重掛安全：先 off 再 on）
        await model_selector_instance.bind_events();
    }
}

export const bind_model_picker = (function (instance) {
    return function () {

        return instance;
    };
})(new _bind_model_picker());
