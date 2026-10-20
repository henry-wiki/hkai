// jsmods/setup/act/switch_language.js —— 轉語言（act 層——一檔一事）
// 職責：switch_language（取 label 內 hidden radio 值 → 交 req 側即時記錄＋即時以新語言重出整件；唔改 URL·唔 reload）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _switch_language {
    constructor() {}

    // 轉語言（取 label 內 hidden radio 值——後端即時記入過程檔＋即時以新語言重出整件）
    async switch_language(j_language_label) {
        const language_value = j_language_label.find("input[type='radio']").val();
        const section_id = setup_instance.get_current_section_id();

        // spawn 取 req 組（switch_language——連線側）
        const switch_language_instance = await setup_instance.req().switch_language();

        await switch_language_instance.switch_language(section_id, language_value, {
            success: function (result) {
                // 換左選單（新語言——後端砌好）
                const j_left_pane = jQuery(".setup_modal__left");

                j_left_pane.replaceWith(result.html.left);

                // 換右欄（新語言）
                const j_right_pane = jQuery(".setup_modal__right");

                j_right_pane.replaceWith(result.html.right);

                // 換頁面標題（跟新語言）
                document.title = result.title;
            },
            error: function () {
                // 網絡／失敗 → 內容唔動（最安全）
            }
        });
    }
}

export const switch_language = (function (instance) {
    return function () {

        return instance;
    };
})(new _switch_language());
