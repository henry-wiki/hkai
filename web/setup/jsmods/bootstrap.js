// jsmods/bootstrap.js —— ESM entry（class + boot）
// 載入鏈：bootstrap → register.js（開機即需者靜態 import；其餘用到先載；boot 編排歸本檔）
import register from "./register.js";

class _bootstrap {
    // 啟動（DOM ready 後行一次——init 載入遮罩預設＋setup 精靈域）
    async boot() {
        // 載入遮罩（頁面／大區域預設——三種 start 未指定時沿用；兩者同款遮罩）
        const loading_mask_config = {
            mask: {
                bgcolor : "rgba(0,0,0,.3)"
            }
        };

        register().sc_loading().init({
            "page_loading"  : loading_mask_config,
            "panel_loading" : loading_mask_config
        });

        // setup 精靈域（靜態 accessor——開機即需）
        const setup = register().setup();

        await setup.init();
    }
}

// DOM ready 才啟動
jQuery(function () {
    new _bootstrap().boot();
});
