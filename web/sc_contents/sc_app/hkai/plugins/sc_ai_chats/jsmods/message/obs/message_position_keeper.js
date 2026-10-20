// jsmods/message/obs/message_position_keeper.js —— 訊息區閱讀位置看守（obs 層——一檔一事）
// 職責：message_position_keeper（內容變動（插頂／灌 slots／render markdown）時校正 scrollTop——將視窗頂嗰條嘅畫面位置釘死；mutation batch 尾、畫格前即量即校）
// 掛法：init（.sc_thread__viewport——childList／subtree／characterData；重掛先 disconnect 防重）
// 唔校正嘅情況（一律只重新種錨）：載入相位（load_status ≠ ready）／喺最底（data-is_at_bottom＝1——交底錨釘底）／搵唔到錨（空窗）
// 結構同 obs 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_position_keeper {
    constructor() {
        // 內容觀察器（重掛先斷——防舊 observer 疊住）
        this.observer = null;

        // 錨狀態（視窗頂第一條掂到嘅 wrapper——種子＝上次量度：畫面位置＋當時 scrollTop）
        this.anchor_element = null;
        this.anchor_screen_offset = 0;
        this.anchor_scroll_top = 0;
    }

    // 掛觀察器（載入收結後掛——sc_ai_chats.js；init 內置 disconnect 防重）
    init() {
        // 重掛先斷（防舊 observer 疊住）
        if (this.observer !== null) {
            this.observer.disconnect();
            this.observer = null;
        }

        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：容器唔喺度 → 唔掛
        if (j_messages.length === 0) {
            return;
        }

        // 清零種子（新一輪——下個 batch 重新種）
        this.anchor_element = null;

        const thisObject = this;

        // 內容變動觀察（childList／subtree／characterData——插頂、灌 slots、render markdown 全部包）
        this.observer = new MutationObserver(function () {

            thisObject.settle_position();
        });
        this.observer.observe(j_messages[0], { childList: true, subtree: true, characterData: true });
    }

    // 種錨（搵視窗頂第一條掂到嘅 wrapper——記低佢畫面位置＋當時 scrollTop）
    seed_anchor() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：容器唔喺度 → 清種子
        if (j_messages.length === 0) {
            this.anchor_element = null;
            return;
        }

        const viewport_top = j_messages[0].getBoundingClientRect().top;
        const message_wrapper_elements = j_messages.children(".message_wrapper");

        this.anchor_element = null;

        // 逐條搵第一條掂到視窗頂（條底過咗頂線——唔使換算 scrollTop）
        for (let index = 0; index < message_wrapper_elements.length; index++) {
            const wrapper_rect = message_wrapper_elements[index].getBoundingClientRect();

            // 判定：掂到視窗頂（未完全縮咗上去）→ 就係佢
            if (wrapper_rect.bottom > viewport_top) {
                this.anchor_element = message_wrapper_elements[index];
                this.anchor_screen_offset = wrapper_rect.top - viewport_top;
                this.anchor_scroll_top = j_messages[0].scrollTop;
                return;
            }
        }
    }

    // 校正（每個 mutation batch 尾、畫格前）：錨應該郁嘅量（跟 scrollTop 差）同實際郁量唔同 → scrollTop 即補返
    settle_position() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：容器唔喺度 → 清種子
        if (j_messages.length === 0) {
            this.anchor_element = null;
            return;
        }

        // 判定：載入相位（load_status ≠ ready）→ 唔校正（交載入側），只重新種錨
        if (j_messages.attr("data-load_status") !== "ready") {
            this.seed_anchor();
            return;
        }

        // 判定：喺最底（釘底 mode）→ 唔校正（交底錨釘底），只重新種錨
        if (j_messages.attr("data-is_at_bottom") === "1") {
            this.seed_anchor();
            return;
        }

        // 判定：未種過 → 今次只種
        if (this.anchor_element === null) {
            this.seed_anchor();
            return;
        }

        // 判定：錨唔喺 DOM（換咗／刪咗）→ 今次只重新種
        if (!j_messages[0].contains(this.anchor_element)) {
            this.seed_anchor();
            return;
        }

        // 錨應該喺邊（種子畫面位置 － 期間 scrollTop 嘅增減——user 自己捲走嘅部分唔算飄）
        const scroll_top_now = j_messages[0].scrollTop;
        const expected_offset = this.anchor_screen_offset - (scroll_top_now - this.anchor_scroll_top);

        // 錨實際喺邊（畫面位置）
        const viewport_top = j_messages[0].getBoundingClientRect().top;
        const actual_offset = this.anchor_element.getBoundingClientRect().top - viewport_top;

        // 判定：位置飄咗（內容變動郁咗佢）→ scrollTop 即補返
        if (actual_offset !== expected_offset) {
            const landing_scroll_top = scroll_top_now + (actual_offset - expected_offset);

            // 程式捲動聲明（寫低落點——畀「係唔係最底」快照識別自己嘅回聲；程式捲動唔入帳）
            j_messages.data("programmatic_scroll_landing_top", landing_scroll_top);

            j_messages.scrollTop(landing_scroll_top);
        }

        // 重新種錨（貼返視窗頂嗰條——跨 batch 保持新鮮）
        this.seed_anchor();
    }
}

export const message_position_keeper = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_position_keeper());
