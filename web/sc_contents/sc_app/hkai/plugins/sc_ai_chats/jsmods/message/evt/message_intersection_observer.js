// jsmods/message/evt/message_intersection_observer.js —— 訊息區捲動綁定（factory 單例——一檔一族：scroll 直綁（user 側快照）＋錨點交集觀察（auto 側釘底））
// 所有東西在此檔：① 綁定註冊（init——scroll 直綁＋錨點觀察掛載；窗口重載後再掛）② 動作（refresh_is_at_bottom——寫快照＋切換按鈕；refresh_scroll_to_bottom_btn——按鈕顯示；observed_scroll_anchor——快照 1 先釘返底）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一族——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_intersection_observer {
    constructor() {
        // 錨點觀察器（重掛時先 disconnect）
        this.anchor_observer = null;

        // 上一次內容高度（釘底只跟「內容變化」——純 user 捲動唔觸發；null = 尚未記錄）
        this.last_scroll_height = null;
    }

    // 綁定註冊（由 entry init 呼叫——一次；錨點重掛＝再叫一次）
    async init() {
        // 橋接駁（同域——message instance 攞定；同步動作即時讀）
        this.message_instance = await registry().sc_ai_chats().message();

        const thisObject = this;

        // 捲動（user 側——同步「係唔係最底」快照；scroll 唔冒泡 → 直接綁 .sc_thread__viewport）
        jQuery(".sc_thread__viewport")
            .off("scroll.sc_ai_chats_messages")
            .on("scroll.sc_ai_chats_messages", this.refresh_is_at_bottom.bind(this));

        // 錨點觀察（#sc_scroll_anchor 離開視野＝下方內容長高；訊息窗口重載後由 message_load 直接再叫）
        // 判定：舊觀察器存在 → 先解除（避免殘留舊節點）
        if (thisObject.anchor_observer) {
            thisObject.anchor_observer.disconnect();
            thisObject.anchor_observer = null;
        }

        // 錨點即時取（不可快取——訊息窗口重載後會換新節點）
        const anchor_element = jQuery(".sc_thread__viewport").children("#sc_scroll_anchor")[0];

        // 判定：錨點不存在 → 唔掛（維持原行為）
        if (!anchor_element) {
            return;
        }

        // 判定：瀏覽器唔支援 IntersectionObserver → 唔掛（維持原行為）
        if (typeof IntersectionObserver !== "function") {
            return;
        }

        // 觀察器（用盡觀察者：threshold 兩線＋底部容忍交 rootMargin——幾何判斷零 scrollTop 計算）
        thisObject.anchor_observer = new IntersectionObserver(function () {
            // 判定：內容高度冇變（＝純 user 捲動）→ 唔叫（釘底只跟內容變化）
            if (!thisObject.check_content_height_changed()) {
                return;
            }

            thisObject.observed_scroll_anchor();
        }, {
            root: jQuery(".sc_thread__viewport")[0],
            threshold: [0, 1],                 // 0＝觸線即報、1＝全見底（兩線用盡）
            rootMargin: "0px 0px 10px 0px",    // 底邊擴 10px＝貼底容忍（等價舊 bottom_threshold 10）
        });

        thisObject.anchor_observer.observe(anchor_element);
    }

    // 內容高度有冇變（同上次比對——變咗＝內容推走（auto）；冇變＝純 user 捲動）
    check_content_height_changed() {
        const current_scroll_height = jQuery(".sc_thread__viewport").prop("scrollHeight");

        // 判定：同上次一樣 → 冇變（唔觸發釘底）
        if (current_scroll_height === this.last_scroll_height) {
            return false;
        }

        this.last_scroll_height = current_scroll_height;

        return true;
    }

    // 更新「係唔係最底」快照（user 捲動側寫入——1/0；判斷源＝messages 域；CSS 閘門用）
    // 同步寫（唔可以遲——觀察者同一幀會讀快照；async 會輸給觀察者 → 誤彈返底）
    // 契約：只有 user 動作（捲動／跳轉 click）先可以改快照——程式捲動（跳底／釘底／校正）一律唔入帳
    refresh_is_at_bottom() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 程式捲動回聲豁免（有聲明＝消費佢；落點對上＝自己嘅回聲唔入帳；唔對＝當外部郁動照寫）
        const programmatic_landing_top = j_messages.data("programmatic_scroll_landing_top");

        // 判定：有未消費嘅程式捲動聲明
        if (programmatic_landing_top !== undefined) {
            j_messages.removeData("programmatic_scroll_landing_top");

            const landing_gap = Math.abs(j_messages.scrollTop() - programmatic_landing_top);

            // 判定：位置＝聲明落點（自己嘅回聲）→ 唔入帳（程式捲動唔算 user）
            if (landing_gap <= 1) {
                return;
            }
        }

        const at_bottom_flag = this.message_instance.is_at_bottom() ? "1" : "0";

        j_messages.attr("data-is_at_bottom", at_bottom_flag);

        // 順手記現高（純捲動期間高度基準保持最新——唔會被誤當「內容變化」而叫釘底）
        this.last_scroll_height = j_messages.prop("scrollHeight");

        // 同一撳捲動——一併切換按鈕顯示（兩個輸出、一個判斷源）
        this.refresh_scroll_to_bottom_btn();
    }

    // 依「係唔係最底」切換按鈕（最底 → 移除 .visible；否則 → 加 .visible）——即時查 scroll position
    refresh_scroll_to_bottom_btn() {
        const j_scroll_to_bottom_btn = jQuery(".sc_thread > [data-action='scroll_to_bottom']");

        // 判定：按鈕唔存在 → 不做
        if (!j_scroll_to_bottom_btn.length) {
            return;
        }

        // 判定：喺最底 → 移除 .visible（唔顯示）
        if (this.message_instance.is_at_bottom()) {
            j_scroll_to_bottom_btn.removeClass("visible");
            return;
        }

        // 唔喺最底 → 加 .visible（顯示）
        j_scroll_to_bottom_btn.addClass("visible");
    }

    // 撤銷「喺最底」快照（使用者主動跳轉用——唔等捲動事件；防釘底反應喺 smooth 捲動起步空窗搶咗個跳轉）
    clear_is_at_bottom() {
        jQuery(".sc_thread__viewport").attr("data-is_at_bottom", "0");
    }

    // 錨點觀察回呼（auto 側——快照係 1（喺底）→ 釘返底；0（user 捲走）→ 唔郁）
    async observed_scroll_anchor() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：唔喺最底 → 唔釘（user 閱讀中）
        if (j_messages.attr("data-is_at_bottom") !== "1") {
            return;
        }

        // 橋接駁（同域 act——新方式）
        const message_scroll_to_bottom_instance = await this.message_instance.act().message_scroll_to_bottom();

        message_scroll_to_bottom_instance.message_scroll_to_bottom(false);
    }
}

export const message_intersection_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_intersection_observer());
