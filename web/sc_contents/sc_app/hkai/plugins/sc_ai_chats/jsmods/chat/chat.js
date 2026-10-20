// jsmods/chat/chat.js —— chat 域（唯一入口——evt 住檔頂；出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().chat()).evt()／act()／req()／obs().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req／obs 全部接喺度；每條自載）

class _chat {
    constructor() {
        this.inited = false;
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（chat evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 對話網址（chat_sid 空字串 → 專案根＝未選擇對話）
    build_chat_url(project_slug, chat_sid) {
        // 判定：未選擇對話 → 專案根
        if (!chat_sid) {
            return "/" + project_slug + "/";
        }

        return "/" + project_slug + "/" + chat_sid + "/";
    }

    // 對外取「URL 對話段原值」（連線層用——原值照帶去後端，交後端判斷；sync＝純讀、免 await）
    get_url_chat_sid() {
        return this.get_chat_sid_from_url(location.href);
    }

    // 對外取「現時選取嘅對話 sid」——純 sid（語境形 keyword@<chat_sid> → 剝語境取 @ 後；跨 plugin 端點用；要 URL 段原值嘅連線層用 get_url_chat_sid；sync＝純讀、免 await）
    get_chat_sid() {
        const url_chat_sid = this.get_url_chat_sid();

        // 無 @ → 純 sid 原值
        if (url_chat_sid.indexOf("@") === -1) {
            return url_chat_sid;
        }

        // 語境形 → @ 後（第一個 @ 分界）
        return url_chat_sid.slice(url_chat_sid.indexOf("@") + 1);
    }

    // 由指定 URL 抽「對話 sid」（URL 第二段——URL 變化事件帶前後兩條 URL 時，抽前一條用；sync＝純讀、免 await）
    get_chat_sid_from_url(url) {
        return new URL(url).pathname.split("/")[2] || "";
    }

    // 由指定 URL 抽「清單語境」——歸檔語境＝"archive"（歸檔列面 archive／瀏覽歸檔對話 archive@<chat_sid>）；普通＝""（sync＝純讀、免 await）
    get_chat_list_context_from_url(url) {
        const chat_segment = this.get_chat_sid_from_url(url);

        // 純關鍵字（archive——歸檔列面）
        if (chat_segment === "archive") {
            return "archive";
        }

        // 語境形（archive@<chat_sid>——瀏覽歸檔對話）
        if (chat_segment.indexOf("archive@") === 0) {
            return "archive";
        }

        return "";
    }

    // 依 message_poll jobs 逐個對話同步狀態屬性（已搬 chat/act/chat_activity.js::chat_list_status_refresh——data-status 純 CSS 閘）

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }

}

// 連接（出入口掛上 prototype）
_chat.prototype.evt = function () { return new _chat_evt_bridge(); };
_chat.prototype.act = function () { return new _chat_act_bridge(); };
_chat.prototype.req = function () { return new _chat_req_bridge(); };
_chat.prototype.obs = function () { return new _chat_obs_bridge(); };

export const chat = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat());

// ── 動作表（bridge——接駁 act／evt／req／obs 四個目錄嘅 function；每條自載）──

// evt（事件入口）
class _chat_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.chat_add()).init();
        (await this.chat_archive()).init();
        (await this.chat_archive_switch()).init();
        (await this.chat_delete()).init();
        (await this.chat_item_switch()).init();
        (await this.chat_restore()).init();
    }
    async chat_add()     { return (await import("./evt/chat_add.js")).chat_add();            }
    async chat_archive() { return (await import("./evt/chat_archive.js")).chat_archive();    }
    async chat_archive_switch() { return (await import("./evt/chat_archive_switch.js")).chat_archive_switch(); }
    async chat_delete()  { return (await import("./evt/chat_delete.js")).chat_delete();      }
    async chat_item_switch()  { return (await import("./evt/chat_item_switch.js")).chat_item_switch();      }
    async chat_restore()      { return (await import("./evt/chat_restore.js")).chat_restore();              }
}

// act（動作——開單叫工作）
class _chat_act_bridge {
    constructor() {}
    init() {

    }
    async chat_activity() { return (await import("./act/chat_activity.js")).chat_activity();   }
    async chat_archive() { return (await import("./act/chat_archive.js")).chat_archive();      }
    async chat_restore() { return (await import("./act/chat_restore.js")).chat_restore();      }
    async chat_delete() { return (await import("./act/chat_delete.js")).chat_delete();         }
    async chat_item_fetch() { return (await import("./act/chat_item_fetch.js")).chat_item_fetch(); }
    async chat_item_insert() { return (await import("./act/chat_item_insert.js")).chat_item_insert(); }
    async chat_item_remove() { return (await import("./act/chat_item_remove.js")).chat_item_remove(); }
    async chat_item_switch() { return (await import("./act/chat_item_switch.js")).chat_item_switch(); }
    async chat_list_fetch() { return (await import("./act/chat_list_fetch.js")).chat_list_fetch(); }
    async chat_list_refresh() { return (await import("./act/chat_list_refresh.js")).chat_list_refresh(); }
    async chat_start_new() { return (await import("./act/chat_start_new.js")).chat_start_new(); }
    async detect_chat_list_context_change() { return (await import("./act/detect_chat_list_context_change.js")).detect_chat_list_context_change(); }
    async detect_chat_sid_change() { return (await import("./act/detect_chat_sid_change.js")).detect_chat_sid_change(); }
    async detect_project_slug_change() { return (await import("./act/detect_project_slug_change.js")).detect_project_slug_change(); }
    async sync_archive_link() { return (await import("./act/sync_archive_link.js")).sync_archive_link(); }
}

// req（出街工作）
class _chat_req_bridge {
    constructor() {}
    init() {

    }
    async chat_archive() { return (await import("./req/chat_archive.js")).chat_archive();      }
    async chat_restore() { return (await import("./req/chat_restore.js")).chat_restore();      }
    async chat_delete() { return (await import("./req/chat_delete.js")).chat_delete();         }
    async chat_item_fetch() { return (await import("./req/chat_item_fetch.js")).chat_item_fetch(); }
    async chat_list_fetch() { return (await import("./req/chat_list_fetch.js")).chat_list_fetch(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _chat_obs_bridge {
    constructor() {}
    async page_loaded_observer() { return (await import("./obs/page_loaded_observer.js")).page_loaded_observer(); }
    async chat_item_observer() { return (await import("./obs/chat_item_observer.js")).chat_item_observer(); }
    async url_changed_observer() { return (await import("./obs/url_changed_observer.js")).url_changed_observer(); }
}
