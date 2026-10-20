// jsmods/sc_sidebar/sc_sidebar.js —— Sidebar 統一管理（rail／topbar 收合掣 → 側欄收合／展開；狀態隨後台 sidebar_state 活動記住）
// rail 掣：收合類綁定喺本檔（統管）；其餘 rail evt 綁定喺 evt/rail.js（evt 層——一檔一事）
// 結構同 sc_topnav：class + IIFE 單例（registry().sc_sidebar() 取用）
// 收合全由純 CSS 閘（body[data-projects-collapsed]／body[data-chats-collapsed]）——此處只切狀態、同步掣文字
// 開機狀態：後台讀活動隨 SSR 落（body 旗標＋欄寬 CSS var）——此處唔重置，只照讀同步
// 側欄狀態活動（sidebar_state）＝拖曳調寬完寫入——交 act/sidebar_state_save.js（act 層）

class _sc_sidebar {
    constructor() {
        // 拖曳狀態（拖拉調寬進行中——未拖＝null）
        this.resize_drag = null;

        // 兩欄一齊掣（rail 最頂——一撳全收／全開；selector 集中一處）
        this.all_sidebars_button_selector = "[data-action='toggle_all_sidebars']";

        // 兩欄設定（對稱）：收合旗標屬性／掣／文字／欄寬上下限
        this.sidebar_configs = {
            "projects": {
                data_attr: "data-projects-collapsed",
                button_selector: "[data-action='toggle_projects_sidebar']",
                close_title: "收合專案欄",
                open_title: "展開專案欄",
                sidebar_selector: ".sc_sidebar--projects",
                min_width: 200,
                max_width: 300,
                default_width: 220,
            },
            "chats": {
                data_attr: "data-chats-collapsed",
                button_selector: "[data-action='toggle_chats_sidebar']",
                close_title: "向右收起對話欄",
                open_title: "展開對話欄",
                sidebar_selector: ".sc_sidebar--chats",
                min_width: 200,
                max_width: 300,
                default_width: 260,
            },
        };
    }

    // 啟動（bootstrap 編排——一次）：預設展開＋綁收合掣＋綁 rail evt／拖拉調寬
    async init() {
        // 開機同步（兩欄開合由後台讀活動隨 SSR 落 body——此處照讀同步掣嘅 title／aria；唔重置）
        for (const sidebar_key of Object.keys(this.sidebar_configs)) {
            this.apply_state(sidebar_key, this.is_collapsed(sidebar_key));
        }

        // 專案欄收合掣（rail 專案掣〔--projects〕——click 切換；根＝.sc_rail）
        jQuery(".sc_rail")
            .off("click.sc_sidebar_toggle_projects")
            .on("click.sc_sidebar_toggle_projects", this.sidebar_configs.projects.button_selector
                , this.toggled_projects_sidebar.bind(this));

        // 對話欄收合掣（rail 掣＋topbar 最左——click 切換；根＝.sc_rail／.sc_topbar）
        jQuery(".sc_rail, .sc_topbar")
            .off("click.sc_sidebar_toggle_chats")
            .on("click.sc_sidebar_toggle_chats", this.sidebar_configs.chats.button_selector
                , this.toggled_chats_sidebar.bind(this));

        // 兩欄一齊收合掣（rail 最頂——click 一撳全收／全開；根＝.sc_rail）
        jQuery(".sc_rail")
            .off("click.sc_sidebar_toggle_all")
            .on("click.sc_sidebar_toggle_all", this.all_sidebars_button_selector
                , this.toggled_all_sidebars.bind(this));

        // rail 掣綁定（evt 層——動態載入＋init；一檔一事）
        const { rail } = await import("./evt/rail.js");

        rail().init();

        // 拖拉調寬（把手 pointerdown——委派；開始拖曳）
        jQuery(document)
            .off("pointerdown.sc_sidebar_resize_start")
            .on("pointerdown.sc_sidebar_resize_start", ".sc_sidebar__resize_line"
                , this.started_resize.bind(this));
    }

    // 套狀態（唯一寫入點——body data 屬性〔純 CSS 閘〕＋掣嘅 title／aria-expanded）
    apply_state(sidebar_key, is_collapsed) {
        const sidebar_config = this.sidebar_configs[sidebar_key];

        let toggle_title  = "";
        let aria_expanded = "";
        let state_flag    = "";

        // 判定：收起態 → 「展開」提示＋旗標 1
        if (is_collapsed === true) {
            toggle_title  = sidebar_config.open_title;
            aria_expanded = "false";
            state_flag    = "1";
        // 否則＝展開態 → 「收合」提示＋旗標 0
        } else {
            toggle_title  = sidebar_config.close_title;
            aria_expanded = "true";
            state_flag    = "0";
        }

        jQuery("body").attr(sidebar_config.data_attr, state_flag);

        const j_toggle_button = jQuery(sidebar_config.button_selector);

        j_toggle_button.attr("title", toggle_title);
        j_toggle_button.attr("aria-expanded", aria_expanded);

        // 兩欄一齊掣提示（rail 最頂——跟兩欄旗標；寫入點同喺呢度＝單一寫入）
        this.apply_all_sidebars_control_state();
    }

    // 兩欄一齊掣提示同步（rail 最頂——兩欄全收＝「展開」提示；有欄開＝「收合」提示）
    apply_all_sidebars_control_state() {
        let toggle_title  = "收合全部欄";
        let aria_expanded = "true";

        // 判定：兩欄全收 → 下一步係開 → 「展開」提示
        if (this.is_all_sidebars_collapsed() === true) {
            toggle_title  = "展開全部欄";
            aria_expanded = "false";
        }

        const j_all_button = jQuery(this.all_sidebars_button_selector);

        j_all_button.attr("title", toggle_title);
        j_all_button.attr("aria-expanded", aria_expanded);
    }

    // 側欄收合切換（讀現行狀態〔body＝SSOT〕→ 反轉；兩欄共用；切換完寫活動）
    async toggled_sidebar(sidebar_key) {
        const next_is_collapsed = this.is_collapsed(sidebar_key) === false;

        this.apply_state(sidebar_key, next_is_collapsed);

        // 側欄狀態活動寫入（每次切換寫一次——開合要記住；act 層）
        const { sidebar_state_save } = await import("./act/sidebar_state_save.js");

        await sidebar_state_save().sidebar_state_save();
    }

    // 專案欄收合切換（rail 掣）
    toggled_projects_sidebar() {
        this.toggled_sidebar("projects");
    }

    // 對話欄收合切換（topbar 掣）
    toggled_chats_sidebar() {
        this.toggled_sidebar("chats");
    }

    // 兩欄一齊收合切換（rail 最頂掣——有欄打開中 → 全收；兩欄全收 → 全開）
    async toggled_all_sidebars() {
        // 判定：兩欄全收 → 下一步＝全開；否則（有欄打開中）→ 下一步＝全收
        const next_is_collapsed = this.is_all_sidebars_collapsed() === false;

        this.apply_state("projects", next_is_collapsed);
        this.apply_state("chats", next_is_collapsed);

        // 側欄狀態活動寫入（每次切換寫一次——開合要記住；act 層）
        const { sidebar_state_save } = await import("./act/sidebar_state_save.js");

        await sidebar_state_save().sidebar_state_save();
    }

    // 讀現行收合狀態（body 屬性＝SSOT）
    is_collapsed(sidebar_key) {
        return jQuery("body").attr(this.sidebar_configs[sidebar_key].data_attr) === "1";
    }

    // 讀兩欄是否全收（body 屬性＝SSOT——切換判斷＋提示同步共用同一支）
    is_all_sidebars_collapsed() {
        const projects_collapsed = this.is_collapsed("projects");
        const chats_collapsed    = this.is_collapsed("chats");

        return (projects_collapsed === true) && (chats_collapsed === true);
    }

    // 套欄寬（寫 --sc_sidebar_w〔CSS var——唔用 inline width，收合 width:0 先蓋得過〕；隨後台 sidebar_state 記住）
    apply_width(sidebar_key, sidebar_width) {
        const sidebar_element = jQuery(this.sidebar_configs[sidebar_key].sidebar_selector).get(0);

        sidebar_element.style.setProperty("--sc_sidebar_w", sidebar_width + "px");
    }

    // 讀現行欄寬（--sc_sidebar_w 內聯變數＝SSOT；未設過 → 回落該欄 CSS 預設）
    get_width(sidebar_key) {
        const sidebar_config  = this.sidebar_configs[sidebar_key];
        const sidebar_element = jQuery(sidebar_config.sidebar_selector).get(0);

        const width_value = sidebar_element.style.getPropertyValue("--sc_sidebar_w");

        // 判定：未設過（未拖過、SSR 又冇落）→ 用 CSS 預設
        if (width_value === "") {
            return sidebar_config.default_width;
        }

        return parseInt(width_value, 10);
    }

    // 開始拖拉調寬（pointerdown——記起點；收合中唔俾拖）
    started_resize(event) {
        const sidebar_key    = jQuery(event.currentTarget).attr("data-resize");
        const sidebar_config = this.sidebar_configs[sidebar_key];

        // 判定：收合中（闊度 0）→ 唔拖
        if (this.is_collapsed(sidebar_key) === true) {
            return;
        }

        // 起點闊度＝而家實際闊度（CSS 預設定拖拉後嘅值都照跟）
        const start_width = Math.round(jQuery(sidebar_config.sidebar_selector).get(0).getBoundingClientRect().width);

        this.resize_drag = {
            sidebar_key: sidebar_key,
            start_x:     event.clientX,
            start_width: start_width,
            current_width: start_width,
        };

        // 拖曳期間唔揀字＋熄過渡（即時跟手——唔使等 .22s 動畫）
        jQuery("body").addClass("is-resizing");
        jQuery("body").css("user-select", "none");

        // 拖曳跟蹤（document 層——移出把手都仲跟；放手／取消時連 namespace 一齊拆）
        jQuery(document)
            .off(".sc_sidebar_resize")
            .on("pointermove.sc_sidebar_resize", this.resized_sidebar.bind(this))
            .on("pointerup.sc_sidebar_resize", this.ended_resize.bind(this))
            .on("pointercancel.sc_sidebar_resize", this.ended_resize.bind(this));
    }

    // 拖拉中（pointermove——夾 200–300 即時套）
    resized_sidebar(event) {
        const sidebar_key    = this.resize_drag.sidebar_key;
        const sidebar_config = this.sidebar_configs[sidebar_key];

        const moved_width = this.resize_drag.start_width + (event.clientX - this.resize_drag.start_x);

        // 夾限（200–300）
        const clamped_width = Math.round(Math.max(sidebar_config.min_width, Math.min(sidebar_config.max_width, moved_width)));

        this.resize_drag.current_width = clamped_width;

        this.apply_width(sidebar_key, clamped_width);
    }

    // 放手（pointerup——解綁收尾＋寫側欄狀態活動〔欄寬隨後台記住〕）
    async ended_resize() {
        jQuery("body").removeClass("is-resizing");
        jQuery("body").css("user-select", "");

        jQuery(document).off(".sc_sidebar_resize");

        this.resize_drag = null;

        // 側欄狀態活動寫入（一次拖曳寫一次——記錄兩欄開合；act 層）
        const { sidebar_state_save } = await import("./act/sidebar_state_save.js");

        await sidebar_state_save().sidebar_state_save();
    }
}

export const sc_sidebar = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_sidebar());
