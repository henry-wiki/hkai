// jsmods/message/evt/message_dots.js —— 訊息導航點綁定（factory 單例——純前台派生元件：rail＋重建＋亮燈＋跳轉；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——dot 委派＋首次建點）② 動作直接做（rebuild_dots／init_active_dot_observer／on_active_dot_observed／clicked_dot——唔經 callback）
// 只睇 message_list（.sc_thread__viewport DOM——零後端）
// 亮燈機制：IntersectionObserver 監視區（＝.sc_thread__viewport 可見區）——任何進入嘅訊息 → 佢嗰粒亮（可多粒同時亮；零 scroll 計算、零 reflow）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_dots {
    constructor() {
        // 監視區觀察器（重掛時先 disconnect——防重）
        this.active_dot_observer = null;
    }

    // 建立＋綁定（由 entry init 呼叫——一次）
    init() {
        // 點擊跳轉（導航點——唯一事件；亮燈交觀察器，唔揪 scroll）
        jQuery(document)
            .off("click.sc_ai_chats_message_dots")
            .on("click.sc_ai_chats_message_dots", ".sc_thread__scroll_rail .msg_dot", this.clicked_dot.bind(this));

        // 首次建點（連觀察註冊）
        //this.rebuild_dots();
    }

    // 重建導航點（一個 wrapper 一粒——順序跟 DOM；新訊息／重載／刪除自動跟——由 message_mutation_observer 通知）
    rebuild_dots() {
        const j_message_dots = jQuery(".sc_thread__scroll_rail");

        // 判定：容器唔存在 → 不做
        if (!j_message_dots.length) {
            return;
        }

        const j_wrappers = jQuery(".sc_thread__viewport").children(".message_wrapper");

        j_message_dots.empty();

        // 逐條訊息 → 一粒點（data-message_id 跟 wrapper；title＝該條問題預覽——原生 tooltip）
        for (let index = 0; index < j_wrappers.length; index++) {
            const j_wrapper = jQuery(j_wrappers[index]);

            const j_dot = jQuery("<button class='msg_dot'></button>");
            j_dot.attr("data-message_id", j_wrapper.attr("data-message_id"));

            // 問題預覽：原文字 → 壓縮空白 → 截短 80 字（hover 原生 title 顯示）
            const prompt_raw_text = j_wrapper.find(".user_prompt").text();
            const prompt_flat_text = prompt_raw_text.replace(/\s+/g, " ").trim();
            const prompt_preview_text = prompt_flat_text.slice(0, 30);

            // 判定：有預覽文字 → 掛 title
            if (prompt_preview_text) {
                j_dot.attr("title", prompt_preview_text);
            }

            j_message_dots.append(j_dot);
        }

        // 觀察註冊（新 DOM——重掛；舊觀察器由 init_active_dot_observer 內部解除）
        this.init_active_dot_observer();
    }

    // 觀察註冊（一個觀察器同時監視全部 .message_wrapper——重掛時舊觀察器先解除）
    init_active_dot_observer() {
        const thisObject = this;
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：容器唔存在 → 唔掛（維持原行為）
        if (!j_messages.length) {
            return;
        }

        // 判定：瀏覽器唔支援 IntersectionObserver → 唔掛（維持原行為）
        if (typeof IntersectionObserver !== "function") {
            return;
        }

        // 判定：舊觀察器存在 → 先解除（防重）
        if (thisObject.active_dot_observer) {
            thisObject.active_dot_observer.disconnect();
            thisObject.active_dot_observer = null;
        }

        // 觀察器（監視區＝.sc_thread__viewport 可見區：root 就係佢；threshold 0＝有重疊就算入區（部分可見即成））
        thisObject.active_dot_observer = new IntersectionObserver(function (entries) {
            thisObject.on_active_dot_observed(entries);
        }, {
            root: j_messages[0],
            threshold: 0,
        });

        // 逐條登記（.sc_thread__viewport 直系——現刻全部）
        const j_wrappers = j_messages.children(".message_wrapper");

        for (let index = 0; index < j_wrappers.length; index++) {
            thisObject.active_dot_observer.observe(j_wrappers[index]);
        }
    }

    // 觀察回呼（entries 只報變化——入區 → 亮；離區 → 熄；可多粒同時亮）
    on_active_dot_observed(entries) {
        // 逐筆落燈（一格一動作）
        for (let entry_index = 0; entry_index < entries.length; entry_index++) {
            const entry = entries[entry_index];

            const message_id = entry.target.getAttribute("data-message_id");

            const j_dot = jQuery(".sc_thread__scroll_rail .msg_dot[data-message_id='" + message_id + "']");

            // 判定：入區（含部分可見）→ 亮燈
            if (entry.isIntersecting) {
                j_dot.addClass("active");
            }

            // 判定：離區（唔可見）→ 熄燈
            if (!entry.isIntersecting) {
                j_dot.removeClass("active");
            }
        }
    }

    // 點擊導航點 → 捲去該訊息（平台原語 scrollIntoView；捲完觀察器會自己亮返）
    async clicked_dot(event) {
        const j_dot = jQuery(event.currentTarget);

        const message_id = j_dot.attr("data-message_id");

        const j_target_wrapper = jQuery(".sc_thread__viewport .message_wrapper[data-message_id='" + message_id + "']");

        // 判定：目標唔存在 → 唔處理
        if (!j_target_wrapper.length) {
            return;
        }

        // 橋接駁（同域 evt——跳轉前先撤銷「喺最底」快照；防釘底反應喺 smooth 捲動起步空窗搶咗個跳轉）
        const message_instance = await registry().sc_ai_chats().message();
        const message_intersection_observer_instance = await message_instance.evt().message_intersection_observer();

        message_intersection_observer_instance.clear_is_at_bottom();

        // 目標頂對齊容器頂（呼吸位＝CSS scroll-margin-top:8px；對齊／平滑交瀏覽器）
        j_target_wrapper[0].scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }
}

export const message_dots = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_dots());
