// jsmods/setup/evt/switch_language.js —— 語言選擇綁定（evt 層——一檔一事）
// 職責：switch_language（label.setup_lang_button 點擊 → 標記 checked → 交 act 即時記錄＋換件；唔 reload）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _switch_language {
    constructor() {}

    // 綁定註冊（域召集呼叫——錨 modal 根；頁面初始化一次）
    init() {
        const j_modal = setup_instance.get_modal_element();

        j_modal.off("click.setup_switch_language").on("click.setup_switch_language", this.on_click.bind(this));
    }

    // 語言 label 點擊（值＝label 內 hidden radio）
    async on_click(event) {
        const j_language_label = jQuery(event.target).closest("label.setup_lang_button");

        // 判定：唔係語言 label → 唔動
        if (j_language_label.length === 0) {
            return;
        }

        // 停預設＋停冒泡（label 預設會再觸發 input 點擊——統一由本程序處理）
        event.preventDefault();
        event.stopPropagation();

        // 標記選中（label 內 checkbox／radio 即時 checked——唔靠瀏覽器預設）
        j_language_label.find("input[type='checkbox'], input[type='radio']").prop("checked", true);

        // spawn 取組（act——轉語言）
        const switch_language_instance = await setup_instance.act().switch_language();

        await switch_language_instance.switch_language(j_language_label);
    }
}

export const switch_language = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_language());
