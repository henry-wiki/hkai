// jsmods/setup/req/check_migration_source.js —— 舊安裝搬遷掃描請求（連線側——一 req 一檔；動作名＝檔名）
// 職責：check_migration_source（組 payload（source_dir）→ 後端 check_migration_source → 派發）
// 結構同 setup 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup_request_headers } from "../request_headers.js";

class _check_migration_source {
    constructor() {}

    // 掃描舊安裝（網絡失敗 → null，交呼叫端判）
    async check_migration_source(source_dir) {
        const web_base = jQuery("body").attr("data-web_base");

        const value_params = new URLSearchParams();
        value_params.set("source_dir", source_dir);

        let result = null;

        try {
            const response = await fetch(web_base + "/setup_request/check_migration_source", {
                method: "POST",
                headers: setup_request_headers(),
                body: value_params.toString()
            });

            result = await response.json();
        }
        catch (error) {
            result = null;
        }

        return result;
    }
}

export const check_migration_source = (function (instance) {
    return function () {

        return instance;
    };
})(new _check_migration_source());
