// jsmods/prompt/act/act_prompt_resume.js —— 重入通知流程（act 層——一檔一事）
// 職責：prompt_resume（接線 handler——chat_sid 交 req；回應只入 console，唔改畫面）

class _act_prompt_resume {
    constructor() {}

    // 通知伺服器補派工（頁面重入時一次；回傳 notified 數）
    async prompt_resume(chat_sid) {
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_resume_instance = await prompt_instance.req().prompt_resume();

        const result = await prompt_resume_instance.prompt_resume(chat_sid);

        // 判定：後端有補派 → 記錄（除錯用；畫面交 poll 反映）
        if (result && result.ok === true && result.notified > 0) {
            sc_console("prompt", "resume").log("notified=" + result.notified);
        }

        return result;
    }
}

export const prompt_resume = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_prompt_resume());
