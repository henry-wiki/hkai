// jsmods/project/obs/project_item_observer.js —— 專案單項變動觀察綁定（factory 單例——MutationObserver 逐項盯 .sc_project_card；檔名＝DevTools 面板顯示嘅字）
// 所有東西在此檔：① 綁定註冊（init——逐個 .sc_project_card 掛觀察（attributes＋attributeFilter data-update_time）；重掛先 disconnect）② 變化 → 派 hook 信號（obs:project_item_updated——做咩由 root 側 register_hook 決定；本檔唔判斷）③ 外部註冊（observe——新專案項加入即補掛）
// 只睇專案項（.sc_project_card DOM——零後端）；只認 data-update_time（唯一觀察目標——其他屬性唔報）
// 備註：項目集合變動（新項加入／清單重繪）後要再 init 一次——先覆蓋新項（init 可重複呼叫，內置 disconnect 防重）
// 結構：eager 單例 closure factory（具名 export——域接駁 project.js obs 橋動態 import 解構後呼叫 init()）

class _project_item_observer {
    constructor() {
        // 專案項觀察器（重掛時先 disconnect）
        this.mutation_observer = null;
    }

    // 建立＋逐項觀察（可重複呼叫——重掛先解除；只認 data-update_time）
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

        // 現有專案項（逐項掛——集合變動後再 init 覆蓋新項）
        const j_project_items = jQuery(".sc_project_list .sc_project_card");

        // 判定：冇專案項 → 唔掛觀察器
        if (j_project_items.length < 1) {
            return;
        }

        // 逐項觀察（單一觀察器、多目標 observe；只認 data-update_time）
        thisObject.mutation_observer = new MutationObserver(function (mutation_records) {
            // 受變更嘅專案項（逐筆記錄抽 target——同一項多筆照過；做咩交 downstream）
            const affected_project_items = [];
            for (let record_index = 0; record_index < mutation_records.length; record_index++) {
                affected_project_items.push(mutation_records[record_index].target);
            }

            // 派 obs:project_item_updated 信號（做咩＝root 側 register_hook 嘅事；本檔唔判斷）
            registry().sc_ai_projects().execute_hook_after("obs:project_item_updated", [affected_project_items]);
        });

        // 逐項掛（同一觀察器 observe 多個目標——共用 observe 方法）
        for (let item_index = 0; item_index < j_project_items.length; item_index++) {
            thisObject.observe(j_project_items[item_index]);
        }
    }

    // 外部註冊（新專案項加入即補掛——同樣只認 data-update_time；直收 DOM element）
    observe(project_item_element) {
        // 判定：觀察器未建立（未 init）→ 唔掛（init 時會一次過覆蓋現有項）
        if (this.mutation_observer === null) {
            return;
        }

        this.mutation_observer.observe(project_item_element, {
            attributes: true,
            attributeFilter: ["data-update_time"],
        });
    }
}

export const project_item_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_item_observer());
