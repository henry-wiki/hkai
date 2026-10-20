// jsmods/message/obs/message_intersection_observer.js —— 訊息區捲動綁定（factory 單例——一檔一族：scroll 直綁（user 側快照）＋入場閘（捲動狀態）＋底錨交集觀察（auto 側釘底））
// 所有東西在此檔：① 綁定註冊（init——scroll 直綁＋入場閘 scroll／scrollend 直綁＋底錨一個掛載方法：init_bottom_anchor_observer（底錨釘底）；載入收結後掛（同一地方——sc_ai_chats.js））② 動作（refresh_is_at_bottom／clear_is_at_bottom——寫快照；mark_viewport_scrolling／settle_viewport_scrolling——入場閘狀態（捲動中唔准入場；停穩重叫頭段檢查）；observed_scroll_anchor——快照 1 先釘返底；reached_top——向上補載入口（先過入場閘：喺底唔入／捲動緊唔入；再判三閘：有更舊／唔係載緊／上面已備夠〔政策工具喺 act〕）＝叫載一頁（由 rail 觀察器交入——原頂錨觸發已拆））
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一族——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _message_intersection_observer {
    constructor() {
        // 底錨觀察器（重掛時先 disconnect）
        this.bottom_anchor_observer = null;

        // 上一次內容高度（釘底只跟「內容變化」——純 user 捲動唔觸發；null = 尚未記錄）
        this.last_scroll_height = null;

        // 捲動狀態旗（scroll 事件＝捲動中；scrollend＝停穩——入場閘用：停穩先載，防載入喺捲動途中插入＝位置跳）
        this.is_viewport_scrolling = false;
    }

    // 綁定註冊（由載入收結呼叫——message_loaded_init；窗口重載後再叫；底錨一個方法掛載）
    async init() {
        // 橋接駁（同域——message instance 攞定；同步動作即時讀）
        this.message_instance = await registry().sc_ai_chats().message();

        // 捲動（user 側——同步「係唔係最底」快照；scroll 唔冒泡 → 直接綁 .sc_thread__viewport）
        jQuery(".sc_thread__viewport")
            .off("scroll.sc_ai_chats_messages")
            .on("scroll.sc_ai_chats_messages", this.refresh_is_at_bottom.bind(this));

        // 入場閘直綁（捲動狀態——scroll 記「捲動中」；scrollend 記「停穩」＋補叫頭段檢查）
        // 判定：瀏覽器唔支援 scrollend → 唔掛入場閘（維持原行為）
        if ("onscrollend" in window) {
            jQuery(".sc_thread__viewport")
                .off("scroll.sc_ai_chats_message_scrolling")
                .on("scroll.sc_ai_chats_message_scrolling", this.mark_viewport_scrolling.bind(this));

            jQuery(".sc_thread__viewport")
                .off("scrollend.sc_ai_chats_message_scroll_settle")
                .on("scrollend.sc_ai_chats_message_scroll_settle", this.settle_viewport_scrolling.bind(this));
        }

        // 觀察器①：底錨（#sc_scroll_anchor——內容長高＝釘返底）
        this.init_bottom_anchor_observer();
    }

    // 觀察器①：底錨觀察器（#sc_scroll_anchor 離開視野＝下方內容長高；訊息窗口重載後再掛）
    init_bottom_anchor_observer() {
        const thisObject = this;

        // 判定：舊底錨觀察器存在 → 先解除（避免殘留舊節點）
        if (thisObject.bottom_anchor_observer) {
            thisObject.bottom_anchor_observer.disconnect();
            thisObject.bottom_anchor_observer = null;
        }

        // 底錨即時取（不可快取——訊息窗口重載後會換新節點）
        const anchor_element = jQuery(".sc_thread__viewport").children("#sc_scroll_anchor")[0];

        // 判定：底錨不存在 → 唔掛（維持原行為）
        if (!anchor_element) {
            return;
        }

        // 判定：瀏覽器唔支援 IntersectionObserver → 唔掛（維持原行為）
        if (typeof IntersectionObserver !== "function") {
            return;
        }

        // 觀察器（用盡觀察者：threshold 兩線＋底部容忍交 rootMargin——幾何判斷零 scrollTop 計算）
        thisObject.bottom_anchor_observer = new IntersectionObserver(function () {
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

        thisObject.bottom_anchor_observer.observe(anchor_element);
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

    }

    // 捲動開始（scroll 事件——記「捲動中」；入場閘：捲動期間唔准入場補載）
    mark_viewport_scrolling() {
        this.is_viewport_scrolling = true;
    }

    // 捲動停穩（scrollend——複位＋重叫頭段檢查：仲喺頭段先叫返 reached_top 入場補載）
    async settle_viewport_scrolling() {
        this.is_viewport_scrolling = false;

        // 橋接駁（同域 obs——重跑活躍點刷新；頭段條件由佢自己重判）
        const message_rail_active_observer_instance = await this.message_instance.obs().message_rail_active_observer();

        message_rail_active_observer_instance.refresh_active_dots();
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

    // 向上補載入口（rail 觀察器交入——掂到窗口頭段）——先過入場閘（喺底唔入／捲動中唔入）＋判三閘（有更舊／唔係載緊／上面已備夠；是但一個唔對都唔叫）→ 交向上補載程序（載一頁）
    // 載完嘅下一輪由 rail 觀察器再交（點重建觸發刷新；三閘內建）——唔喺度自轉
    async reached_top() {
        const j_messages = jQuery(".sc_thread__viewport");

        // 判定：喺最底（跟底模式——向上緩衝係為咗向上讀，底位唔補）→ 唔叫
        if (j_messages.attr("data-is_at_bottom") === "1") {
            return;
        }

        // 判定：捲動緊（smooth 動畫／user）→ 唔入場（停穩先載——scrollend 後由 rail 重判再叫）
        if (this.is_viewport_scrolling) {
            return;
        }

        // 判定：冇更舊（has_more ≠ "1"——包含未載過）→ 唔叫
        if (j_messages.attr("data-has_more") !== "1") {
            return;
        }

        // 判定：載緊（load_status ≠ "ready"）→ 唔叫（防重入；載入相位〔窗口 pipeline／補載 session〕收場返 ready 先再受叫）
        if (j_messages.attr("data-load_status") !== "ready") {
            return;
        }

        // 政策工具（act：判①條數／判②高度 → 綜合）——唔夠先補
        const count_check_instance  = await this.message_instance.act().message_above_count_check();
        const height_check_instance = await this.message_instance.act().message_above_height_check();
        const decide_instance       = await this.message_instance.act().message_load_more_decide();

        const count_ok  = count_check_instance.message_above_count_check();
        const height_ok = height_check_instance.message_above_height_check();

        const need_more = decide_instance.message_load_more_decide(count_ok, height_ok);

        // 判定：上面已備夠（兩項達標）→ 唔叫
        if (!need_more) {
            return;
        }

        // 橋接駁（同域 act——新方式）
        const message_load_more_instance = await this.message_instance.act().message_load_more();

        await message_load_more_instance.message_load_more();
    }
}

export const message_intersection_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_intersection_observer());
