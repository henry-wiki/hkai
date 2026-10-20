// jsmods/setup/act/close_setup.js —— 關閉精靈（act 層——一 action 一檔）
// 職責：close_setup（感謝頁「關閉」——重新載入當前頁〔完成後入口唔再出精靈、直去聊天列表〕）
// 結構同 model_selector 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _close_setup {
    constructor() {}

    // 關閉（重新載入當前頁——完成後入口閘直去聊天列表）
    close_setup() {
        window.location.reload();
    }
}

export const close_setup = (function (instance) {
    return function () {

        return instance;
    };
})(new _close_setup());
