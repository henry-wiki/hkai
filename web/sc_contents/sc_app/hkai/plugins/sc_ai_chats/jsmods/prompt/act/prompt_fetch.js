// jsmods/prompt/act/prompt_fetch.js —— prompt 區塊載入（act 層——一檔一事）
// 職責：prompt_fetch（prompt 區塊載入——handler；成功先渲染：內裏整包換＋input 重算＋派發 load_prompt）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_fetch {
    constructor() {}

    // prompt 區塊載入（連載最後一棒——由訊息窗口載入後叫；成功先渲染）
    async prompt_fetch(callbacks) {
        callbacks = typeof callbacks !== "object" ? {} : callbacks;

        // 橋接駁（同域——instance 畀 req）
        const chats = registry().sc_ai_chats();
        const prompt_instance = await chats.prompt();

        // 派發 load_prompt hook（before 相位——prompt 載入前；跨模組用家各自嘅反應）
        chats.execute_hook_before("load_prompt", []);

        // 專案判定（快路——未選專案：直接清空 composer（唔發請求）；清空＝內容清走、元素長駐——唔 remove）
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();

        // 判定：未選專案（URL 冇 slug）→ 直接清空（唔等後端）
        if (project_slug === "") {
            jQuery(".sc_composer").html("");

            // 通知 input handler（主按鈕兩態重算）
            jQuery("#sc_input").trigger("input");

            // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
            jQuery(".sc_composer").attr("data-loaded", "1");

            // prompt 區塊載入完成 → 派發 load_prompt hook（外部用家各自嘅反應）
            chats.execute_hook_after("load_prompt", {});

            return;
        }

        // 橋接駁（req 橋——新方式）
        const prompt_fetch_instance = await prompt_instance.req().prompt_fetch();

        return prompt_fetch_instance.prompt_fetch({
            success: function (response_data) {
                // 內裏整包換（後端砌好——前端只 insert；外層 .sc_composer 頁面長駐；事件走 document 委派）
                jQuery(".sc_composer").html(response_data.html.composer);

                // 通知 input handler（主按鈕兩態重算）
                jQuery("#sc_input").trigger("input");

                // 載入標記（data-loaded 0→1——元素「已載入」語義；用家待未來）
                jQuery(".sc_composer").attr("data-loaded", "1");

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
})(new _prompt_fetch());
