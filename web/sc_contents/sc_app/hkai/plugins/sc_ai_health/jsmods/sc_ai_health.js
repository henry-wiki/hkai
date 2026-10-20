// plugins/sc_ai_health/jsmods/sc_ai_health.js —— health plugin 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_health()（單例）；域接駁：(await registry().sc_ai_health().health()).act().<動作>() 咁叫
// 職責：① 啟動編排（init——bootstrap 呼叫；域召集＋抓取引擎——顯示中先開）② 對外 API 轉發（stop／restart）③ 分頁可見性調節（顯示中先跑、切走＝停、返嚟＝即跳；多頁並存＝各自跑；背景零 /health 請求）
// 契約：1 域（health）；域動作住 health/health.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_health {
    constructor() {
        this.inited = false;
        this.hooks = {};
        this.visibility_state = document.visibilityState;   // 平台原樣值——"visible"／"hidden"（信號更新；restart 閘用）
    }

    // 啟動 plugin（bootstrap 取例項後呼叫——域召集＋抓取引擎（顯示中先開）；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（health 域——事件綁定）
        const health_instance = await this.health();
        await health_instance.init();

        // 橋接駁（health 域 act 橋——抓取引擎；啟動決定權喺呢度：顯示中先開）
        const fetch_health_instance = await health_instance.act().fetch_health();
        fetch_health_instance.init();

        // 分頁可見性信號 hook（obs:visibility_changed——原樣收平台值；切走＝停心跳／返嚟＝即跳）
        const thisObject = this;

        this.register_hook("obs:visibility_changed", {
            before: function () {

            },
            after: function (visibility_state) {
                thisObject.visibility_state = visibility_state;

                // 判定：頁面隱藏（切走咗）→ 停（清排程＋中止進行中請求）
                if (visibility_state === "hidden") {
                    thisObject.stop();
                } else {
                    // 頁面返嚟 → 重啟（停 → 立即重跳一輪）
                    thisObject.restart();
                }
            }
        });

        // 分頁可見性觀察（obs 模組只綁＋派信號——做咩喺上面 hook；背景零 /health 請求）
        (await health_instance.obs().visibility_changed_observer()).init();

        // 判定：載入時顯示中 → 開（背景載入唔開——等切返嚟先跳）
        if (thisObject.visibility_state === "visible") {
            fetch_health_instance.start();
        }

        return this;
    }

    // 停止（清排程＋中止進行中請求）
    async stop() {
        // 橋接駁（health 域 act 橋）
        const health_instance = await this.health();
        const fetch_health_instance = await health_instance.act().fetch_health();

        return fetch_health_instance.stop();
    }

    // 重啟抓取（停 → 立即重跳——發送新訊息後喚醒偵測新任務；本頁唔顯示唔跳）
    async restart() {
        // 判定：本頁唔可見 → 唔跳（顯示中先跳——隱藏咗引擎停咗，返嚟自然跳）
        if (this.visibility_state !== "visible") {
            return;
        }

        // 橋接駁（health 域 act 橋——抓取引擎）
        const health_instance = await this.health();
        const fetch_health_instance = await health_instance.act().fetch_health();

        return fetch_health_instance.restart();
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_health.prototype.health = async function () { return (await import("./health/health.js")).health(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_health.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_health = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_health());
