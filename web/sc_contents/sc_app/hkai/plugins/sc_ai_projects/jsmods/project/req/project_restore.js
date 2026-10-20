// jsmods/project/req/project_restore.js —— 還原專案（連線側——一 req 一檔）
// 職責：與伺服器連接部分——project_restore（組 payload（request_data）→ 後端 project_restore → 派發）；進行中請求嘅 controller ＋ 取消（快速連按——新請求前先 abort 舊）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _project_restore {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連按只認最後一次）
        this.request_abort_controller = null;
    }

    // 還原專案（與伺服器連接部分——只做連線）：組 payload → before 程序 → 後端 project_restore → 收到嘅資料派去本機側程序；進行中請求自管可取消
    async project_restore(request_data, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連按——舊回應一律棄，唔會蓋新結果）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下次連按會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const payload = {};
            Object.assign(payload, request_data);

        // before 程序（經 before_restore callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_restore
        ) {
            callbacks.before_restore();
        }

        const result = await shared.sc_request().post_json("/request/hkai/project_restore/", payload, undefined, request_abort_controller);

        // 判定：已被新請求取代（快速連按——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
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

    // 取消進行中嘅請求（快速連按——新請求前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中請求 → 中止
        if (this.request_abort_controller !== null) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }
}

export const project_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_restore());
