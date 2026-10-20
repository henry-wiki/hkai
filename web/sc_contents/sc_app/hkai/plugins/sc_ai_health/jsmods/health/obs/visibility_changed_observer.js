// jsmods/health/obs/visibility_changed_observer.js —— 分頁可見性觀察綁定（factory 單例——監聽 document visibilitychange；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——綁 document visibilitychange；重掛先卸載）② 變化 → 派 hook 信號（obs:visibility_changed——做咩由 root 側 register_hook 決定；本檔唔判斷）
// 只睇分頁可見性（Page Visibility API——零後端）：瀏覽器只喺真變化先派事件（冇重複報）
// 備註：一個生命週期綁定一次；綁定唔會即場派——首載結算交 root 開機競選
// 結構：eager 單例 closure factory（具名 export——域接駁 health.js obs 橋動態 import 解構後呼叫 init()）

class _visibility_changed_observer {
    constructor() {
        // 事件綁定回呼（身份存住——卸載要傳返同一個）
        this.subscribed_callback = null;
    }

    // 建立＋綁定（可重複呼叫——重掛先卸載；只綁——唔即刻派）
    init() {
        const thisObject = this;

        // 判定：舊綁定存在 → 先卸載（重掛唔殘留）
        if (thisObject.subscribed_callback) {
            document.removeEventListener("visibilitychange", thisObject.subscribed_callback);
            thisObject.subscribed_callback = null;
        }

        // 綁定回呼（可見性一變即派信號——帶當前 visibilityState；做咩＝root 側 register_hook 嘅事）
        thisObject.subscribed_callback = function () {
            registry().sc_ai_health().execute_hook_after("obs:visibility_changed", document.visibilityState);
        };

        document.addEventListener("visibilitychange", thisObject.subscribed_callback);
    }
}

export const visibility_changed_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _visibility_changed_observer());
