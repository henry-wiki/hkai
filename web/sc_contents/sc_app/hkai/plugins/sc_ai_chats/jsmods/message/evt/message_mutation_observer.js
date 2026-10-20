// jsmods/message/evt/message_mutation_observer.js —— 訊息區變動觀察綁定（factory 單例——MutationObserver 盯 .sc_thread__viewport 直系子樹；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——觀察 .sc_thread__viewport（childList＋subtree＋characterData＋attributes〔只報 data-message_index／data-load_status〕）；重掛先 disconnect）② 動作（有變動 → 訊息級＝rebuild_dots＋rebuild_float_user_prompt（載入相位〔load_status ≠ ready〕唔重建——載入期死寂；ready 返嚟嗰下＝一槍收結）；跟底＝follow_bottom（喺最底＝同步撳返落底——任何批次都撳、批尾＝畫格前；唔喺底唔郁））
// 只睇 message_list（.sc_thread__viewport DOM——零後端）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

// ─── DOM 觀察器使用參考（穩定參考——寫任何觀察器前先睇；本段唔隨代碼改）───
// MutationObserver（觀察 DOM 樹數據）：
// ① 冇默認範圍：唔傳 options／傳 {}／淨傳 subtree → 一律抛 TypeError
//    （childList／attributes／characterData 至少要有一個 = true）
// ② 可設定項＝七個（冇第八）：
//    childList——節點加入／移除／搬位（限真 DOM 操作；CSS transform 嗰種視覺搬位唔報）
//    attributes——屬性改動；配 attributeFilter 只報白名單、唔配＝報全部
//    characterData——文字節點內容改動
//    subtree——上述三樣延伸落子孫（唔開＝只目標直屬一層）
//    attributeOldValue——record 附帶舊屬性值
//    characterDataOldValue——record 附帶舊文字值
//    attributeFilter——屬性白名單（字串陣列）
// ③ 自動聯動（唯一）：淨傳 attributeFilter／attributeOldValue → attributes 自動 = true；
//    淨傳 characterDataOldValue → characterData 自動 = true
// ④ 睇唔到：CSS／版面／scroll 位置／尺寸——渲染結果一律唔知
// ⑤ 交付時機：微任務批量——同一輪多個變動合成一個 records 陣列、一次回呼
// IntersectionObserver（觀察幾何）：
// ⑥ 只報「目標同 root 嘅交集狀態」——位置／可見度變（無論咩原因都係同一個報告）；
//    attribute／children／文字一律唔知，亦分唔清內容推走定用戶捲走
// ────────────────────────────────────────────────

class _message_mutation_observer {
    constructor() {
        // message_list 觀察器（重掛時先 disconnect）
        this.mutation_observer = null;
    }

    // 建立＋觀察（由 entry init 呼叫——一次）
    init() {
        const thisObject = this;

        // 判定：舊觀察器存在 → 先解除（重掛唔殘留）
        if (thisObject.mutation_observer) {
            thisObject.mutation_observer.disconnect();
            thisObject.mutation_observer = null;
        }

        // 判定：瀏覽器唔支援 MutationObserver → 唔掛（維持基本行為）
        if (typeof MutationObserver !== "function") {
            return;
        }

        const messages_element = jQuery(".sc_thread__viewport")[0];

        // 判定：容器唔存在 → 唔掛觀察器
        if (!messages_element) {
            return;
        }

        // 觀察 message_list（subtree＋characterData——串流逐段打字都見到；直系 childList＝訊息級；attributes 只認 data-message_index（批灌完）＋data-load_status（載入相位收結））
        thisObject.mutation_observer = new MutationObserver(function (mutation_records) {
            // 分流：直系 childList＝訊息級（導航點結構）；data-message_index 收結＝訊息級（內容灌完——導航點 title 嗰刻先讀得到問題文字）；data-load_status 返 ready＝訊息級（載入收結一槍——載入期跳過嘅一次補返）；跟底＝尾段獨立（任何批次都交——唔關分流）
            let has_message_level_change = false;

            for (let record_index = 0; record_index < mutation_records.length; record_index++) {
                const mutation_record = mutation_records[record_index];

                // 判定：直系 childList（target＝.sc_thread__viewport 本身）→ 訊息級
                if (
                    mutation_record.type === "childList"
                    && mutation_record.target === messages_element
                ) {
                    has_message_level_change = true;
                    continue;
                }

                // 判定：載入收結（viewport 嘅 data-message_index 一變＝一批灌完 slots——初始〔fetch 73〕／補載〔load_more 130〕都喺灌完之後寫）→ 訊息級
                if (
                    mutation_record.type === "attributes"
                    && mutation_record.attributeName === "data-message_index"
                    && mutation_record.target === messages_element
                ) {
                    has_message_level_change = true;
                    continue;
                }

                // 判定：載入相位訊號（viewport 嘅 data-load_status——起步＝無事；返 ready＝一槍收結〔載入期跳過嘅一次補返〕）
                if (
                    mutation_record.type === "attributes"
                    && mutation_record.attributeName === "data-load_status"
                    && mutation_record.target === messages_element
                ) {
                    // 判定：返 ready → 訊息級
                    if (messages_element.getAttribute("data-load_status") === "ready") {
                        has_message_level_change = true;
                    }
                    continue;
                }
            }

            // 判定：載入相位（load_status ≠ ready）→ 訊息級唔重建（載入期死寂——防大批灌入逐條跳；ready 收結嗰下先放行）
            if (messages_element.getAttribute("data-load_status") !== "ready") {
                has_message_level_change = false;
            }

            // 判定：有訊息級變動 → 重建導航點＋重掛浮問題條
            if (has_message_level_change) {
                thisObject.rebuild_dots();
                thisObject.rebuild_float_user_prompt();
            }

            // 跟底（唔分層級——任何批次都交；喺底＝同步撳返落底、唔喺底唔郁）
            thisObject.follow_bottom();
        });

        thisObject.mutation_observer.observe(messages_element, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ["data-message_index", "data-load_status"],
        });
    }

    // 有變動 → 直接交導航點重建（.sc_thread__viewport 直系 childList＝訊息級變動；唔經 callback）
    async rebuild_dots() {
        // 橋接駁（同域 evt——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_dots_instance = await message_instance.evt().message_dots();

        message_dots_instance.rebuild_dots();
    }

    // 有變動 → 直接交浮問題條重掛（.sc_thread__viewport 直系 childList＝訊息級變動；唔經 callback）
    async rebuild_float_user_prompt() {
        // 橋接駁（同域 obs——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_prompt_observer_instance = await message_instance.obs().message_prompt_observer();

        message_prompt_observer_instance.rebuild();
    }

    // 跟底（每個 mutation batch 尾、畫格前）：喺最底＝同步撳返落底（內容幾時變都喺同一格跟住、唔飄——唔等觀察器）；唔喺底＝唔郁（位置交 message_position_keeper）
    follow_bottom() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：唔喺最底（瀏覽中）→ 唔郁
        if (j_messages.attr("data-is_at_bottom") !== "1") {
            return;
        }

        const scroll_height = j_messages.prop("scrollHeight");
        const viewport_height = j_messages.prop("clientHeight");
        const landing_scroll_top = Math.max(0, scroll_height - viewport_height);

        // 程式捲動聲明（寫低落點——畀「係唔係最底」快照識別自己嘅回聲；程式捲動唔入帳）
        j_messages.data("programmatic_scroll_landing_top", landing_scroll_top);

        j_messages.scrollTop(landing_scroll_top);
    }
}

export const message_mutation_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_mutation_observer());
