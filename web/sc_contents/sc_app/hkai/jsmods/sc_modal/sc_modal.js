// jsmods/sc_modal/sc_modal.js —— 通用 modal 框架根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_modal()（單例）；域接駁：(await registry().sc_modal().modal()).act().create_modal() 咁叫
// 職責：① 啟動編排（init——域召集）② 對外 facade（create_modal——薄轉發域橋）
// 契約：1 域（modal）；域動作住 modal/modal.js 檔尾（bridge class——每條自載）
// 用法示例：
//   const sc_modal = registry().sc_modal();
//   const j_modal = await sc_modal.create_modal({
//       modal_id: "demo",                     // 必填——data-modal 屬性值（替換式清理依據）
//       modal_title: "Demo Modal",            // 標題文字（留空不處理——.modal__title 由 header 槽自訂）
//       header: (j_modal_header) => {...},    // header 槽 content（string/dom/jElement/function）
//       body: jQuery("<div>hello</div>"),     // body 槽 content
//       footer: null,                         // footer 槽 content（undefined/null → 跳過）
//   });
//   j_modal.addClass("visible");            // 顯示（消費端自行控制）
//   關閉 = 移除式：✕ 鈕 / mask 點擊 / j_modal[0].modal_close()
// 回傳：j_modal DOM 元素（.modal__overlay）——modal functions 掛在元素上

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_modal {
    constructor() {
        this.inited = false;
        this.hooks = {};
    }

    // 啟動（域召集——事件綁定；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（modal 域——事件綁定）
        const modal_instance = await this.modal();
        await modal_instance.init();

        return this;
    }

    // 建立 modal（對外 facade——薄轉發域橋；原 sync 改 async——呼叫端要 await）
    async create_modal(modal_config) {
        // 橋接駁（同域 act 橋）
        const modal_domain = await this.modal();
        const create_modal_instance = await modal_domain.act().create_modal();

        return create_modal_instance.create_modal(modal_config);
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_modal.prototype.modal = async function () { return (await import("./modal/modal.js")).modal(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_modal.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_modal = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_modal());
