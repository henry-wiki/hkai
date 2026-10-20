// jsmods/register.js —— setup 模組註冊表（typed bridging——動態載入）
// 寫法同 shared/jsmods/shared.js：class + 單例（register() 回傳單例）
// 契約：register().{模組名}() 回傳該模組單例——動態 accessor 皆 async（呼叫端要 await）；靜態 accessor 直回（await 照通）
// 載入：動態模組＝await import()、靜態批＝頂層 import（一律 literal path——編輯器照樣解析型別、F12 可跳）
// 例外（靜態——開機即需）：shared／setup／sc_loading（accessor 直回、唔使 await）
// 次序：靜態載入嘅放最上、動態載入嘅放下面（新加嘅照跟）
// 結構：class 單例 + export default factory（本檔由 bootstrap 靜態 import——保持 default）

import { shared }     from "./shared/shared.js";
import { setup }      from "./setup/setup.js";
import { sc_loading } from "./sc_loading/sc_loading.js";

class _register {
    constructor() {
    }

    // shared（靜態——開機即需：共用域註冊表——shared().sc_web_utils()／shared().sc_http()／shared().sc_request()／shared().sc_alert()）
    shared() {
        return shared();
    }

    // setup（靜態——開機即需；setup 精靈域）
    setup() {
        return setup();
    }

    // sc_loading（靜態——開機即需：大區域載入遮罩）
    sc_loading() {
        return sc_loading();
    }
}

const register = (function (/** @type {_register} */ instance) {
    return function () {

        return instance;
    };
})(new _register());

export default register;

// 全域接駁（路線 1——任何檔案直接叫 register()、免 import）
window.register = register;
