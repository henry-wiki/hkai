// jsmods/sc_topnav/sc_topnav.js —— 頂部導覽列統一管理
// 職責：topBar 工具按鈕 click 綁定（委託各 plugin handler）
// 結構同 sc_health：class + IIFE 單例（registry().sc_topnav() 取用）；init 由 bootstrap 編排呼叫

class _sc_topnav {
    constructor() {
    }

    // 啟動（bootstrap 編排——一次）：頂欄掣綁定（具名＋獨立 namespace——DevTools 可追）
    async init() {
        // nav_refresh → 重新整理
        jQuery("[data-action='nav_refresh']")
            .off("click.sc_topnav_nav_refresh")
            .on("click.sc_topnav_nav_refresh", this.nav_refresh.bind(this));

        // copy → 複製路徑列顯示嘅專案路徑（evt/copy.js——一檔一事）
        (await this.copy()).init();
    }

    // 重新整理（start_page_loading → 頁面重新載入）
    async nav_refresh() {
        // 動態載入（registry——用先載）
        const loading = registry().sc_loading();

        loading.start_page_loading();
        document.location.reload();
    }

    // 複製（evt/copy.js——動態 import 解構後呼叫）
    async copy() {
        return (await import("./evt/copy.js")).copy();
    }

    // 專案路徑顯示（topnav 路徑列——寫入 bdi（LTR 隔離內容）；空＝placeholder 顯）
    set_project_path(project_path) {
        jQuery(".sc_topbar__path bdi").text(project_path);
    }

}

export const sc_topnav = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_topnav());
