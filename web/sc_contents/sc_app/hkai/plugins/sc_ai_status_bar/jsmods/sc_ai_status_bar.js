// plugins/sc_ai_status_bar/jsmods/sc_ai_status_bar.js —— status bar 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_status_bar()（單例）；域接駁：(await registry().sc_ai_status_bar().status_bar()).act().<動作>() 咁叫
// 職責：① 啟動編排（init——bootstrap 呼叫；域召集＋四區首輪 fetch＋時鐘＋健康反應）② 對外 API 轉發（set_state／set_item 系／sync_model——跨 plugin 用家）
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

        // 出值（唯一路徑——URL 變化與首載共用；init 只接線，唔出值）
        this.register_hook("obs:url_changed", {
            after: async function (goto_url, prev_url) {
                await refresh_status_bar_regions(status_bar_domain);
            }
        });

        // 首載出值（chats 置 body data-loaded=1 時派 obs:page_loaded——只做一次）
        registry().sc_ai_chats().register_hook("obs:page_loaded", {
            after: async function () {
                await refresh_status_bar_regions(status_bar_domain);
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

    async sync_model() {
        const status_bar_domain = await this.status_bar();
        const sync_status_items_instance = await status_bar_domain.act().sync_status_items();
        return sync_status_items_instance.sync_model();
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

// 出值（status bar 四區一次過重取：三區 fetch＋model＋git＋時鐘起錶）
async function refresh_status_bar_regions(status_bar_domain) {
    const project_fetch_instance   = await status_bar_domain.act().project_fetch();
    const chat_fetch_instance      = await status_bar_domain.act().chat_fetch();
    const workspace_fetch_instance = await status_bar_domain.act().workspace_fetch();

    project_fetch_instance.project_fetch();
    chat_fetch_instance.chat_fetch();
    workspace_fetch_instance.workspace_fetch();

    const sync_status_items_instance = await status_bar_domain.act().sync_status_items();
    await sync_status_items_instance.sync_model();

    const refresh_git_item_instance = await status_bar_domain.act().refresh_git_item();
    refresh_git_item_instance.refresh_git_item();

    const tick_clock_instance = await status_bar_domain.act().tick_clock();
    tick_clock_instance.start_clock();
}
