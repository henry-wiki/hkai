// jsmods/chat/obs/page_loaded_observer.js —— 頁面載入旗標觀察綁定（factory 單例——MutationObserver 盯 body 嘅 data-loaded；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——觀察 document.body（attributes＋attributeFilter data-loaded）；重掛先 disconnect）② 變化 → 派 hook 信號（obs:page_loaded——做咩由 root 側 register_hook 決定；本檔唔判斷）
// 只睇頁面旗標（body[data-loaded] DOM——零後端）：bootstrap 編排完成後由 0 改 1
// 備註：一個生命週期只派一次（派完即解除觀察）
// 結構：eager 單例 closure factory（具名 export——域接駁 chat.js obs 橋動態 import 解構後呼叫 init()）

class _page_loaded_observer {
    constructor() {
        // 頁面旗標觀察器（觀察目標＝document.body）
        this.mutation_observer = null;
    }

    // 建立＋觀察（可重複呼叫——重掛先解除；只認 data-loaded）
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

        // 建立觀察器（body 旗標一改就入嚟判定）
        thisObject.mutation_observer = new MutationObserver(function () {
            // 判定：旗標未到「已載入」→ 唔派（0 → 1 中間嘅寫入唔算）
            if (document.body.getAttribute("data-loaded") !== "1") {
                return;
            }

            // 旗標到 1 → 解除觀察（一個生命週期只派一次）
            thisObject.mutation_observer.disconnect();
            thisObject.mutation_observer = null;

            // 派 obs:page_loaded 信號（做咩＝root 側 register_hook 嘅事；本檔唔判斷）
            registry().sc_ai_chats().execute_hook_after("obs:page_loaded", []);
        });

        // 觀察 body 嘅 data-loaded（唯一觀察目標——其他屬性／childList 唔報）
        thisObject.mutation_observer.observe(document.body, {
            attributes: true,
            attributeFilter: ["data-loaded"],
        });
    }
}

export const page_loaded_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _page_loaded_observer());
