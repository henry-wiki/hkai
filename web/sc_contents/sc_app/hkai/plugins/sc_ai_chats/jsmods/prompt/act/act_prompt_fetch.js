// jsmods/prompt/act/act_prompt_fetch.js —— prompt 區塊載入（act 層——一檔一事）
// 職責：prompt_fetch（prompt 區塊載入——handler；成功先渲染：內裏整包換＋input 重算＋派發 load_prompt）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _act_prompt_fetch {
    constructor() {}

    // prompt 區塊載入（連載最後一棒——由訊息窗口載入後叫；成功先渲染）
    async prompt_fetch(callbacks) {
        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        // 橋接駁（同域——instance 畀 req）
        const chats = registry().sc_ai_chats();
        const prompt_instance = await chats.prompt();

        // 派發 load_prompt hook（before 相位——prompt 載入前；跨模組用家各自嘅反應）
        chats.execute_hook_before("load_prompt", []);

        // 橋接駁（req 橋——新方式）
        const prompt_fetch_instance = await prompt_instance.req().prompt_fetch();

        // 請求選項（caller 指定 chat_sid 時照傳——空＝新對話）
        const request_options = {};

        // 判定：caller 明確要新對話（空 sid）→ 准換網址
        const is_new_chat_request = (callbacks && callbacks.chat_sid === "");

        // 判定：caller 有指定 chat_sid → 轉交
        if (callbacks && typeof callbacks.chat_sid === "string") {
            request_options.chat_sid = callbacks.chat_sid;
        }

        return prompt_fetch_instance.prompt_fetch({
            chat_sid: request_options.chat_sid,
            success: async function (response_data) {
                // 內裏整包換（後端砌好——前端只 insert；外層 .sc_composer 頁面長駐；事件走 document 委派）
                jQuery(".sc_composer").html(response_data.html.composer);

                // 模板資料（base64 隱藏 div）隨 composer 一併換入——此處零處理

                // 主按鈕兩態重算（直接叫 toggle——唔經 input 事件，避免排程草稿寫入）
                prompt_instance.act().prompt_send_button_state().then(function (toggle_instance) {
                    toggle_instance.refresh();
                });

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                jQuery(".sc_composer").attr("data-loaded", "1");

                // 後端回嘅專案 slug（home＝playground）——prompt_send 用
                prompt_instance.set_composer_project_slug(response_data.project_slug);

                // 網址跟隨後端派嘅 sid（主人＝後端；URL 只係鏡像）
                const chat_instance = await registry().sc_ai_chats().chat();
                const response_chat_sid = String(response_data.chat_sid || "");
                const response_project_slug = String(response_data.project_slug || "");

                // 判定：只有「新對話」請求才對齊網址（普通重取唔准劫持 URL）
                if (is_new_chat_request === true) {
                    // 判定：後端有派 sid、且與 URL 唔同 → 靜默換網址
                    if (response_chat_sid !== "" && chat_instance.get_chat_sid() !== response_chat_sid) {
                        sc_url.shallow_replace_url("/" + response_project_slug + "/" + response_chat_sid + "/");
                    }
                }

                // prompt 區塊載入完成 → 派發 load_prompt hook（外部用家各自嘅反應）
                chats.execute_hook_after("load_prompt", response_data);
            },
            error: function (response_data) {
                // 失敗 → 不更新（維持現狀）
                if (typeof callbacks.error === "function") {
                    callbacks.error(response_data);
                }
            }
        });
    }
}

export const prompt_fetch = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_prompt_fetch());
