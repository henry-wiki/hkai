// jsmods/message/req/message_poll.js —— message_poll 一輪請求（連線側——一 req 一檔）
// 職責：與伺服器連接部分——message_poll（組 payload（project_slug＋chat_sid＋acquired_responses）→ 後端 message_poll → 派發）；進行中請求嘅 controller ＋ 取消（新一輪前先 abort 舊輪——慢輪唔會疊）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_poll {
    constructor() {
        // 進行中請求嘅 controller（新一輪前先 abort 舊嘅——重整／換對話只認最後一次）
        this.request_abort_controller = null;
    }

    // 一輪 message_poll（與伺服器連接部分——只做連線：組 payload → 後端 message_poll → 收到嘅資料派去本機側程序；進行中請求自管可取消——新一輪前先 abort 舊）
    async message_poll(chat_sid, acquired_responses, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（新一輪前先殺舊輪——慢輪唔會疊）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下一輪會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared   = registry().shared();
        const projects = registry().sc_ai_projects();

        // project_slug：SSOT＝projects 域（唯一取法）；chat_sid：呼叫端傳入（顯式——poll 層必須有）
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();

        const payload = {};
        payload.project_slug = project_slug;
        payload.chat_sid     = chat_sid;

        // 已獲取申報（per 訊息 per 段——response_id＋已收長度；後台據此裁增量）
        payload.acquired_responses = acquired_responses || {};

        // before 程序（經 before_load callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_load
        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/message_poll/", payload, undefined, request_abort_controller);

        // 判定：已被下一輪取代（abort 咗嘅舊輪）→ 靜默（唔派任何回呼）
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

    // 取消進行中嘅請求（新一輪前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中 → abort 兼清
        if (this.request_abort_controller) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }
}

export const message_poll = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_poll());
