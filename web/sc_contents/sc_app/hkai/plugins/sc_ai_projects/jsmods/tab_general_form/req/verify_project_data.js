// jsmods/tab_general_form/req/verify_project_data.js —— 驗證專案資料（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——verify_project_data（送全表單；一律 200——result 原樣交 success）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _verify_project_data {
    constructor() {}

    // 驗證專案資料（送全表單——返 ok／唔 ok＋全部欄位錯誤＋最終 slug／資料夾名；後端 SSOT；2026-09-23 自 gateway/project_modal_form.js 搬入）
    // 一律 200——error or not 靠前台 JSON 處理（null＝網絡失敗；其餘 result 原樣交 success）
    async verify_project_data(form_data, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        if (
            callbacks
            &&  callbacks.before_load
        ) {            callbacks.before_load();
        }

        const result = await shared.sc_request().post_json("/request/hkai/verify_project_data/", form_data);

        if (result == null) {
            if (
                callbacks
                &&  callbacks.error
            ) {                callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });
            }
            return;
        }

        if (
            callbacks
            &&  callbacks.success
        ) {            callbacks.success(result);
        }
    }
}

export const verify_project_data = (function (instance) {
    return function () {
        return instance;
    };
})(new _verify_project_data());
