// jsmods/bootstrap.js —— ESM entry（class + boot）
// 載入鏈：bootstrap → registry.js（各模組一律動態 import——用先載；訊息／prompt 域 init 歸 chats 編排）
// 例外：i18n（boot 編排需要——直接 import；其餘一律動態 import）
import registry from "./registry.js";
import { sc_i18n } from "./sc_i18n/sc_i18n.js";

class _bootstrap {
    // 啟動（DOM ready 後行一次——依序 init 專案／對話／訊息模組；其餘模組用到先載）
    async boot() {
        // i18n 字典（重要——直接 import；開機即取）
        sc_i18n().init();

        // 動態載入（registry——用先載）
        registry().sc_loading().init({
            "page_loading": {
                mask: {
                    bgcolor : "rgba(0,0,0,.3)"
                }
            }, "panel_loading": {
                mask: {
                    bgcolor : "rgba(0,0,0,.3)"
                }
            }
        });


        registry().sc_loading().start_page_loading();

        // sidebar 統一管理（rail 收合掣綁定＋收合狀態還原）
        const sidebar = await registry().sc_sidebar();

        await sidebar.init();

        // topnav（頂欄掣綁定——重新整理／複製路徑）
        const topnav = await registry().sc_topnav();

        await topnav.init();

        const projects = registry().sc_ai_projects();

        await projects.init();

        const chats = registry().sc_ai_chats();

        await chats.init();

        // status bar（事件綁定＋初值同步＋時鐘＋健康更新反應登記；喺 health 之前——首輪就註冊到）
        await registry().sc_ai_status_bar().init();

        // health 心跳（全 plugin init 之後才起——首輪 health 帶得到頁面語境；清單載入由 obs:page_loaded 驅動）
        await registry().sc_ai_health().init();

        // model selection（模型選擇器 modal——bind 註冊＋modal 暖載；動態 accessor＝兩步式）
        const providers = await registry().sc_ai_providers();

        await providers.init();

        // benchmark（排行榜 modal——bind 註冊；動態 accessor＝兩步式）
        const benchmark = await registry().sc_ai_benchmark();

        await benchmark.init();

        // settings modal（gear／status bar 觸發——bind 註冊＋modal 暖載；動態 accessor＝兩步式）
        const settings = await registry().sc_ai_settings();

        await settings.init();

        registry().sc_loading().stop_loading();

        // 編排完成 → 亮頁面載入旗標（body data-loaded 由 0 改 1；專案側觀察到即載入清單）
        document.body.setAttribute("data-loaded", "1");
    }
}

// DOM ready 才啟動
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
        new _bootstrap().boot();
    });
} else {
    new _bootstrap().boot();
}
