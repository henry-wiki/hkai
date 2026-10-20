// jsmods/bind/process_kill.js —— Process KILL 鈕綁定（bind 層——一檔一事）
// 職責：process_kill（[data-process_kill] 點擊 → 終止程序流程）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _process_kill {
    constructor() {}

    // 綁定註冊（由 host 呼叫——一次；selector 委派——[data-modal='settings_modal'] 範圍＝原 bind_modal 等價）
    init() {
        jQuery(document)
            .off("click.sc_ai_settings_process_kill")
            .on("click.sc_ai_settings_process_kill", "[data-modal='settings_modal'] [data-process_kill]", this.process_kill.bind(this));
    }

    // 動作：終止程序（KILL）
    async process_kill(event) {
        // 動態載入（registry——用先載）

        const j_button = jQuery(event.currentTarget);

        // spawn 取組（process 分頁）
        const process_domain = await (await registry().sc_ai_settings()).process();
        const process_module = await process_domain.act().process();

        process_module.kill_process(j_button);
    }
}

export const process_kill = (function (instance) {
    return function () {

        return instance;
    };
})(new _process_kill());
