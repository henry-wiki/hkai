// jsmods/setup/evt/close_setup.js —— 「關閉」綁定（evt 層——一檔一事）
// 職責：close_setup（[data-setup_close] 感謝頁關閉掣點擊 → 重新載入當前頁）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _close_setup {
    constructor() {}

    // 綁定註冊（域召集呼叫——錨 modal 根；頁面初始化一次）
    init() {
        const j_modal = setup_instance.get_modal_element();

        j_modal.off("click.setup_close_setup").on("click.setup_close_setup", this.on_click.bind(this));
    }

    // 「關閉」點擊（去站點入口）
    async on_click(event) {
        const j_close_button = jQuery(event.target).closest("[data-setup_close]");

        // 判定：唔係關閉掣 → 唔動
        if (j_close_button.length === 0) {
            return;
        }

        // spawn 取組（act——關閉）
        const close_setup_instance = await setup_instance.act().close_setup();

        close_setup_instance.close_setup();
    }
}

export const close_setup = (function (instance) {
    return function () {

        return instance;
    };
})(new _close_setup());
