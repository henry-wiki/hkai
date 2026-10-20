// jsmods/setup/req/start_single_check.js —— 單項重跑請求（連線側——一 req 一檔）
// 職責：與伺服器連接部分——start_single_check（組 payload（section_id／check_item_code（呼叫端 declare——目標））→ 後端 start_single_check.php → 派發）；進行中請求嘅 controller ＋ 取消（快速連按——新請求前先 abort 舊）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

import { setup_request_headers } from "../request_headers.js";

class _start_single_check {
    constructor() {
        // 進行中請求嘅 controller（新請求前先 abort 舊嘅——快速連按只認最後一次）
        this.request_abort_controller = null;
    }

    // 檢查項重跑請求（與伺服器連接部分——只做連線：組 payload → 後端 start_single_check.php → 收到嘅資料派去本機側程序；進行中請求自管可取消——新請求前先 abort 舊）
    async start_single_check(section_id, check_item_code, callbacks) {
        const thisObject = this;

        // 取消上一個未完成嘅請求（快速連按——舊回應一律棄，唔會蓋新卡）
        thisObject.abort_pending_request();

        thisObject.request_abort_controller = new AbortController();

        // 本次請求嘅 controller（下次請求會被 abort）
        const request_abort_controller = thisObject.request_abort_controller;

        const web_base = jQuery("body").attr("data-web_base");

        // 欄位契約（統一）：formdata[section_id][check_item_code]（唔帶值——重跑檢查）
        const value_params = new URLSearchParams();
        value_params.set("formdata[" + section_id + "][" + check_item_code + "]", "");

        let result = null;

        // 連線（網絡失敗 → null——交三態派發判）
        try {
            const response = await fetch(web_base + "/setup_request/start_single_check", {
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

        // 判定：已被新請求取代（快速連按——abort 咗嘅舊請求）→ 靜默（唔派任何回呼）
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

    // 取消進行中嘅請求（快速連按——新請求前先殺舊；冇進行中＝冇事）
    abort_pending_request() {
        // 判定：有進行中 → abort 兼清
        if (this.request_abort_controller) {
            this.request_abort_controller.abort();
            this.request_abort_controller = null;
        }
    }
}

export const start_single_check = (function (instance) {
    return function () {
        return instance;
    };
})(new _start_single_check());
