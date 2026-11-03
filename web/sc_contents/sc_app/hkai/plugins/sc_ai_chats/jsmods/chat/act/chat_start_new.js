// jsmods/chat/act/chat_start_new.js —— 進入新對話模式（act 層——一檔一事）
// 職責：chat_start_new（切去新對話語境＋重抓 composer 取新 sid＋輸入框清空聚焦——第一則訊息送出時才建對話）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _chat_start_new {
    constructor() {}

        // 進新對話模式（不選任何既有對話——送出第一則訊息時才由後端建立新對話）
    async chat_start_new() {
        // 切到「未選擇對話」（換主——清單高亮全落＋窗側主鍵對齊；URL 換喺 evt 層做完）
        // 橋接駁（同域 act——新方式）
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_switch_instance = await chat_instance.act().chat_item_switch();

        await chat_item_switch_instance.chat_item_switch("");

        // composer 重抓（URL 已無 sid → 後端派新 chat_sid；防止沿用上一個對話嘅 hidden sid）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_fetch_instance = await prompt_instance.act().prompt_fetch();

        await prompt_fetch_instance.prompt_fetch({ chat_sid: "" });

        // 訊息窗清空（新對話未有訊息——重取即空；URL 已由 prompt_fetch 換到新 sid）
        const message_instance = await registry().sc_ai_chats().message();
        const message_list_fetch_instance = await message_instance.act().message_list_fetch();

        await message_list_fetch_instance.message_list_fetch();

        // 輸入框清空 ＋ 聚焦（意圖明確：要打新問題）
        jQuery("#sc_input").val("").trigger("focus");

        // 通知 input handlers（主按鈕同步——val() 本身不觸發事件）
        jQuery("#sc_input").trigger("input");
    }
}

export const chat_start_new = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_start_new());
