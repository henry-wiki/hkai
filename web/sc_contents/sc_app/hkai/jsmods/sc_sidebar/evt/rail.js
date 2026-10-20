// jsmods/sc_sidebar/evt/rail.js —— rail 掣 click 綁定（evt 層——一檔一事）
// 職責：① 綁定註冊（init——selector 委派）② 派發（dbviewer_clicked——交 act/opened_dbviewer；本檔零業務）
// 檔案＝DevTools Event Listeners 面板顯示嘅字（一眼知位置）
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _rail {
    constructor() {}

    // 綁定註冊（由 sc_sidebar init 呼叫——一次；selector 委派；off 先清舊綁定）
    init() {
        // 資料庫檢視掣（rail——click 開 lightbox；iframe 目標由後台出（data-src））
        jQuery(".sc_rail")
            .off("click.sc_sidebar_rail_open_dbviewer")
            .on("click.sc_sidebar_rail_open_dbviewer", "[data-btn='modal_open_dbviewer']"
                , this.dbviewer_clicked.bind(this));
    }

    // 資料庫檢視掣點擊（掣 → act；動作邏輯唔喺本檔）
    async dbviewer_clicked(event) {
        // 動態載入（act 層——用先載）
        const { opened_dbviewer } = await import("../act/opened_dbviewer.js");

        await opened_dbviewer().opened_dbviewer(jQuery(event.currentTarget));
    }
}

export const rail = (function (instance) {
    return function () {

        return instance;
    };
})(new _rail());
