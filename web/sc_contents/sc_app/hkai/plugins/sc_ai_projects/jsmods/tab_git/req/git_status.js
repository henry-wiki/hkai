// jsmods/tab_git/req/git_status.js —— 取單一專案 Git 狀態（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——git_status（唯讀診斷——status bar 用；回傳後端標準結果）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _git_status {
    constructor() {}

    // 取單一專案 Git 狀態（唯讀診斷——status bar 用；回傳後端標準結果）
    async git_status(project_slug) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/git_status/", { project_slug: project_slug || "" });

        // 網路／解析層失敗 → 包成標準錯誤物件
        if (result === null) {
            return { ok: false, error: "request failed" };
        }

        return result;
    }
}

export const git_status = (function (instance) {
    return function () {
        return instance;
    };
})(new _git_status());
