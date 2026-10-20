// jsmods/sc_loading/sc_loading.js —— 載入遮罩模組（shared module——供各 plugin 使用）
// 職責：載入遮罩（大區域：遮罩 ＋「載入中」／小地方：單純轉圈／整頁：page_loading）
// 結構：init（樣式初始化）＋ 三種 start（依「放哪裡」分；各自組自己的 DOM——互不影響）＋ 一個 stop
//   init(settings)                            初始化樣式預設（{ "page_loading"／"panel_loading"／"basic_loading": { mask: {bgcolor, opacity} } }——呼叫端 settings 優先）
//   start_panel_loading(j_panel, settings)    大區域（card／window／panel）——遮罩 ＋ 旋轉圖示 ＋「載入中」
//   start_basic_loading(j_target, settings)   小地方（BTN／文字）——只有旋轉，無字
//   start_page_loading(settings)              整頁（<body> 的 .page_loading）——settings.mask 同前兩者
//   stop_loading(j_target)                    刪 loading（該目標的遮罩；目標是按鈕→恢復可用；另清整頁遮罩）
// settings.mask ＝ {bgcolor, opacity}：覆寫遮罩外觀（bgcolor→background-color、opacity 0-1；未指定＝依 init() 初始化樣式、再無＝CSS 預設）
// settings.text ＝ 大區域標籤文字（未指定／空字串＝只留圖案、不顯示文字）
// 觸發點：由呼叫端主動 start／stop（無自動輪詢）

class _sc_loading {

    constructor() {
        // 設定模型（init() 寫入——三種 start 未指定時沿用）
        this.defaults = {};
    }

    // 初始化：把我的設定放入模型（開機呼叫——bootstrap；外掛可再呼叫覆寫單一鍵——呼叫端 settings 優先）
    // 形狀：{ "page_loading": { mask: {bgcolor, opacity} }, "panel_loading": {…}, "basic_loading": {…} }
    // 深拷貝入 this.defaults（jQuery.extend——免呼叫端物件後改影響模型）
    init(settings) {
        this.defaults.page_loading  = jQuery.extend(true, {}, settings["page_loading"]);
        this.defaults.panel_loading = jQuery.extend(true, {}, settings["panel_loading"]);
        this.defaults.basic_loading = jQuery.extend(true, {}, settings["basic_loading"]);
    }

    // 大區域：顯示載入遮罩（card／window／panel——遮罩 ＋ 旋轉圖示 ＋ 標籤文字；不會用在按鈕上）
    // 本函數自己組自己的 DOM（獨立——與其他樣式互不影響）
    // settings 併模型預設（jQuery.extend 深合併——呼叫端優先；靶預置 mask：下面直取，唔使再檢查類型）
    // mask ＝ {bgcolor, opacity}：覆寫遮罩外觀（未指定＝模型預設、再無＝CSS 預設）
    // text ＝ 標籤文字（未指定／空字串＝只留圖案、不顯示文字）
    start_panel_loading(j_panel, settings) {
        settings = jQuery.extend(true, { mask: {} }, this.defaults.panel_loading, settings);

        const mask       = settings.mask;
        const label_text = (settings.text !== undefined) ? settings.text : "";

        this.stop_loading(j_panel);

        const j_loading_mask = jQuery("<span class='loading_mask'></span>");
        const j_bgmask       = jQuery("<div class='bgmask'></div>");

        // 判定：有遮罩底色 → 套用（覆寫 CSS 預設）
        if (mask.bgcolor) {
            j_bgmask.css("background-color", mask.bgcolor);
        }

        // 判定：有遮罩不透明度 → 套用（0-1）
        if (mask.opacity !== undefined) {
            j_bgmask.css("opacity", mask.opacity);
        }

        j_loading_mask.append(j_bgmask);

        // 標籤（圖示在文字左邊、用 FA 內建 spin）
        const j_label = jQuery("<div class='loading_label'></div>");
        j_label.append(jQuery("<i class='fa-solid fa-arrows-rotate'></i>"));

        // 判定：有標籤文字 → 一併顯示（空字串＝只留圖案）
        if (label_text !== "") {
            j_label.append(document.createTextNode(label_text));
        }

        j_loading_mask.append(j_label);

        j_panel.append(j_loading_mask);
    }

    // 小地方：顯示載入遮罩（BTN／文字——只有圓環轉圈）
    // 本函數自己組自己的 DOM（獨立——與其他樣式互不影響）
    // settings 併模型預設（jQuery.extend 深合併——呼叫端優先；靶預置 mask：下面直取，唔使再檢查類型）
    // mask ＝ {bgcolor, opacity}：覆寫遮罩外觀（未指定＝模型預設、再無＝CSS 預設）
    start_basic_loading(j_target, settings) {
        settings = jQuery.extend(true, { mask: {} }, this.defaults.basic_loading, settings);

        const mask      = settings.mask;
        const j_wrapper = j_target.closest(".sc_btn_wrapper");
        const j_panel   = j_wrapper.length ? j_wrapper : j_target;

        this.stop_loading(j_target);

        const j_loading_mask = jQuery("<span class='loading_mask'></span>");
        const j_bgmask       = jQuery("<div class='bgmask'></div>");

        // 判定：有遮罩底色 → 套用（覆寫 CSS 預設）
        if (mask.bgcolor) {
            j_bgmask.css("background-color", mask.bgcolor);
        }

        // 判定：有遮罩不透明度 → 套用（0-1）
        if (mask.opacity !== undefined) {
            j_bgmask.css("opacity", mask.opacity);
        }

        j_loading_mask.append(j_bgmask);

        // 圓環轉圈
        j_loading_mask.append(jQuery("<div class='spinner'></div>"));

        j_panel.append(j_loading_mask);

        // 判定：目標是按鈕 → 一併禁用（遮罩擋滑鼠，禁用擋鍵盤）
        if (j_target.is("button")) {
            j_target.prop("disabled", true);
        }
    }

    // 整頁：顯示載入遮罩（settings.mask 同 start_panel_loading——底色／不透明度只套在底色層，spinner 唔受影響）
    // settings 併模型預設（jQuery.extend 深合併——呼叫端優先；靶預置 mask：下面直取，唔使再檢查類型）
    // mask ＝ {bgcolor, opacity}：覆寫遮罩外觀（未指定＝模型預設、再無＝CSS 預設）
    start_page_loading(settings) {
        settings = jQuery.extend(true, { mask: {} }, this.defaults.page_loading, settings);

        const mask = settings.mask;

        // 判定：已有整頁遮罩 → 先移除（避免重複堆疊）
        if (jQuery("[data-is='page_loading']").length) {
            jQuery("[data-is='page_loading']").remove();
        }

        const j_page_loading = jQuery("<div data-is='page_loading' class='page_loading'></div>");
        const j_page_bgmask  = jQuery("<div class='page_bgmask'></div>");

        // 判定：有遮罩底色 → 套用（覆寫 CSS 預設）
        if (mask.bgcolor) {
            j_page_bgmask.css("background-color", mask.bgcolor);
        }

        // 判定：有遮罩不透明度 → 套用（0-1）
        if (mask.opacity !== undefined) {
            j_page_bgmask.css("opacity", mask.opacity);
        }

        j_page_loading.append(j_page_bgmask);

        jQuery("body").append(j_page_loading);
    }

    // 刪 loading（該目標的遮罩；目標是按鈕 → 恢復可用；另清整頁遮罩）
    stop_loading(j_target) {
        // 判定：有指定目標 → 移除其上的遮罩
        if (j_target !== undefined && j_target !== null) {
            const j_wrapper = j_target.closest(".sc_btn_wrapper");
            const j_panel   = j_wrapper.length ? j_wrapper : j_target;

            // 判定：該容器上有載入遮罩 → 移除
            if (j_panel.children(".loading_mask").length) {
                j_panel.children(".loading_mask").remove();
            }

            // 判定：目標是按鈕 → 恢復可用
            if (j_target.is("button")) {
                j_target.prop("disabled", false);
            }
        }

        // 判定：有整頁遮罩 → 淡出後移除
        if (jQuery("[data-is='page_loading']").length) {
            jQuery("[data-is='page_loading']").stop().fadeOut(function () {
                jQuery(this).remove();
            });
        }
    }
}

export const sc_loading = (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_loading());
