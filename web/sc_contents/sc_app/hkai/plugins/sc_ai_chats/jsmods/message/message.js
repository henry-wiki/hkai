// jsmods/message/message.js —— message 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().message()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——evt／act／req／obs 全部接喺度；每條自載）

class _message {
    constructor() {
        this.inited = false;
        // 「係唔係最底」容差（px——可設定；例：scrollHeight 2000、位置 1950-2000 → 真）
        this.at_bottom_threshold = 60;
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（message evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 取得該訊息的 wrapper（.sc_thread__viewport 內 .message_wrapper[data-message_id]——每次即時取；AJAX 替換 .sc_workspace 後仍有效）
    get_j_message_panel(message_id) {
        const j_message_panel = jQuery(".sc_thread__viewport").find(
            ".message_wrapper[data-message_id=" + message_id + "]",
        );

        return j_message_panel;
    }

    // 是否在最底（scrollTop＋clientHeight 距 scrollHeight ≤ 容差＝真；愈接近底愈真）
    is_at_bottom() {
        const j_messages = jQuery(".sc_thread__viewport");

        const scroll_height = j_messages.prop("scrollHeight");
        const client_height = j_messages.prop("clientHeight");
        const scroll_top = j_messages.scrollTop();

        return (scroll_height - scroll_top - client_height) <= this.at_bottom_threshold;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_message.prototype.evt = function () { return new _message_evt_bridge(); };
_message.prototype.act = function () { return new _message_act_bridge(); };
_message.prototype.req = function () { return new _message_req_bridge(); };
_message.prototype.obs = function () { return new _message_obs_bridge(); };

export const message = (function (instance) {
    return function () {

        return instance;
    };
})(new _message());

// ── 動作表（bridge——接駁 act／evt／req／obs 四個目錄嘅 function；每條自載）──

// evt（事件入口）
class _message_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.message_copy()).init();
        (await this.message_delete()).init();
        (await this.message_dots()).init();
        (await this.message_intersection_observer()).init();
        (await this.message_mutation_observer()).init();
        (await this.message_zoom()).init();
        (await this.message_scroll_to_bottom()).init();
        (await this.message_scroll_to_top()).init();
        (await this.permission_decide()).init();
    }
    async message_copy()                  { return (await import("./evt/message_copy.js")).message_copy(); }
    async message_delete()                { return (await import("./evt/message_delete.js")).message_delete(); }
    async message_dots()                  { return (await import("./evt/message_dots.js")).message_dots(); }
    async message_intersection_observer() { return (await import("./evt/message_intersection_observer.js")).message_intersection_observer(); }
    async message_mutation_observer()     { return (await import("./evt/message_mutation_observer.js")).message_mutation_observer(); }
    async message_zoom()                  { return (await import("./evt/message_zoom.js")).message_zoom(); }
    async message_scroll_to_bottom()              { return (await import("./evt/message_scroll_to_bottom.js")).message_scroll_to_bottom(); }
    async message_scroll_to_top()                  { return (await import("./evt/message_scroll_to_top.js")).message_scroll_to_top(); }
    async permission_decide()                      { return (await import("./evt/permission_decide.js")).permission_decide(); }
}

// act（動作——開單叫工作）
class _message_act_bridge {
    constructor() {}
    init() {

    }
    async message_delete()    { return (await import("./act/message_delete.js")).message_delete(); }
    async message_writer()    { return (await import("./act/message_writer.js")).message_writer(); }
    async message_poll()      { return (await import("./act/message_poll.js")).message_poll(); }
    async message_stream()    { return (await import("./act/message_stream.js")).message_stream(); }
    async message_list_fetch()    { return (await import("./act/message_list_fetch.js")).message_list_fetch(); }
    async message_list_default()    { return (await import("./act/message_list_default.js")).message_list_default(); }
    async message_frame_build()    { return (await import("./act/message_frame_build.js")).message_frame_build(); }
    async message_frame_apply()    { return (await import("./act/message_frame_apply.js")).message_frame_apply(); }
    async message_scroll_to_bottom()  { return (await import("./act/message_scroll_to_bottom.js")).message_scroll_to_bottom(); }
    async message_scroll_to_top()     { return (await import("./act/message_scroll_to_top.js")).message_scroll_to_top(); }
    async message_render()    { return (await import("./act/message_render.js")).message_render(); }
    async message_editblock()  { return (await import("./act/message_editblock.js")).message_editblock(); }
    async message_load_more()  { return (await import("./act/message_load_more.js")).message_load_more(); }
    async message_item_render()        { return (await import("./act/message_item_render.js")).message_item_render(); }
    async message_above_count_check()   { return (await import("./act/message_above_count_check.js")).message_above_count_check(); }
    async message_above_height_check()  { return (await import("./act/message_above_height_check.js")).message_above_height_check(); }
    async message_load_more_decide()    { return (await import("./act/message_load_more_decide.js")).message_load_more_decide(); }
    async permission_decide()    { return (await import("./act/permission_decide.js")).permission_decide(); }
}

// req（出街工作）
class _message_req_bridge {
    constructor() {}
    init() {

    }
    async message_delete()  { return (await import("./req/message_delete.js")).message_delete(); }
    async message_poll()    { return (await import("./req/message_poll.js")).message_poll(); }
    async message_list_fetch()  { return (await import("./req/message_list_fetch.js")).message_list_fetch(); }
    async permission_decide()  { return (await import("./req/permission_decide.js")).permission_decide(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _message_obs_bridge {
    constructor() {}
    async message_intersection_observer() { return (await import("./obs/message_intersection_observer.js")).message_intersection_observer(); }
    async message_rail_active_observer() { return (await import("./obs/message_rail_active_observer.js")).message_rail_active_observer(); }
    async message_prompt_observer() { return (await import("./obs/message_prompt_observer.js")).message_prompt_observer(); }
    async message_position_keeper() { return (await import("./obs/message_position_keeper.js")).message_position_keeper(); }
}
