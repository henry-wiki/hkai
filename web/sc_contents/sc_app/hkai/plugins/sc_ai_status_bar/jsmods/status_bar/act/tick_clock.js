// jsmods/action/tick_clock.js —— status bar 時鐘每秒刷新（action 層——一 action 一檔）
// 職責：tick_clock（HH:MM → sb_clock；start_clock 起 1 秒 interval，tick 寫值）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _tick_clock {
    constructor() {
        // 時鐘 interval id（start_clock 建立）
        this.clock_timer_id = null;
    }

    // 時鐘（HH:MM——每秒更新，跨分即時）
    start_clock() {
        const thisObject = this;

        thisObject.tick();
        thisObject.clock_timer_id = setInterval(function () {
            thisObject.tick();
        }, 1000);
    }

    // 寫入目前時間（HH:MM——sb_clock）
    async tick() {
        const now_time = new Date();
        const hour_text = String(now_time.getHours()).padStart(2, "0");
        const minute_text = String(now_time.getMinutes()).padStart(2, "0");

        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        status_item_render_instance.set_item("sb_clock", hour_text + ":" + minute_text);
    }
}

export const tick_clock = (function (instance) {
    return function () {

        return instance;
    };
})(new _tick_clock());
