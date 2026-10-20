// jsmods/status_bar/act/chat_fetch.js —— chat 區動態載入（act 層——一檔一事）
// 職責：chat_fetch（叫 req 攞返 chat 區 status bar HTML → 換返畫面上嗰區——值由後端出）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_fetch {
    constructor() {}

    // chat 區重取（換返 .sc_statusbar--chat 整個 wrapper）
    async chat_fetch() {
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const chat_status_bar_fetch_instance = await status_bar_domain.req().chat_status_bar_fetch();

        await chat_status_bar_fetch_instance.chat_status_bar_fetch({
            success: async function (response_data) {
                jQuery(".sc_statusbar--chat").replaceWith(response_data.html.status_bar);
            },
            error: async function (response_data) {
                // 失敗（維持現狀——等下次 URL 變化再攞）
            },
        });
    }
}

export const chat_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_fetch());
