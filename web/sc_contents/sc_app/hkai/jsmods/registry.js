// jsmods/registry.js —— 跨目錄共用模組註冊表（typed bridging——動態載入）
// 寫法同 sc_ai_health.js：class + 單例（registry() 回傳單例）
// 契約：registry().{模組名}() 回傳該模組單例——動態 accessor 皆 async（呼叫端要 await）；靜態 accessor 直回（await 照通）
// 用途：跨 dir／js 取用其他模組（例：sc_ai_providers → sc_ai_chats）
// 載入：動態模組＝await import()、靜態批＝頂層 import（一律 literal path——編輯器照樣解析型別、F12 可跳）
// 例外（靜態——開機即需）：sc_loading／sc_modal／sc_i18n／shared／sc_ai_projects／sc_ai_chats／sc_ai_status_bar／sc_ai_health（accessor 直回、唔使 await）
// 次序：靜態載入嘅放最上、動態載入嘅放下面（新加嘅照跟）
// 結構：class 單例 + export default factory（本檔由 bootstrap／各模組靜態 import——保持 default）

import { sc_loading } from "./sc_loading/sc_loading.js";
import { sc_modal }   from "./sc_modal/sc_modal.js";
import { sc_i18n }    from "./sc_i18n/sc_i18n.js";
import { shared }     from "../../shared/jsmods/shared.js";
import { sc_ai_projects } from "../plugins/sc_ai_projects/jsmods/sc_ai_projects.js";
import { sc_ai_chats }    from "../plugins/sc_ai_chats/jsmods/sc_ai_chats.js";
import { sc_ai_status_bar } from "../plugins/sc_ai_status_bar/jsmods/sc_ai_status_bar.js";
import { sc_ai_health } from "../plugins/sc_ai_health/jsmods/sc_ai_health.js";

class _registry {
    constructor() {
    }

    // sc_loading（靜態——開機即需：loading 處理器唔可以等動態載入——放最上）
    sc_loading() {
        return sc_loading();
    }

    // sc_modal（靜態——開機即需）
    sc_modal() {
        return sc_modal();
    }

    // sc_i18n（靜態——開機即需）
    sc_i18n() {
        return sc_i18n();
    }

    // shared（靜態——開機即需）
    shared() {
        return shared();
    }

    // sc_ai_projects（靜態——開機即需）
    sc_ai_projects() {
        return sc_ai_projects();
    }

    // sc_ai_chats（靜態——開機即需）
    sc_ai_chats() {
        return sc_ai_chats();
    }

    // sc_ai_status_bar（靜態——開機即需）
    sc_ai_status_bar() {
        return sc_ai_status_bar();
    }

    // sc_ai_health（靜態——開機即需）
    sc_ai_health() {
        return sc_ai_health();
    }

    // sc_topnav（動態載入——用先載）
    async sc_topnav() {
        const { sc_topnav } = await import("./sc_topnav/sc_topnav.js");
        return sc_topnav();
    }

    // sc_sidebar（動態載入——用先載）
    async sc_sidebar() {
        const { sc_sidebar } = await import("./sc_sidebar/sc_sidebar.js");
        return sc_sidebar();
    }

    // sc_markdown（動態載入——用先載）
    async sc_markdown() {
        const { sc_markdown } = await import("./sc_markdown/sc_markdown.js");
        return sc_markdown();
    }

    // sc_editor（動態載入——用先載；Markdown 編輯器服務——每語境 create_editor）
    async sc_editor() {
        const { sc_editor } = await import("./sc_editor/sc_editor.js");
        return sc_editor();
    }

    // sc_page_activity（動態載入——用先載）
    async sc_page_activity() {
        const { sc_page_activity } = await import("./sc_page_activity/sc_page_activity.js");
        return sc_page_activity();
    }

    // sc_ai_providers（動態載入——用先載）
    async sc_ai_providers() {
        const { sc_ai_providers } = await import("../plugins/sc_ai_providers/jsmods/sc_ai_providers.js");
        return sc_ai_providers();
    }

    // sc_ai_benchmark（動態載入——用先載）
    async sc_ai_benchmark() {
        const { sc_ai_benchmark } = await import("../plugins/sc_ai_benchmark/jsmods/sc_ai_benchmark.js");
        return sc_ai_benchmark();
    }

    // sc_ai_settings（動態載入——用先載）
    async sc_ai_settings() {
        const { sc_ai_settings } = await import("../plugins/sc_ai_settings/jsmods/sc_ai_settings.js");
        return sc_ai_settings();
    }


}


const registry = (function (/** @type {_registry} */ instance) {
    return function () {

        return instance;
    };
})(new _registry());

export default registry;

// 全域接駁（路線 1——任何檔案直接叫 registry()、免 import）
window.registry = registry;
