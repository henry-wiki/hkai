// jsmods/model_selector/req/fetch_models.js —— 單一 provider model 列表抓取（連線側——一 req 一檔；檔名／函數名＝endpoint 名）
// 職責：與伺服器連接部分——fetch_models（組 payload（provider_id（呼叫端 declare——目標））→ 後端 provider/fetch_models → 派發）；進行中請求嘅 controller ＋ 取消（快速連切——新請求前先 abort 舊）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _fetch_models {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連切只認最後一次）
        this.request_abort_controller = null;
    }

    // 單一 provider model 列表抓取（與伺服器連接部分——只做連線：組 payload → 後端 provider/fetch_models → 收到嘅資料派去本機側程序；進行中請求自管可取消——新請求前先 abort 舊）
    async fetch_models(provider_id, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連切——舊回應一律棄，唔會蓋新內容）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下次切換會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const payload = {};
        payload.provider_id = provider_id;

        // before 程序（經 before_load callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_load
        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/provider/fetch_models/", payload, undefined, request_abort_controller);

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
            await callbacks.success(result);   // await——成功回呼行完先算完成

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

export const fetch_models = (function (instance) {
    return function () {
        return instance;
    };
})(new _fetch_models());
