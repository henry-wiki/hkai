// jsmods/prompt/prompt.js —— prompt 域（唯一入口——出入口掛喺 prototype）
// 用途：(await registry().sc_ai_chats().prompt()).evt()／act()／req().<動作>() 咁叫（F12 逐條跳得到）
// 契約：動作表住本檔尾（bridge class——act／evt／req／obs 全部接喺度；每條自載）

class _prompt {
    constructor() {
        this.inited = false;
        // home 輸入用：後端回嘅 playground 專案 slug（URL 冇專案時 prompt_send 用）
        this.composer_project_slug = "";
    }

    // 讀 composer 專案 slug（home 輸入——URL 冇 slug 時嘅後備）
    get_composer_project_slug() {
        return this.composer_project_slug;
    }

    // 寫 composer 專案 slug（prompt_fetch 成功後由後端回填）
    set_composer_project_slug(project_slug) {
        this.composer_project_slug = project_slug || "";
    }

    // 讀 composer hidden chat_sid（新 prompt 由後端配；未輸入前 URL 未換）
    get_composer_chat_sid() {
        var j_hidden_chat_sid = jQuery(".sc_composer__hidden_fields input[data-model_field='chat_sid']");

        // 判定：hidden 未載入 → 空
        if (j_hidden_chat_sid.length < 1) {
            return "";
        }

        return j_hidden_chat_sid.val();
    }

    // 現行對話 sid（URL 優先；新 prompt 未換 URL → 取 composer hidden）
    async get_active_chat_sid() {
        const chat_instance = await registry().sc_ai_chats().chat();
        var chat_sid = chat_instance.get_chat_sid();

        // 判定：URL 有 sid → 用佢
        if (chat_sid !== "") {
            return chat_sid;
        }

        return this.get_composer_chat_sid();
    }

    // 首次成功寫草稿之後：URL 未帶 sid → 靜默換（重載可憑 sid 取回）
    async sync_chat_url(project_slug) {
        const chat_instance = await registry().sc_ai_chats().chat();

        // 判定：URL 已有 sid 段 → 唔做
        if (chat_instance.get_chat_sid() !== "") {
            return;
        }

        var chat_sid = this.get_composer_chat_sid();

        // 判定：未有 sid → 唔做
        if (chat_sid === "") {
            return;
        }

        // 判定：冇 project_slug（根頁 "/"）→ 唔改 URL（否則會砌出 "//{sid}/"＝當成網域）
        if (project_slug === "") {
            return;
        }

        sc_url.shallow_replace_url("/" + project_slug + "/" + chat_sid + "/");
    }

    // 啟動域（sc_ai_chats.init 召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 事件綁定（prompt evt 家族——分組見 bind_events）
        await this.bind_events();

        return this;
    }

    // 讀 model_info 容器全欄位（data-model_field 為 key——容器加新欄位自動帶上，零 JS 改動）
    get_model_info_data() {
        const model_info_data = {};
        for (const field_input of jQuery("[data-is='model_info']").find("input[data-model_field]")) {
            model_info_data[field_input.dataset.model_field] = field_input.value;
        }
        return model_info_data;
    }

    // 開域召集（橋各自 init）
    async bind_events() {
        await this.evt().init();
        await this.act().init();
        await this.req().init();
    }
}

// 連接（出入口掛上 prototype）
_prompt.prototype.evt = function () { return new _prompt_evt_bridge(); };
_prompt.prototype.act = function () { return new _prompt_act_bridge(); };
_prompt.prototype.req = function () { return new _prompt_req_bridge(); };
_prompt.prototype.obs = function () { return new _prompt_obs_bridge(); };

export const prompt = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt());

// ── 動作表（bridge——接駁 act／evt／req／obs 四個目錄嘅 function；每條自載）──

// evt（事件入口）
class _prompt_evt_bridge {
    constructor() {}
    // 綁定召集（開域一次——逐條拎 instance 再 init）
    async init() {
        (await this.agent_switch()).init();
        (await this.prompt_attach()).init();
        (await this.prompt_draft_restore()).init();
        (await this.prompt_draft_save()).init();
        (await this.prompt_enter_send()).init();
        (await this.prompt_gen_random()).init();
        (await this.prompt_input()).init();
        (await this.prompt_send()).init();
        (await this.prompt_stop()).init();
    }
    async agent_switch() { return (await import("./evt/evt_agent_switch.js")).agent_switch(); }
    async prompt_attach()      { return (await import("./evt/evt_prompt_attach.js")).prompt_attach(); }
    async prompt_draft_restore() { return (await import("./evt/evt_prompt_draft_restore.js")).prompt_draft_restore(); }
    async prompt_draft_save() { return (await import("./evt/evt_prompt_draft_save.js")).prompt_draft_save(); }
    async prompt_enter_send() { return (await import("./evt/evt_prompt_enter_send.js")).prompt_enter_send(); }
    async prompt_gen_random() { return (await import("./evt/evt_prompt_gen_random.js")).prompt_gen_random(); }
    async prompt_input()      { return (await import("./evt/evt_prompt_input.js")).prompt_input(); }
    async prompt_send()       { return (await import("./evt/evt_prompt_send.js")).prompt_send(); }
    async prompt_stop()       { return (await import("./evt/evt_prompt_stop.js")).prompt_stop(); }
}

// act（動作——開單叫工作）
class _prompt_act_bridge {
    constructor() {}
    init() {

    }
    async prompt_draft_save()        { return (await import("./act/act_prompt_draft_save.js")).prompt_draft_save(); }
    async prompt_fetch()           { return (await import("./act/act_prompt_fetch.js")).prompt_fetch(); }
    async prompt_send()               { return (await import("./act/act_prompt_send.js")).prompt_send(); }
    async prompt_resume()             { return (await import("./act/act_prompt_resume.js")).prompt_resume(); }
    async prompt_send_button_toggle() { return (await import("./act/act_prompt_send_button_toggle.js")).prompt_send_button_toggle(); }
    async prompt_stop()               { return (await import("./act/act_prompt_stop.js")).prompt_stop(); }
}

// req（出街工作）
class _prompt_req_bridge {
    constructor() {}
    init() {

    }
    async agent_switch() { return (await import("./req/req_agent_switch.js")).agent_switch(); }
    async prompt_fetch() { return (await import("./req/req_prompt_fetch.js")).prompt_fetch(); }
    async draft_create() { return (await import("./req/req_draft_create.js")).draft_create(); }
    async prompt_send()  { return (await import("./req/req_prompt_send.js")).prompt_send(); }
    async prompt_resume() { return (await import("./req/req_prompt_resume.js")).prompt_resume(); }
    async prompt_stop()  { return (await import("./req/req_prompt_stop.js")).prompt_stop(); }
    async upload_attachment() { return (await import("./req/req_upload_attachment.js")).upload_attachment(); }
    async request_delete_draft_attachment() { return (await import("./req/req_request_delete_draft_attachment.js")).request_delete_draft_attachment(); }
    async draft_save_meta() { return (await import("./req/req_draft_save_meta.js")).draft_save_meta(); }
    async draft_fetch() { return (await import("./req/req_draft_fetch.js")).draft_fetch(); }
}

// obs（DOM 觀察器——變化派 hook 信號；唔判斷）
class _prompt_obs_bridge {
    constructor() {}
    async prompt_input_height_observer() { return (await import("./obs/prompt_input_height_observer.js")).prompt_input_height_observer(); }
}
