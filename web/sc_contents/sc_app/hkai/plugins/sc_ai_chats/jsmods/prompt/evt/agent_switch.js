// jsmods/prompt/evt/agent_switch.js —— agent 選擇按鈕綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（agent_switch）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _agent_switch {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // agent 選擇（goose／aider／openhands——切換現行 agent）
        jQuery(document)
            .off("click.sc_ai_chats_agent_switch")
            .on("click.sc_ai_chats_agent_switch", "[data-btn='agent']", this.agent_switch.bind(this));
    }

    // agent 切換（[data-btn='agent'] → 取 data-value → 交 req；成功即重渲 composer（mode 掣跟新 agent））
    async agent_switch(event) {
        // 點擊的 agent 值（按鈕 data-value——本次切換的來源）
        const agent_value = jQuery(event.currentTarget).data("value");

        // 橋接駁（同域 req——連線側）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const agent_switch_req_instance = await prompt_instance.req().agent_switch();

        agent_switch_req_instance.agent_switch(agent_value, {
            success: async function () {
                // 重渲 prompt 區塊（後端砌——toolbar active／mode 掣／dock 一齊跟新 agent；前端只 insert）
                const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

                prompt_fetch_instance.prompt_fetch();
            },
            error: function (result) {
                console.error("agent_switch failed:", result);
            },
        });
    }
}

export const agent_switch = (function (instance) {
    return function () {

        return instance;
    };
})(new _agent_switch());
