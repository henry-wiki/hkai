// jsmods/project/req/project_list_fetch.js —— 專案清單載入（連線側——一 handler 一檔；檔名／函數名＝endpoint 名）
// 職責：與伺服器連接部分——project_list_fetch（組 payload（select_project_slug——由 URL 讀）→ 後端 project_list_fetch → 派發）；進行中請求嘅 controller ＋ 取消（新請求前先 abort 舊）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _project_list_fetch {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連發只認最後一次）
        this.request_abort_controller = null;
    }

    // 專案清單載入（與伺服器連接部分——只做連線：組 payload（slug 由 URL 讀）→ 後端 project_list_fetch → 收到嘅資料派去本機側程序）
    async project_list_fetch(callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連發——舊回應一律棄）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下次會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // select_project_slug：SSOT＝projects 域（由 URL 讀——唯一取法）；後端據此標 active
        const project_instance = await registry().sc_ai_projects().project();

        const payload = {};
        payload.select_project_slug = project_instance.get_project_slug();

        // before 程序（經 before_load callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_load
        ) {            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/project_list_fetch/", payload, undefined, request_abort_controller);

        // 判定：已被新請求取代（快速連發——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
        if (thisObject.request_abort_controller !== request_abort_controller) {
            return;
        }

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)
        if (result == null) {
            if (
                callbacks
                &&  callbacks.error
            ) {                callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });
            }

            return;
        }

        if (result.ok === false) {
            if (
                callbacks
                &&  callbacks.error
            ) {                callbacks.error(result);
            }

            return;
        }

        if (result.ok === true) {
            if (
                callbacks
                &&  callbacks.success
            ) {                callbacks.success(result);
            }

            return;
        }
    }

    // 取消進行中嘅請求（新請求前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中 → abort 兼清
        if (this.request_abort_controller) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }
}

export const project_list_fetch = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_list_fetch());
