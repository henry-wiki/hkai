// jsmods/message/obs/message_prompt_observer.js —— 浮問題條觀察綁定（factory 單例——兩條件：淨一粒燈 ∧ 嗰條問題唔喺視野 → 顯；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——click 委派＋rail 燈 class 觀察）② 重掛（rebuild——問題元素重掛交集觀察＋先收；由 message_mutation_observer 交入）③ 動作（refresh_visibility——判決＋填內容；observe_missing_prompts——補掛未註冊問題元素；clicked_float——跳去原問題）
// 只睇 message_list（.sc_thread__viewport DOM——零後端）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_prompt_observer {
    constructor() {
        // 問題元素觀察器（重掛時先 disconnect）
        this.prompt_observer = null;

        // rail 燈觀察器（重掛時先 disconnect）
        this.rail_observer = null;

        // 問題元素 → 有冇喺視野（快照——觀察回呼寫入；未知＝唔當可見）
        this.prompt_visible_map = new Map();
    }

    // 綁定註冊（由載入收結呼叫——一次；重掛安全）
    init() {
        // click 委派（撳浮條＝跳去原問題）
        jQuery(document)
            .off("click.sc_ai_chats_float_user_prompt")
            .on("click.sc_ai_chats_float_user_prompt", ".sc_float_user_prompt", this.clicked_float.bind(this));

        // rail 燈觀察（亮熄即重判）
        this.init_rail_observer();
    }

    // rail 燈觀察器（觀察 .sc_thread__scroll_rail 子樹 class 白名單——燈亮熄即報；rail 骨架長駐、唔跟窗口重建）
    init_rail_observer() {
        const thisObject = this;

        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (thisObject.rail_observer) {
            thisObject.rail_observer.disconnect();
            thisObject.rail_observer = null;
        }

        // 判定：瀏覽器唔支援 MutationObserver → 唔掛（維持原行為）
        if (typeof MutationObserver !== "function") {
            return;
        }

        const rail_element = jQuery(".sc_thread__scroll_rail")[0];

        // 判定：rail 唔存在 → 唔掛觀察器
        if (!rail_element) {
            return;
        }

        // 觀察 rail（class 白名單——燈亮熄即刷新；自身內容唔郁、唔會自觸）
        thisObject.rail_observer = new MutationObserver(function () {
            thisObject.refresh_visibility();
        });

        thisObject.rail_observer.observe(rail_element, {
            subtree: true,
            attributes: true,
            attributeFilter: ["class"],
        });
    }

    // 重掛（窗口重建——問題元素換晒；先收＋解除舊觀察器再重新掛；由 message_mutation_observer 交入）
    rebuild() {
        const thisObject = this;

        // 先收（重建期間唔留舊內容）
        jQuery(".sc_float_user_prompt").attr("data-visible", "0");

        // 快照清零（舊問題元素唔再有效）
        thisObject.prompt_visible_map.clear();

        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (thisObject.prompt_observer) {
            thisObject.prompt_observer.disconnect();
            thisObject.prompt_observer = null;
        }

        // 判定：瀏覽器唔支援 IntersectionObserver → 唔掛（維持原行為）
        if (typeof IntersectionObserver !== "function") {
            return;
        }

        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：容器唔存在 → 唔掛觀察器
        if (!j_messages.length) {
            return;
        }

        // 問題元素觀察器（root＝視窗可見區、threshold 0＝部分可見即成——同導航點同一寫法）
        thisObject.prompt_observer = new IntersectionObserver(function (entries) {
            // 逐筆落快照（入區＝true／離區＝false）
            for (let entry_index = 0; entry_index < entries.length; entry_index++) {
                const entry = entries[entry_index];

                thisObject.prompt_visible_map.set(entry.target, entry.isIntersecting);
            }

            thisObject.refresh_visibility();
        }, {
            root: j_messages[0],
            threshold: 0,
        });

        // 逐條問題登記（.message_wrapper 內 .user_prompt——文字元素）
        const j_prompts = j_messages.find(".message_wrapper .user_prompt");

        for (let index = 0; index < j_prompts.length; index++) {
            thisObject.prompt_observer.observe(j_prompts[index]);
        }
    }

    // 補掛未註冊嘅問題元素（slot 後填／元素被換——一律兜到；已註冊重覆呼叫＝無害 no-op）
    observe_missing_prompts() {
        // 判定：觀察器未掛（未 rebuild／瀏覽器唔支援）→ 唔做
        if (!this.prompt_observer) {
            return;
        }

        const j_messages = jQuery(".sc_thread__viewport");
        const j_prompts = j_messages.find(".message_wrapper .user_prompt");

        for (let index = 0; index < j_prompts.length; index++) {
            const prompt_element = j_prompts[index];

            // 判定：已喺快照（已註冊）→ 跳過
            if (this.prompt_visible_map.has(prompt_element)) {
                continue;
            }

            this.prompt_observer.observe(prompt_element);
        }
    }

    // 判決（兩條件：淨一粒燈 ∧ 嗰條問題唔喺視野 → 顯＋填內容；否則收）
    refresh_visibility() {
        // 先補掛未註冊問題元素（未註冊＝值未知會被當「見到」——補掛後交付回呼即刻修正）
        this.observe_missing_prompts();

        const j_float = jQuery(".sc_float_user_prompt");

        // 判定：浮條唔存在 → 唔做
        if (!j_float.length) {
            return;
        }

        // 條件①：淨一粒燈（多過一粒＝見到交界／下一條問題；零粒＝冇訊息）
        const j_active_dots = jQuery(".sc_thread__scroll_rail .msg_dot.active");

        // 判定：唔係淨一粒 → 收
        if (j_active_dots.length !== 1) {
            j_float.attr("data-visible", "0");
            return;
        }

        const message_id = j_active_dots.first().attr("data-message_id");

        const j_wrapper = jQuery(".sc_thread__viewport .message_wrapper[data-message_id='" + message_id + "']");

        // 判定：對唔到訊息框 → 收
        if (!j_wrapper.length) {
            j_float.attr("data-visible", "0");
            return;
        }

        const j_prompt = j_wrapper.find(".user_prompt").first();

        // 判定：搵唔到問題元素 → 收
        if (!j_prompt.length) {
            j_float.attr("data-visible", "0");
            return;
        }

        // 條件②：嗰條問題唔喺視野（快照 false 先算確認唔喺；未知／true＝當見到 → 收）
        const prompt_in_view = this.prompt_visible_map.get(j_prompt[0]) !== false;

        // 判定：問題喺視野（或未知）→ 收
        if (prompt_in_view) {
            j_float.attr("data-visible", "0");
            return;
        }

        // 兩條件齊 → 填內容＋顯示
        j_float.html(j_prompt.html());
        j_float.attr("data-message_id", message_id);
        j_float.attr("data-visible", "1");
    }

    // 撳浮條 → 跳去原問題（照導航點跳轉——先撤「喺最底」快照防釘底搶跳；目標頂對齊頂）
    async clicked_float() {
        const j_float = jQuery(".sc_float_user_prompt");
        const message_id = j_float.attr("data-message_id");

        // 判定：冇目標訊息 → 唔處理
        if (!message_id) {
            return;
        }

        const j_target_wrapper = jQuery(".sc_thread__viewport .message_wrapper[data-message_id='" + message_id + "']");

        // 判定：目標唔存在 → 唔處理
        if (!j_target_wrapper.length) {
            return;
        }

        // 橋接駁（同域 evt——跳轉前先撤銷「喺最底」快照；防釘底反應喺 smooth 捲動起步空窗搶咗個跳轉）
        const message_instance = await registry().sc_ai_chats().message();
        const message_intersection_observer_instance = await message_instance.evt().message_intersection_observer();

        message_intersection_observer_instance.clear_is_at_bottom();

        // 目標頂對齊容器頂（呼吸位＝CSS scroll-margin-top；對齊／平滑交瀏覽器）
        j_target_wrapper[0].scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }
}

export const message_prompt_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_prompt_observer());
