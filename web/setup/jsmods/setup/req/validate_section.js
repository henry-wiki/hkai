// jsmods/setup/req/validate_section.js —— 下一步儲存＋驗證請求（連線側——一 req 一檔；動作名＝檔名）
// 職責：validate_section（組 payload（section＋fields）→ 後端 validate_section → 派發）
// 結構同 setup 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup_request_headers } from "../request_headers.js";

class _validate_section {
    constructor() {}

    // 儲存＋驗證（網絡失敗 → null，交呼叫端判）
    async validate_section(section_id, field_pairs) {
        const web_base = jQuery("body").attr("data-web_base");

        const value_params = new URLSearchParams();
        value_params.set("section", section_id);

        // 逐個 field 入 payload（field 名本身已是 formdata[...][value] 完整名）
        for (const field_pair of field_pairs) {
            const field_name  = field_pair[0];
            const field_value = field_pair[1];

            value_params.append(field_name, field_value);
        }

        let result = null;

        try {
            const response = await fetch(web_base + "/setup_request/validate_section", {
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

export const validate_section = (function (instance) {
    return function () {

        return instance;
    };
})(new _validate_section());
