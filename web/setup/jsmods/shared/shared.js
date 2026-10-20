// sc_app/shared/jsmods/shared.js —— shared 域模組註冊表（typed bridging——同 hkai/jsmods/registry.js 模式）
// 契約：shared().{模組名}() 回傳該模組單例；方法名＝模組檔名（去 .js，不用 get）
// 用途：跨域取用 shared 模組（例：shared().sc_web_utils()／shared().sc_http()）
// 注意：只在頂層 import、不在頂層呼叫（單例由各模組 factory 建立——避免循環初始化 TDZ）

import _sc_web_utils from "./sc_web_utils.js";
import _sc_http      from "./sc_http.js";
import _sc_request   from "./sc_request.js";
import _sc_alert     from "./sc_alert/sc_alert.js";

class _shared {
    constructor() {
    }


    sc_web_utils() {
        return _sc_web_utils();
    }

    // sc_http
    sc_http() {
        return _sc_http();
    }

    // sc_request
    sc_request() {
        return _sc_request();
    }

    // sc_alert（通用訊息彈窗——modal ＋ toast）
    sc_alert() {
        return _sc_alert();
    }
}

// 出口（factory——同 hkai/jsmods/registry.js 模式；shared() 取單例）
export const shared = (function (instance) {
    return function () {
        return instance;
    };
})(new _shared());
