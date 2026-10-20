// sc_app/shared/jsmods/sc_request.js —— app 級共用 HTTP 請求模組（ESM 模組）
// 載入：hkai/jsmods/bootstrap.js → registry.js → shared/jsmods/shared.js（ESM 鏈）
// 取用：registry().shared().sc_request().post_json(url, payload)
// 職責：統一 POST JSON（CSRF header／JSON body／錯誤處理）——各 plugin 不再各自重複實作

import sc_web_utils from "./sc_web_utils.js";

class _sc_request {
    constructor() {
    }

    // POST JSON（回傳：成功＝解碼後物件；網路／解析／逾時／呼叫端 abort 失敗＝null；timeout_ms 省略 → 不設逾時；abort_controller 省略 → 內部自建）
    async post_json(request_url, request_payload, timeout_ms, abort_controller) {
        // 判定：呼叫端未交 controller → 內部自建（唔影響既有呼叫端）
        if (!abort_controller) {
            abort_controller = new AbortController();
        }

        let abort_timer_id = null;

        // 指定逾時 → 時間到自動 abort（同一個 controller——呼叫端亦可隨時 abort）
        if (timeout_ms) {
            abort_timer_id = setTimeout(() => {
                abort_controller.abort();
            }, timeout_ms);
        }

        try {
            const response = await fetch(request_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": sc_web_utils().get_csrf_token(),
                },
                body: JSON.stringify(request_payload),
                signal: abort_controller.signal,
            });


            return await response.json();
        } catch (fetch_error) {

          // 判定：呼叫端主動 abort（快速連切——舊請求設計性取消）→ 靜默回 null（唔當失敗、唔登錯誤）
          if (fetch_error.name === "AbortError") {

                return null;
            }

            // 網路／解析／逾時失敗 → null（呼叫端依此判定失敗）
            console.error("request failed: " + request_url, fetch_error.message);
            return null;
        } finally {

            // 有計時器才清（無逾時時不殘留 timer）
            if (abort_timer_id !== null) {
                clearTimeout(abort_timer_id);
            }
        }
    }
}

// 出口（factory——同 hkai/jsmods/registry.js 模式；shared().sc_request() 取單例）
export default (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_request());
