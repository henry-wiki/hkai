// jsmods/prompt/act/prompt_draft_save.js —— 輸入草稿活動寫入（act 層——一檔一事）
// 職責：prompt_draft_save（#sc_input 內容有變／chat options 提交 → 寫入活動 prompt_draft——防突然關掉失稿；節流／即寫兩式）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _prompt_draft_save {
    constructor() {
        // 節流計時器（打字停手先寫一次——避免逐字 POST）
        this.throttle_timer = null;

        // 節流延遲（毫秒——打字停 0.8 秒算一段）
        this.throttle_delay_ms = 800;
    }

    // 草稿寫入排程（input 事件→節流；change／提交點→即寫）
    prompt_draft_save(save_immediately) {
        clearTimeout(this.throttle_timer);

        // 判定：即寫（change 失焦／chat options 提交）→ 立即寫
        if (save_immediately === true) {
            this.write_prompt_draft();
            return;
        }

        // input（打字中）→ 節流排程（停手 0.8 秒先寫）
        this.throttle_timer = setTimeout(this.write_prompt_draft.bind(this), this.throttle_delay_ms);
    }

    // 實際寫入（bundle：model info／chat mode／文字；scope：語境字串〔chat_sid／project_slug——後端解析 id〕——活動名 prompt_draft）
    async write_prompt_draft() {
        // 輸入內容（#sc_input 現值——傳咩寫咩）
        const prompt_text = jQuery("#sc_input").val();

        // 對話 sid／專案 slug（URL 語境——本機側唯一取法）
        const chats = registry().sc_ai_chats();
        const chat_instance = await chats.chat();
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();

        // model info（composer hidden fields——現行選取；model_context 讀）
        const providers_instance = await registry().sc_ai_providers();
        const model_selector_instance = await providers_instance.model_selector();
        const model_context_instance = await model_selector_instance.act().model_context();

        // 活動內容（草稿整包——provider_id／model_id／reasoning_effort／chat_mode＋text）
        const activity_bundle = {};
        activity_bundle.provider_id      = model_context_instance.get_context_provider_id();
        activity_bundle.model_id         = model_context_instance.get_context_model_id();
        activity_bundle.reasoning_effort = model_context_instance.get_context_reasoning_effort();
        activity_bundle.chat_mode        = model_context_instance.get_context_chat_mode();
        activity_bundle.text             = prompt_text;

        // 活動 scope（語境字串——chat_sid／project_slug；project_id／chat_id 由後端解析）
        const activity_scope = {};
        activity_scope.chat_sid     = chat_instance.get_chat_sid();
        activity_scope.project_slug = project_instance.get_project_slug();

        // 動態載入（registry——用先載）
        const sc_page_activity_instance = await registry().sc_page_activity();

        await sc_page_activity_instance.set_activity("prompt_draft", activity_bundle, activity_scope);
    }
}

export const prompt_draft_save = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_draft_save());
