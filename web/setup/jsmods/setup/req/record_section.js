// jsmods/setup/req/record_section.js —— 格選擇記錄請求（連線側——一 req 一檔；檔名／函數名＝endpoint 名）
// 職責：與伺服器連接部分——record_section（組 payload（value_pairs（呼叫端收集——名＋值對））→ 後端 record_section.php → 派發）；單發（撳「下一步」一次——唔設 abort·唔可以漏）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup_request_headers } from "../request_headers.js";

class _record_section {
    constructor() {}

    // 格選擇記錄請求（與伺服器連接部分——只做連線：組 payload → 後端 record_section.php → 收到嘅資料派去本機側程序）
    async record_section(value_pairs, callbacks) {
        const web_base = jQuery("body").attr("data-web_base");

        // 欄位契約（統一）：name 已係 formdata[section_id][check_item_code][value]——逐對原樣入 params
        const value_params = new URLSearchParams();

        // 逐對入 params（盲收——空值照交·收唔收由後台決定）
        let value_pair_index;

        for (value_pair_index = 0; value_pair_index < value_pairs.length; value_pair_index++) {
            value_params.append(value_pairs[value_pair_index][0], value_pairs[value_pair_index][1]);
        }

        let result = null;

        // 連線（網絡失敗 → null——交三態派發判）
        try {
            const response = await fetch(web_base + "/setup_request/record_section", {
                method: "POST",
                headers: setup_request_headers(),
                body: value_params.toString()
            });

            result = await response.json();
        }
        catch (error) {
            result = null;
        }

        // 三態派發：null ⇒ E_NETWORK，ok false ⇒ callbacks.error，ok true ⇒ callbacks.success（回呼一律 await——完成語義＝回呼行完）
        if (result === null) {
            await callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        if (result.ok !== true) {
            await callbacks.error(result);

            return;
        }

        await callbacks.success(result);
    }
}

export const record_section = (function (instance) {
    return function () {
        return instance;
    };
})(new _record_section());
