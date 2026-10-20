// jsmods/queue/req/queue_list.js —— 等待列載入（連線側——一 req 一檔）
// 職責：與伺服器連接部分——queue_list（組 payload（project_slug＋chat_sid）→ 後端 queue_list → 派發）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _queue_list {
    constructor() {}

    // 等待列載入（與伺服器連接部分——只做連線：組 payload → 後端 queue_list → 收到嘅資料派去本機側程序）
    async queue_list(callbacks) {
        // registry 取值（靜態——直回單例）
        const shared   = registry().shared();
        const projects = registry().sc_ai_projects();

        // project_slug：SSOT＝projects 域（唯一取法）；chat_sid：SSOT＝chats 域（唯一取法）
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();
        const chats = registry().sc_ai_chats();

        const payload = {};
        payload.project_slug = project_slug;
        const chat_instance = await chats.chat();

        payload.chat_sid     = chat_instance.get_chat_sid();

        // before 程序（經 before_load callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_load
        ) {
            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/queue_list/", payload);

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)
        if (result == null) {
            callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        if (result.ok === false) {
            callbacks.error(result);

            return;
        }

        if (result.ok === true) {
            callbacks.success(result);

            return;
        }
    }
}

export const queue_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue_list());
