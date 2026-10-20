// jsmods/setup/evt/refresh_install_item.js —— 「安裝」綁定（evt 層——一檔一事）
// 職責：refresh_install_item（[data-action='install'] 主掣點擊 → 逐項一項一項安裝）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup } from "../setup.js";

// 域例項（頂層 import 單例——流程內域存取用）
const setup_instance = setup();

class _refresh_install_item {
    constructor() {}

    // 綁定註冊（域召集呼叫——錨 modal 根；頁面初始化一次）
    init() {
        const j_modal = setup_instance.get_modal_element();

        j_modal.off("click.setup_refresh_install_item").on("click.setup_refresh_install_item", this.on_click.bind(this));
    }

    // 「安裝」點擊（逐項安裝——前端逐項叫、每項即跑即回）
    async on_click(event) {
        const j_install_button = jQuery(event.target).closest("[data-action='install']");

        // 判定：唔係安裝主掣 → 唔動
        if (j_install_button.length === 0) {
            return;
        }

        // spawn 取組（act——逐項安裝＋鎖掣）
        const refresh_install_item_instance = await setup_instance.act().refresh_install_item();

        await refresh_install_item_instance.refresh_install_item();
    }
}

export const refresh_install_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _refresh_install_item());
