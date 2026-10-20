// jsmods/project/project.js —— project 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_projects().project()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req 全部接喺度；每條自載）

class _project {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_projects.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（project evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 對外取「現時專案 slug」（跨 plugin 用家；SSOT＝URL 第一段——切換時 URL 同步改，唯一取法；sync＝純讀、免 await）
    get_project_slug() {
        return this.get_project_slug_from_url(location.href);
    }

    // 由指定 URL 抽「專案 slug」（URL 第一段——URL 變化事件帶前後兩條 URL 時，抽前一條用；sync＝純讀、免 await）
    get_project_slug_from_url(url) {
        return new URL(url).pathname.split("/")[1] || "";
    }

    // 由指定 URL 抽「清單語境」——歸檔語境＝"archive"（歸檔列面 archive／瀏覽歸檔專案 archive@<project_slug>）；普通＝""（sync＝純讀、免 await）
    get_project_list_context_from_url(url) {
        const project_segment = this.get_project_slug_from_url(url);

        // 純關鍵字（archive——歸檔列面）
        if (project_segment === "archive") {
            return "archive";
        }

        // 語境形（archive@<project_slug>——瀏覽歸檔專案）
        if (project_segment.indexOf("archive@") === 0) {
            return "archive";
        }

        return "";
    }

    // 對外組「專案 URL」（跨模組用家；格式＝/{project_slug}/——切換時寫 URL 用；sync＝純讀、免 await）
    build_project_url(project_slug) {
        return "/" + project_slug + "/";
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_project.prototype.evt = function () { return new _project_evt_bridge(); };
_project.prototype.act = function () { return new _project_act_bridge(); };
_project.prototype.obs = function () { return new _project_obs_bridge(); };
_project.prototype.req = function () { return new _project_req_bridge(); };

export const project = (function (instance) {
    return function () {

        return instance;
    };
}) (new _project());

// ── 動作表（bridge——接駁 act／evt／req 三個目錄嘅 function；每條自載）──

// evt（事件入口）
class _project_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.project_item_fetch()).init();
        (await this.project_archive_switch()).init();
        (await this.project_restore()).init();
    }
    async project_item_fetch()     { return (await import("./evt/project_item_fetch.js")).project_item_fetch(); }
    async project_archive_switch() { return (await import("./evt/project_archive_switch.js")).project_archive_switch(); }
    async project_restore()        { return (await import("./evt/project_restore.js")).project_restore(); }
}

// act（動作——開單叫工作）
class _project_act_bridge {
    constructor() {}
    init() {

    }
    async project_list_fetch() { return (await import("./act/project_list_fetch.js")).project_list_fetch(); }
    async project_item_fetch() { return (await import("./act/project_item_fetch.js")).project_item_fetch(); }
    async project_item_insert() { return (await import("./act/project_item_insert.js")).project_item_insert(); }
    async project_item_remove() { return (await import("./act/project_item_remove.js")).project_item_remove(); }
    async project_list_refresh() { return (await import("./act/project_list_refresh.js")).project_list_refresh(); }
    async project_list_status_refresh() { return (await import("./act/project_list_status_refresh.js")).project_list_status_refresh(); }
    async project_list_rank_refresh() { return (await import("./act/project_list_rank_refresh.js")).project_list_rank_refresh(); }
    async detect_project_slug_change() { return (await import("./act/detect_project_slug_change.js")).detect_project_slug_change(); }
    async detect_project_list_context_change() { return (await import("./act/detect_project_list_context_change.js")).detect_project_list_context_change(); }
    async project_restore() { return (await import("./act/project_restore.js")).project_restore(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _project_obs_bridge {
    constructor() {}
    async page_loaded_observer() { return (await import("./obs/page_loaded_observer.js")).page_loaded_observer(); }
    async project_item_observer() { return (await import("./obs/project_item_observer.js")).project_item_observer(); }
    async url_changed_observer() { return (await import("./obs/url_changed_observer.js")).url_changed_observer(); }
}

// req（出街工作）
class _project_req_bridge {
    constructor() {}
    init() {

    }
    async project_list_fetch()  { return (await import("./req/project_list_fetch.js")).project_list_fetch(); }
    async project_item_fetch()  { return (await import("./req/project_item_fetch.js")).project_item_fetch(); }
    async project_restore()     { return (await import("./req/project_restore.js")).project_restore(); }
}
