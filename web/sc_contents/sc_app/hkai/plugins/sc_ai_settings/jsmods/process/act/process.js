// jsmods/action/process.js —— Process Viewer 分頁（action 層——一 action 一檔）
// 職責：process（fetch 分頁內容（後端渲染 HTML——前端只 insert）＋auto-refresh 鏈＋kill_process；狀態 auto_refresh_* 收本檔）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _process {
    constructor() {
        this.auto_refresh_timer_id = null;
        this.auto_refresh_interval = 1000;
    }

    // 載入（POST section；回傳 Process Viewer 分頁內容 HTML——header 注入 modal__header；失敗回 ""）
    async fetch_process_viewer() {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const result = await shared.sc_request().post_json("/request/hkai/fetch_process_viewer/", { section: "process_viewer" });

        // 網路／解析層失敗 → ""
        if (result === null) {
            return "";
        }

        // 判定：後端叫停（keep_auto_refresh=false）→ 清掉計時器、不啟動自動更新
        if (!this.should_keep_auto_refresh(result)) {
            this.stop_auto_refresh();
            return result;
        }

        // 自己的行為自己接：本頁載入成功 → 啟動自動更新（idempotent——每輪重置鏈）
        this.start_auto_refresh();

        return result;
    }

    // 啟動自動更新（每 1 秒靜默重載——鏈式 setTimeout：fetch 成功才排下一輪）
    start_auto_refresh() {
        const thisObject = this;

        this.stop_auto_refresh();
        this.auto_refresh_timer_id = setTimeout(function () { thisObject.auto_refresh(); }, this.auto_refresh_interval);
    }

    // 停止自動更新（切走 section／關 modal 時呼叫——清掉鏈，不再發 request）
    stop_auto_refresh() {
        if (this.auto_refresh_timer_id) {
            clearTimeout(this.auto_refresh_timer_id);
            this.auto_refresh_timer_id = null;
        }
    }

    // 自動更新一輪（靜默重載——不跳 loading mask；fetch 成功 → setTimeout 排下一輪；失敗 → 停鏈）
    async auto_refresh() {
        // 動態載入（registry——用先載）

        const thisObject = this;

        // spawn 取 modal 狀態（j_modal／active_section）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();

        // 判定：modal 已關（DOM 移除）或已切走 → 停止鏈，不再排下一輪
        if (!modal_module.j_modal || !jQuery.contains(document.body, modal_module.j_modal[0]) || modal_module.active_section !== "process_viewer") {
            this.stop_auto_refresh();
            return;
        }

        const viewer_result = await thisObject.fetch_process_viewer();

        // 判定：抓取失敗（""）→ 停鏈（等下次切回本頁再啟動）
        if (viewer_result === "") {
            this.stop_auto_refresh();
            return;
        }

        await modal_module.reload_section("process_viewer", function () { return viewer_result; }, true);

        // 判定：後端叫停（keep_auto_refresh=false）→ 停鏈，不再抓
        if (!thisObject.should_keep_auto_refresh(viewer_result)) {
            this.stop_auto_refresh();
            return;
        }

        this.start_auto_refresh();
    }

    // 後端是否要繼續自動更新（旗標在回應根層：{ok, keep_auto_refresh, html}）
    should_keep_auto_refresh(viewer_result) {
        // 判定：後端明確叫停（false）→ 停鏈
        if (viewer_result.keep_auto_refresh === false) {
            return false;
        }

        return true;
    }

    // 終止程序（確認 → 後端下殺 → 重載分頁；白名單與實際終止全在後端）
    async kill_process(j_button) {
        // 動態載入（registry——用先載）
        const shared = registry().shared();

        const process_pid  = j_button.attr("data-process_kill");
        const process_program = j_button.attr("data-process_program");

        // 判定：使用者未確認 → 不動作（破壞性操作——標準確認框，讀 data-confirm_msg；動態值 [[key]] 替換）
        const replacements = {};
        replacements["process_program"] = process_program;
        replacements["process_pid"]     = process_pid;

        if (!await shared.sc_web_utils().is_confirmed(j_button, "data-confirm_msg", replacements)) {
            return;
        }

        const result = await shared.sc_request().post_json("/request/hkai/kill_process/", { pid: process_pid });
        // 判定：請求失敗（無回應）→ alert 後停
        if (result === null) {
            alert("Kill failed: request error");
            return;
        }

        // 判定：後端拒絕 → alert 顯示原因（後端為唯一判斷者）
        if (!result.ok) {
            let alert_message = result.message;

            // 判定：後端未帶訊息 → 用預設字串
            if (!alert_message) {
                alert_message = "Kill failed";
            }

            alert(alert_message);
        }

        // 重載分頁（後端渲染——被殺的會顯示 last beat，之後自然消失；切頁編排在 nav 層）
        // spawn 取組（modal_nav）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        await modal_nav_module.show_section("process_viewer");
    }
}

export const process = (function (instance) {
    return function () {

        return instance;
    };
})(new _process());
