// jsmods/action/settings_modal.js —— Settings modal 全流程（action 層——一 action 一檔）
// 職責：settings_modal（建 modal → 渲染 → section reload → slots 插入；狀態 j_modal／active_section 單一持有；佈局＝left nav＋right content——section 切換編排在 modal_nav）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _settings_modal {
    constructor() {
        this.j_modal = null;
        this.active_section = "";
        this.inited = false;
    }

    // 啟動 modal（inited 防重）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        return this;
    }

    // 開啟 modal；callback = 開殼後回呼（j_modal 傳入）
    async open_modal(callback) {
        // 動態載入（registry——用先載）
        const modal = registry().sc_modal();

        const j_modal = await modal.create_modal({
            modal_id     : "settings_modal",
            modal_title  : "Settings",
            window_class : "modal__window--lg",
            body         : "<div class='modal__navigation'></div><div class='modal__content' id='settings_content'></div>",
        });
        j_modal.addClass("visible");

        this.j_modal = j_modal;

        // 綁定 modal 內互動（原 modal_event.bind_modal——已移 bind/ 一事件一檔，entry 啟動時 document 委派〔[data-modal='settings_modal'] 範圍〕註冊一次）

        if (typeof callback === "function") {
            callback.apply(this, [j_modal]);
        }
    }

    // 渲染選單＋預設 section（fetch settings navigation → 注入 nav 容器 → 交 nav 層載 server 頁）
    async render_setting_data(j_modal, section) {
        // 動態載入（registry——用先載）

        // spawn 取 fetch 組（handler——settings_fetch）
        const settings_domain = await (await registry().sc_ai_settings()).settings();
        const fetch_module = await settings_domain.req().settings_fetch();

        // fetch setting navigation
        const result = await fetch_module.fetch_settings();

        // 判定：fetch 失敗 → 不渲染（維持現狀）
        if (result === null) {
            return;
        }
        j_modal.find(".modal__navigation").empty();
        j_modal.find(".modal__navigation").append(result.html.navigation);

        // 顯示按鈕指定分頁（data-tab 來；內容載入——切頁編排在 nav 層）
        // spawn 取組（modal_nav）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_nav_module = await modal_domain.act().modal_nav();

        await modal_nav_module.show_section(section);
    }

    // section 資料取得（新分頁在此登記——回傳 {html:{header,footer,body}}；失敗回 ""）
    async fetch_section_data(section) {
        // 動態載入（registry——用先載）

        // 判定：general → General 分頁（settings 第一頁；後端渲染 HTML）
        if (section === "general") {
            // spawn 取組（general 分頁）
            const general_domain = await (await registry().sc_ai_settings()).general();
            const general_module = await general_domain.act().general();

            return await general_module.fetch_general();
        }

        // 判定：server → server 設定資料
        if (section === "server") {
            // spawn 取組（server 分頁）
            const server_domain = await (await registry().sc_ai_settings()).server();
        const server_module = await server_domain.act().server();

            return await server_module.fetch_server_settings();
        }

        // 判定：process_viewer → Process Viewer 分頁（後端渲染 HTML）
        if (section === "process_viewer") {
            // spawn 取組（process 分頁）
            const process_domain = await (await registry().sc_ai_settings()).process();
        const process_module = await process_domain.act().process();

            return await process_module.fetch_process_viewer();
        }

        // 判定：provider → Provider 分頁（一頁列全部 provider）
        if (section === "provider") {
            // spawn 取組（provider 分頁）
            const provider_domain = await (await registry().sc_ai_settings()).provider();
            const provider_module = await provider_domain.act().provider();

            return await provider_module.fetch_providers();
        }

        // 判定：about → 關於 HKAI 分頁（後端渲染 HTML）
        if (section === "about") {
            // spawn 取組（about 分頁）
            const about_domain = await (await registry().sc_ai_settings()).about();
            const about_module = await about_domain.act().about();

            return await about_module.fetch_about();
        }

        // 判定：about → 關於 HKAI 分頁（後端渲染 HTML）
        if (section === "about") {
            // spawn 取組（about 分頁）
            const about_domain = await (await registry().sc_ai_settings()).about();
            const about_module = await about_domain.act().about();

            return await about_module.fetch_about();
        }

        // 判定：system_prompt／system_env → Instructions 分頁（兩子共用一條）
        const instructions_sections = ["system_prompt", "system_env"];
        if (instructions_sections.includes(section)) {
            // spawn 取組（instructions 分頁）
            const instructions_domain = await (await registry().sc_ai_settings()).instructions();
            const instructions_module = await instructions_domain.act().instructions();

            return await instructions_module.fetch_instructions(section);
        }
        return "";
    }

    // 重載 section（mask → fetcher → insert；Refresh 類按鈕走這條——後端渲染，前端只 insert）
    async reload_section(section, fetcher, silent) {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        const j_content = this.j_modal.find(".modal__content");

        // 判定：非靜默 → 顯示 loading 遮罩（自動更新走靜默——不閃遮罩）
        if (!silent) {
            loading.start_panel_loading(j_content, {
                mask: {
                    bgcolor: "#000000",
                    opacity: .5
                },
                text : "Loading..."
            });
        }

        const result = await fetcher();

        if (!silent) {
            loading.stop_loading(j_content);
        }
        // 判定：無資料（fetch 失敗）→ 維持現狀
        if (result === "" || result === null) {
            return;
        }

        // spawn 取組（instructions——編輯器生命週期）
        const instructions_domain = await (await registry().sc_ai_settings()).instructions();
        const instructions_module = await instructions_domain.act().instructions();

        // 判定：非靜默 → 換頁前先卸載 Instructions 編輯器（殘留實例——無實例＝no-op）
        if (!silent) {
            instructions_module.teardown_editor();
        }

        this.apply_setting_page(result, section, silent);

        // 判定：非靜默 → 插入後掛載 Instructions 編輯器（頁內無 mount 節點＝其它分頁、自動略過）
        if (!silent) {
            instructions_module.mount_editor(this.j_modal);
        }
    }

    // 插入 section 內容（template → modal__header-content／modal__content；slots 填 data-slot）
    // silent＝自動輪詢：只換 slots，不動外層／標題列／旋轉圖示
    apply_setting_page(result, section, silent) {
        const j_modal_header_content = this.j_modal.find(".modal__header-content");
        const j_content = this.j_modal.find(".modal__content");

        // 判定：靜默更新且有 slots → 只換槽位後結束（首載與手動觸發仍走整塊插入）
        if (silent && result.html.slots) {
            this.replace_slots(this.j_modal, result.html.slots);
            return;
        }

        // 判定：帶 template（固定外殼契約）→ 外殼插入後，逐槽填入動態內容
        if (result.html.template) {
            j_modal_header_content.empty();
            j_modal_header_content.append(result.html.template.header);
            j_content.empty();
            j_content.append(result.html.template.body + result.html.template.footer);
            this.replace_slots(j_content, result.html.slots);
            return;
        }

        // 舊契約（header／body／footer 三件——其餘分頁沿用）
        j_modal_header_content.empty();
        j_modal_header_content.append(result.html.header);
        j_content.empty();

        // footer 可選（缺＝後端只回 header／body——免 'undefined' 字串漏入畫面）
        let footer_html = result.html.footer;
        // 判定：未提供 → 空字串
        if (!footer_html) {
            footer_html = "";
        }
        j_content.append(result.html.body + footer_html);
    }

    // 槽位替換（只換 [data-slot='key'] 的容器；空值或找不到目標就略過，不破壞畫面）
    replace_slots(j_scope, slots) {
        // 判定：無槽位資料 → 不做
        if (!slots) {
            return;
        }

        const slot_keys = Object.keys(slots);

        for (let i = 0; i < slot_keys.length; i++) {
            const slot_key = slot_keys[i];
            const slot_html = slots[slot_key];

            // 判定：空內容 → 略過（保留原容器，下一輪仍能填入）
            if (!slot_html) {
                continue;
            }

            const j_target = j_scope.find("[data-slot='" + slot_key + "']");

            // 判定：找不到目標 → 略過
            if (!j_target.length) {
                continue;
            }

            j_target.replaceWith(slot_html);
        }
    }
}

export const settings_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _settings_modal());
