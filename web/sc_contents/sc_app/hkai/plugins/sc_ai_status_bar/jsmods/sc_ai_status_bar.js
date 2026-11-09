// plugins/sc_ai_status_bar/jsmods/sc_ai_status_bar.js —— status bar 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_status_bar()（單例）；域接駁：(await registry().sc_ai_status_bar().status_bar()).act().<動作>() 咁叫
// 職責：① 啟動編排（init——bootstrap 呼叫；域召集＋四區首輪 fetch＋時鐘＋健康反應）② 對外 API 轉發（set_state／set_item 系——跨 plugin 用家）
// 契約：1 域（status_bar）；域動作住 status_bar/status_bar.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_status_bar {
    constructor() {
        this.inited = false;
        this.hooks = {};
    }

    // 啟動 plugin（bootstrap 呼叫——域召集＋同步三項＋時鐘＋健康更新反應登記；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（status_bar 域——事件綁定）
        const status_bar_instance = await this.status_bar();
        await status_bar_instance.init();

        // URL 變化註冊（反應＋觀察器——一次性；觀察器只報真變化）
        await this.obs_url_change();

        return this;
    }

    // URL 變化註冊（反應＋觀察器——一次性；觀察器只報真變化）
    async obs_url_change() {
        const status_bar_domain = await this.status_bar();

        // URL 變化觀察器（一次性——訂閱 sc_http；只報真變化）
        (await status_bar_domain.obs().url_changed_observer()).init();

        // 對話選取觀察器（DOM——卡片變 active 即重取 chat 區；唔靠 URL）
        (await status_bar_domain.obs().chat_observer()).init();

        // 專案選取觀察器（DOM——卡片變 active 即對齊 project 區；唔靠 URL）
        (await status_bar_domain.obs().project_observer()).init();

        // 工作區工具線（workspace_stream 的 git 事件 → sb_git）
        (await status_bar_domain.obs().workspace_git_observer()).init();

        // 出值（唯一路徑——URL 變化與首載共用；init 只接線，唔出值）
        this.register_hook("obs:url_changed", {
            after: async function (goto_url, prev_url) {
                // URL 一變（換對話／換專案）→ 由清單 DOM 即時對齊兩區（零請求）
                status_bar_domain.refresh_project_from_list();
                status_bar_domain.refresh_chat_from_list();

                await start_status_bar_clock(status_bar_domain);
            }
        });

        // 對話選取反應（卡片 active 一變 → 由清單 DOM 即時對齊 chat 區——零請求）
        this.register_hook("obs:chat_active", {
            after: async function () {
                status_bar_domain.refresh_chat_from_list();
            }
        });

        // 專案選取反應（卡片 active 一變 → 由清單 DOM 即時對齊 project 區——零請求）
        this.register_hook("obs:project_active", {
            after: async function () {
                status_bar_domain.refresh_project_from_list();
            }
        });

        // 首載（chats 置 body data-loaded=1 時派 obs:page_loaded——只做一次）
        // project／chat 兩區開頁已由 PHP render 帶出——唔再 fetch（之後變化交 observer／URL 變化）
        registry().sc_ai_chats().register_hook("obs:page_loaded", {
            after: async function () {
                await start_status_bar_clock(status_bar_domain);
            }
        });
    }

    // 對外 API（跨 plugin 用家要 await；名／參數／行為不變——全走同域 act 橋）

    async set_state(item_id, state_key) {
        const status_bar_domain = await this.status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        return status_item_render_instance.set_state(item_id, state_key);
    }

    async set_item(item_id, text) {
        const status_bar_domain = await this.status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        return status_item_render_instance.set_item(item_id, text);
    }

    async set_item_class(item_id, class_name) {
        const status_bar_domain = await this.status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        return status_item_render_instance.set_item_class(item_id, class_name);
    }

    async set_item_hidden(item_id, hidden) {
        const status_bar_domain = await this.status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        return status_item_render_instance.set_item_hidden(item_id, hidden);
    }

    async set_item_tooltip(item_id, text) {
        const status_bar_domain = await this.status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();
        return status_item_render_instance.set_item_tooltip(item_id, text);
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_status_bar.prototype.status_bar = async function () { return (await import("./status_bar/status_bar.js")).status_bar(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_status_bar.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_status_bar = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_status_bar());

// 出值（只起時鐘；project／chat 兩區一律由清單 DOM 對齊——零請求）
// 為何唔再抓後端：標題／名稱本身已經喺清單卡面（workspace 快照會校正），狀態欄直接讀卡面就夠
async function start_status_bar_clock(status_bar_domain) {
    const tick_clock_instance = await status_bar_domain.act().tick_clock();

    tick_clock_instance.start_clock();
}
