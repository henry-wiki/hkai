// jsmods/sc_sidebar/act/sidebar_state_save.js —— 側欄狀態活動寫入（act 層——一檔一事）
// 職責：sidebar_state_save（拖曳調寬完／收合切換完 → 寫入活動 sidebar_state——記錄兩欄開合＋欄寬）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { sc_sidebar } from "../sc_sidebar.js";

// 域例項（頂層 import 單例——流程內域存取用）
const sc_sidebar_instance = sc_sidebar();

class _sidebar_state_save {
    constructor() {}

    // 寫入側欄狀態活動（bundle：兩欄開合旗標＋欄寬；scope 唔帶〔側欄狀態唔屬任何 project／chat——後端計 0〕——活動名 sidebar_state）
    async sidebar_state_save() {
        const activity_bundle = {};

        // 逐欄取現行開合＋欄寬（body 屬性／CSS var＝SSOT）
        for (const sidebar_key of Object.keys(sc_sidebar_instance.sidebar_configs)) {
            const collapsed_flag = sc_sidebar_instance.is_collapsed(sidebar_key) === true ? "1" : "0";

            activity_bundle[sidebar_key + "_collapsed"] = collapsed_flag;
            activity_bundle[sidebar_key + "_width"]     = sc_sidebar_instance.get_width(sidebar_key);
        }

        // 動態載入（registry——用先載）
        const sc_page_activity_instance = await registry().sc_page_activity();

        await sc_page_activity_instance.set_activity("sidebar_state", activity_bundle);
    }
}

export const sidebar_state_save = (function (instance) {
    return function () {

        return instance;
    };
})(new _sidebar_state_save());