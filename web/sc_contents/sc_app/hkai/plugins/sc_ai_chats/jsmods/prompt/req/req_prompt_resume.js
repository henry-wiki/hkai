// jsmods/prompt/req/req_prompt_resume.js —— 重入通知（連線側——一 req 一檔）
// 職責：POST /request/hkai/prompt_resume/（chat_sid → 後端掃 polling 箱補派工）；回傳後端標準結果

class _req_prompt_resume {
    constructor() {}

    // 通知伺服器：叫未開工嘅回覆接手（chat_sid＝當前對話）
    async prompt_resume(chat_sid) {
        const shared = registry().shared();

        const payload = {};
        payload.chat_sid = chat_sid;

        const result = await shared.sc_request().post_json("/request/hkai/prompt_resume/", payload);

        return result;
    }
}

export const prompt_resume = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_prompt_resume());
