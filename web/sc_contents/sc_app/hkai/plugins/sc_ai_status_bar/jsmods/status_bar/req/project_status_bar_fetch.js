// jsmods/status_bar/req/project_status_bar_fetch.js —— project 區 HTML 重取（連線側——一 req 一檔；檔名／函數名＝endpoint 名）
// 職責：與伺服器連接部分——project_status_bar_fetch（組 payload（project_slug＋chat_sid（URL 對話段原值））→ 後端 project_status_bar_fetch → 派發）；進行中請求嘅 controller ＋ 取消（快速連切——新請求前先 abort 舊）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_status_bar_fetch {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連切只認最後一次）
        this.request_abort_controller = null;
    }

    // project 區 HTML 重取（與伺服器連接部分——只做連線：組 payload → 後端 project_status_bar_fetch → 收到嘅資料派去本機側程序）
    async project_status_bar_fetch(callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連切——舊回應一律棄，唔會蓋新區塊）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下次切換會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // project_slug：SSOT＝projects 域（唯一取法）；chat_sid：URL 對話段原值（照帶交後端判斷）
        const project_instance = await registry().sc_ai_projects().project();
        const chat_instance    = await registry().sc_ai_chats().chat();

        const payload = {};
        payload.project_slug = project_instance.get_project_slug();
        payload.chat_sid     = chat_instance.get_url_chat_sid();

        const result = await shared.sc_request().post_json("/request/hkai/project_status_bar_fetch/", payload, undefined, request_abort_controller);

        // 判定：已被新請求取代（快速連切——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
        if (thisObject.request_abort_controller !== request_abort_controller) {
            return;
        }

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)（回呼一律 await——完成語義＝回呼行完）
        if (result == null) {
            await callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });   // await——回呼行完先算完成

            return;
        }

        if (result.ok === false) {
            await callbacks.error(result);   // await——回呼行完先算完成

            return;
        }

        if (result.ok === true) {
            await callbacks.success(result);   // await——回呼行完先算完成

            return;
        }
    }

    // 取消進行中嘅請求（快速連切——新請求前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中 → abort 兼清
        if (this.request_abort_controller) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }
}

export const project_status_bar_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_status_bar_fetch());
