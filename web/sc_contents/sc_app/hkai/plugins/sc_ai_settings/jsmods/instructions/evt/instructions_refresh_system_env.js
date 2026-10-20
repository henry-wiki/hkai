// jsmods/instructions/evt/instructions_refresh_system_env.js —— Instructions 更新鈕綁定（evt 層——一檔一事）
// 職責：instructions_refresh_system_env（[data-btn='refresh_system_env'] 點擊 → 重新偵測系統環境）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _instructions_refresh_system_env {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_instructions_refresh_system_env")
            .on("click.sc_ai_settings_instructions_refresh_system_env", "[data-modal='settings_modal'] [data-btn='refresh_system_env']", this.instructions_refresh_system_env.bind(this));
    }

    // 動作：更新系統環境（modal 根經 modal 狀態取）
    async instructions_refresh_system_env() {
        // 動態載入（registry——用先載）

        // spawn 取組（modal 狀態 j_modal／instructions 分頁）
        const modal_domain = await (await registry().sc_ai_settings()).modal();
        const modal_module = await modal_domain.act().settings_modal();
        const instructions_domain = await (await registry().sc_ai_settings()).instructions();
        const instructions_module = await instructions_domain.act().instructions();

        instructions_module.settings_refresh_system_env(modal_module.j_modal);
    }
}

export const instructions_refresh_system_env = (function (instance) {
    return function () {

        return instance;
    };
})(new _instructions_refresh_system_env());
