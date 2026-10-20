// jsmods/setup/req/switch_section.js —— 切格請求（連線側——一 req 一檔；動作名＝檔名）
// 職責：與伺服器連接部分——switch_section（組 payload（section_id）→ 後端 fetch_section.php → 派發）；進行中請求嘅 controller ＋ 取消（快速連切——新請求前先 abort 舊）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup_request_headers } from "../request_headers.js";

class _switch_section {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連切只認最後一次）
        this.request_abort_controller = null;
    }

    // 切格請求（與伺服器連接部分——只做連線：組 payload → 後端 fetch_section.php → 收到嘅資料派去本機側程序；進行中請求自管可取消——新請求前先 abort 舊）
    async switch_section(section_id, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連切——舊回應一律棄，唔會蓋新內容）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（下次切換會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        const web_base = jQuery("body").attr("data-web_base");

        const value_params = new URLSearchParams();
        value_params.set("section", section_id);

        const suffix = thisObject.build_pass_segment();

        let result = null;

        // 連線（網絡失敗 → null——交三態派發判）
        try {
            const response = await fetch(web_base + "/setup_request/fetch_section" + suffix, {
                method: "POST",
                headers: setup_request_headers(),
                body: value_params.toString(),
                signal: request_abort_controller.signal
            });

            result = await response.json();
        }
        catch (error) {
            result = null;
        }

        // 判定：已被新請求取代（快速連切——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
        if (thisObject.request_abort_controller !== request_abort_controller) {
            return;
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

    // 取消進行中嘅請求（快速連切——新請求前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中 → abort 兼清
        if (this.request_abort_controller) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }

    // 傳遞 segment（URL ?force_result=——有先跟入；layout 測試覆寫）
    build_pass_segment() {
        const pass_params = new URLSearchParams();

        const force_matches = window.location.search.match(/[?&]force_result=([^&]+)/);

        // 判定：URL 有 force_result → 入去（layout 測試覆寫）
        if (force_matches !== null) {
            pass_params.set("force_result", force_matches[1]);
        }

        const query_text = pass_params.toString();

        // 判定：冇任何傳遞參數 → 空
        if (query_text === "") {
            return "";
        }

        return "?" + query_text;
    }
}

export const switch_section = (function (instance) {
    return function () {
        return instance;
    };
})(new _switch_section());
