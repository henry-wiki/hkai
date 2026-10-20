// jsmods/prompt/req/prompt_send.js —— 送訊息（連線側——一 req 一檔）
// 職責：與伺服器連接部分——prompt_send（組 payload → 後端 prompt_send → 派發；已記錄＝走 response 路徑，未收下先走 error）；進行中請求嘅 controller ＋ 取消（新請求前先 abort 舊）
// 依賴：prompt（get_model_info_data——model_info 全欄位 SSOT；容器加新欄位自動帶上，零 JS 改動）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_send {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連發只認最後一次）
        this.request_abort_controller = null;
    }

    // 送訊息（與伺服器連接部分——只做連線）：組 payload → before 程序 → 後端 prompt_send → 收到嘅資料派去本機側程序
    async prompt_send(request_data, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連發——舊回應一律棄）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（交 post_json；下次會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        // registry 取值（靜態——直回單例）
        const shared   = registry().shared();
        const projects = registry().sc_ai_projects();

        // project_slug：SSOT＝projects 域（唯一取法）；chat_sid：SSOT＝chats 域（唯一取法）
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();
        const chats = registry().sc_ai_chats();

        // 橋接駁（同域——取 model 資訊）
        const prompt_instance = await chats.prompt();

        const payload = {};
            Object.assign(payload, request_data);
            // model 資訊（SSOT＝prompt——容器全欄位）
            Object.assign(payload, prompt_instance.get_model_info_data());
        payload.project_slug = project_slug;
        const chat_instance = await chats.chat();

        payload.chat_sid     = chat_instance.get_chat_sid();

        // before 程序（經 before_send callback——send 流程傳入手柄呼叫 prompt_before_send）
        if (
            callbacks
            &&  callbacks.before_send
        ) {
            callbacks.before_send();
        }

        // 等待後端返回完整 HTML（後端渲染——前端只做 insert；成敗一律由後端判定）
        const result = await shared.sc_request().post_json("/request/hkai/prompt_send/", payload, undefined, request_abort_controller);

        // 判定：已被新請求取代（快速連發——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
        if (thisObject.request_abort_controller !== request_abort_controller) {
            return;
        }

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)
        if (result == null) {
            callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        if (result.ok === false) {
            // 已收下判定（後台帶渲染好嘅訊息＝已記錄——RECOVERABLE）
            let is_recorded_response = false;
            if (result.html) {
                if (result.html.new_message) {
                    is_recorded_response = true;
                }
            }

            // 判定：已記錄 → 對話一部分，走 response 路徑（唔走 error）
            if (is_recorded_response === true) {
                callbacks.success(result);

                return;
            }

            callbacks.error(result);

            return;
        }

        if (result.ok === true) {
            callbacks.success(result);

            return;
        }

        if (
            callbacks
            &&  callbacks.error
        ) {
            callbacks.error(result || { code: "E_UNKNOWN", message: "Unknown error" });

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

export const prompt_send = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_send());
