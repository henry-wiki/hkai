// jsmods/sc_modal/modal/act/fill_modal_content.js —— modal 內容注入（act——開單叫工作）
// 職責：fill_modal_content（content 四型：string／dom／jElement／function(j_element)——function 自建、其餘替換內文）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）
// == 2026-09-27 自舊 sc_modal.js 分遷入（one file one action） ==

class _fill_modal_content {
    constructor() {}

    // 內容注入（content : string, dom, jElement, function）
    fill_modal_content(j_modal_element, content) {
        if (content === undefined)
            return false;
        if (content === null)
            return false;

        // function 型 → 交回呼自建（帶 j_element）
        if (typeof content === "function") {

            content.apply(this, [j_modal_element])

            return true;
        }
        else {
            j_modal_element.empty();
            j_modal_element.append(content);

        }
    }
}

export const fill_modal_content = (function (instance) {
    return function () {

        return instance;
    };
})(new _fill_modal_content());
