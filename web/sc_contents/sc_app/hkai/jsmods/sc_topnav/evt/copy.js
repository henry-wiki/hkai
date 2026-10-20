// jsmods/sc_topnav/evt/copy.js —— 頂欄複製 click 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——頂欄骨架 .sc_topbar 內 selector 委派）② 動作（copy——讀按鈕自帶 data-copy-target → 寫剪貼簿）
// 目標契約：由按鈕聲明——data-copy-target="<selector>"（通用：任何頂欄按鈕自帶目標即通）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——sc_topnav init 動態 import 解構後呼叫 init()）

class _copy {
    constructor() {
    }

    // 綁定註冊（由 sc_topnav init 呼叫——一次；骨架內 selector 委派）
    init() {
        // 頂欄複製（範圍＝頂欄骨架，唔掛 document——目標由按鈕 data-copy-target 聲明）
        jQuery(".sc_topbar")
            .off("click.sc_topnav_copy")
            .on("click.sc_topnav_copy", "[data-copy-target]", this.copy.bind(this));
    }

    // 複製（copy——目標由按鈕聲明：讀 data-copy-target → 複製 → 成功 → 圖示轉勾回饋）
    async copy(event) {
        // 動態載入（registry——用先載）
        const shared = registry().shared();

        const j_copy_button = jQuery(event.currentTarget);
        const copy_selector = j_copy_button.attr("data-copy-target");
        const j_copy_target = jQuery(copy_selector);

        // 判定：搵唔到目標 → 不處理
        if (!j_copy_target.length) {
            return;
        }

        // 判定：輸入欄位取 value；否則取文字
        let copy_value = "";
        if (j_copy_target.is("input,textarea")) {
            copy_value = j_copy_target.val();
        } else {
            copy_value = j_copy_target.text();
        }

        // 判定：複製成功 → 才顯示勾（失敗不誤導使用者）
        const copy_ok = await shared.sc_web_utils().copy_text(copy_value);
        if (!copy_ok) {
            return;
        }

        shared.sc_web_utils().flash_copy_icon(j_copy_button);
    }
}

export const copy = (function (instance) {
    return function () {

        return instance;
    };
})(new _copy());
