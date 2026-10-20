// jsmods/handler/fetch_health.js —— health 一輪請求（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——fetch_health（組 payload（count／time／project_slug／chat_sid）→ POST fetch_health → 返解析結果；失敗／逾時／中止＝null）
// 備註：用原生 fetch＋外部 signal（唔用 sc_request().post_json——本請求要中止控制：stop() 即時中止、逾時自動中止）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _fetch_health {
    constructor() {
        this.health_url = "/request/hkai/fetch_health/";
    }

    // 一輪 health（連線側——只做連線：組 payload → 後端 fetch_health → 返結果）
    async fetch_health(request_data) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const thisObject = this;

        // 請求資料（POST JSON——count 由引擎帶入；time＝當下毫秒）
        const request_payload = {};
        request_payload["count"] = request_data["count"];
        request_payload["time"] = Date.now();

        // 頁面語境（project_slug＝projects 域、chat_sid＝chats 域——各取自家、即時狀態）
        const project_instance = await registry().sc_ai_projects().project();
        const chat_instance    = await registry().sc_ai_chats().chat();

        request_payload["project_slug"] = project_instance.get_project_slug();
        request_payload["chat_sid"]     = chat_instance.get_url_chat_sid();

        try {
            const response = await fetch(thisObject.health_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": shared.sc_web_utils().get_csrf_token(),
                },
                body: JSON.stringify(request_payload),
                signal: request_data["request_signal"],
            });

            return await response.json();
        } catch (fetch_error) {
            // health 失敗／逾時／中止不做事——本輪結束（下一輪照排；心跳維持失敗亦不阻斷）
            return null;
        }
    }
}

export const fetch_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _fetch_health());
