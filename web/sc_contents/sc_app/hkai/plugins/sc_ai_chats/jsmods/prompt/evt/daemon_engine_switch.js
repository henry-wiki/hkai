// jsmods/prompt/evt/daemon_engine_switch.js —— agent 選擇按鈕綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（daemon_engine_switch）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _daemon_engine_switch {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // agent 選擇（aider／goose——切換現行引擎）
        jQuery(document)
            .off("click.sc_ai_chats_daemon_engine_switch")
            .on("click.sc_ai_chats_daemon_engine_switch", "[data-btn='daemon_engine']", this.daemon_engine_switch.bind(this));
    }

    // agent 切換（[data-btn='daemon_engine'] → 取 data-value → 交 req；成功即重渲 composer（mode 掣跟新引擎））
    async daemon_engine_switch(event) {
        // 點擊的引擎值（按鈕 data-value——本次切換的來源）
        const daemon_engine_value = jQuery(event.currentTarget).data("value");

        // 橋接駁（同域 req——連線側）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const daemon_engine_switch_req_instance = await prompt_instance.req().daemon_engine_switch();

        daemon_engine_switch_req_instance.daemon_engine_switch(daemon_engine_value, {
            success: async function () {
                // 重渲 prompt 區塊（後端砌——toolbar active／mode 掣／dock 一齊跟新引擎；前端只 insert）
                const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

                prompt_fetch_instance.prompt_fetch();
            },
            error: function (result) {
                console.error("daemon_engine_switch failed:", result);
            },
        });
    }
}

export const daemon_engine_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _daemon_engine_switch());
