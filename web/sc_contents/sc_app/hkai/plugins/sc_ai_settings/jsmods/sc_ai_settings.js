// jsmods/sc_ai_settings.js —— settings plugin 根入口（唯一入口——出入口掛喺 prototype；對齊 sc_ai_chats 形）
// 用途：registry().sc_ai_settings()（單例）；域接駁：(await (await registry().sc_ai_settings()).modal()).act().<動作>() 咁叫
// 職責：啟動編排（init——bootstrap 呼叫；域召集＋modal 層 init）
// 契約：8 域（modal／provider／server／process／instructions／about／general／settings）；域動作住各域 <域>.js 檔尾（bridge class——每條自載）

import { _domain_hooks } from "./_common/_domain_hooks.js";

class _sc_ai_settings {
    constructor() {
        this.inited = false;
        this.hooks = {};
    }

    // 啟動 plugin（bootstrap 取例項後呼叫——域召集＋modal 層 init；重複呼叫只行一次）
    async init() {
        // 判定：已啟動 → 直回
        if (this.inited) {
            return this;
        }

        this.inited = true;

        // 域召集（modal 域——事件綁定）
        const modal_instance = await this.modal();
        await modal_instance.init();

        // 域召集（server 域——事件綁定）
        const server_instance = await this.server();
        await server_instance.init();

        // 域召集（process 域——事件綁定）
        const process_instance = await this.process();
        await process_instance.init();

        // 域召集（instructions 域——事件綁定）
        const instructions_instance = await this.instructions();
        await instructions_instance.init();

        // 域召集（settings 域——事件綁定）
        const settings_instance = await this.settings();
        await settings_instance.init();

        // 域召集（provider 域——事件綁定）
        const provider_instance = await this.provider();
        await provider_instance.init();

        // 域召集（general 域——事件綁定）
        const general_instance = await this.general();
        await general_instance.init();

        // modal 層 init（settings_modal ref 接線）
        const modal_domain = await this.modal();
        const settings_modal_instance = await modal_domain.act().settings_modal();
        await settings_modal_instance.init();

        return this;
    }

}

// 連接（出入口掛上 prototype——唯一入口；動態載入＝用先載）
_sc_ai_settings.prototype.general   = async function () { return (await import("./general/general.js")).general(); };
_sc_ai_settings.prototype.modal     = async function () { return (await import("./modal/modal.js")).modal(); };
_sc_ai_settings.prototype.provider  = async function () { return (await import("./provider/provider.js")).provider(); };
_sc_ai_settings.prototype.about     = async function () { return (await import("./about/about.js")).about(); };
_sc_ai_settings.prototype.about     = async function () { return (await import("./about/about.js")).about(); };
_sc_ai_settings.prototype.server    = async function () { return (await import("./server/server.js")).server(); };
_sc_ai_settings.prototype.process   = async function () { return (await import("./process/process.js")).process(); };
_sc_ai_settings.prototype.instructions = async function () { return (await import("./instructions/instructions.js")).instructions(); };
_sc_ai_settings.prototype.settings  = async function () { return (await import("./settings/settings.js")).settings(); };

// 連接（hook 機制掛上 root——唯一位置；F12 追唔到為已知代價）
Object.assign(_sc_ai_settings.prototype, {
    register_hook       : _domain_hooks.prototype.register_hook,
    execute_hook_before : _domain_hooks.prototype.execute_hook_before,
    execute_hook_after  : _domain_hooks.prototype.execute_hook_after,
});

export const sc_ai_settings = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_ai_settings());
