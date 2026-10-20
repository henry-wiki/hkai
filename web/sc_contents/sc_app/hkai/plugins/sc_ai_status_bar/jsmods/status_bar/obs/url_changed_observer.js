// jsmods/status_bar/obs/url_changed_observer.js —— URL 變化觀察綁定（factory 單例——經 sc_http 訂閱（劫持 pushState／replaceState ＋ popstate／hashchange）；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——訂閱 sc_http URL 變化；重掛先退訂）② 變化 → 派 hook 信號（obs:url_changed——做咩由 root 側 register_hook 決定；本檔唔判斷）
// 只睇 URL（DOM——零後端）：sc_http 中央派發已去重（同一條 URL 唔重複報）
// 備註：一個生命週期訂閱一次；只報告連結真變化（註冊唔會即場派——首載結算交 page_loaded）
// 結構：eager 單例 closure factory（具名 export——域接駁 status_bar.js obs 橋動態 import 解構後呼叫 init()）

class _url_changed_observer {
    constructor() {
        // URL 變化訂閱回呼（身份存住——退訂要傳返同一個）
        this.subscribed_callback = null;
    }

    // 建立＋訂閱（可重複呼叫——重掛先退訂；只訂閱——唔即刻派）
    init() {
        const thisObject = this;

        // 判定：舊訂閱存在 → 先退訂（重掛唔殘留）
        if (thisObject.subscribed_callback) {
            registry().shared().sc_http().off_url_change(thisObject.subscribed_callback);
            thisObject.subscribed_callback = null;
        }

        // 訂閱回呼（URL 一變即派信號——帶變化類型＋前後 URL；做咩＝root 側 register_hook 嘅事；本檔只篩 shallow）
        thisObject.subscribed_callback = function (navigation_type, changed_url, previous_url) {
            // DEBUG（臨時——查「邊個改 URL」用）：改前 → 改後＋發起位（call stack 最底幀＝真正寫入嗰行）
            //console.log("[status_bar url_changed_observer] 改前:", previous_url, "| 改後:", changed_url);
            //console.trace("[status_bar url_changed_observer] 發起位");

            // 判定：shallow（只寫 URL 唔導航——URL 只係追上畫面）→ 唔派信號（冇嘢載入）
            if (navigation_type === "shallow") {
                return;
            }

            registry().sc_ai_status_bar().execute_hook_after("obs:url_changed", changed_url, previous_url);
        };

        registry().shared().sc_http().on_url_change(thisObject.subscribed_callback);
    }
}

export const url_changed_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _url_changed_observer());
