// jsmods/prompt/act/prompt_draft_save.js —— 草稿即時保存（act 層——一檔一事）
// 職責：prompt_draft_save（#sc_input 內容有變／chat options 提交 → 寫 draft meta——文字＋model＋reasoning＋chat_mode）
// 舊 activity prompt_draft 已停用（本檔改為唯一草稿寫入點）
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

    // 實際寫入（文字＋provider／model／reasoning／chat_mode → draft meta；agent 由後端補）
    async write_prompt_draft() {
        // 輸入框未載入時 val() 係 undefined → 一律當空字串（否則空草稿都會寫入）
        const prompt_text = String(jQuery("#sc_input").val() || "");

        const chats = registry().sc_ai_chats();
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        var project_slug = project_instance.get_project_slug();

        const prompt_instance = await chats.prompt();

        // 判定：URL 冇專案（home）→ 用 composer 專案
        if (project_slug === "") {
            project_slug = prompt_instance.get_composer_project_slug();
        }

        const attach_instance = await prompt_instance.evt().prompt_attach();

        // 判定：冇文字又冇附件 → 唔寫（唔建草稿目錄）
        if (prompt_text === "" && attach_instance.get_files().length < 1) {
            return;
        }

        // model info（composer hidden fields——現行選取；model_context 讀）
        const providers_instance = await registry().sc_ai_providers();
        const model_selector_instance = await providers_instance.model_selector();
        const model_context_instance = await model_selector_instance.act().model_context();

        const payload = {};
        payload.project_slug = project_slug;
        payload.chat_sid = await prompt_instance.get_active_chat_sid();
        payload.prompt_text = prompt_text;
        payload.provider_id = model_context_instance.get_context_provider_id();
        payload.model_id = model_context_instance.get_context_model_id();
        payload.reasoning_effort = model_context_instance.get_context_reasoning_effort();
        payload.chat_mode = model_context_instance.get_context_chat_mode();

        const save_instance = await prompt_instance.req().draft_save_meta();

        save_instance.draft_save_meta(payload, {
            success: async function () {
                // 首次寫入成功 → URL 未帶 sid 就靜默換（重載可憑 sid 取回草稿）
                await prompt_instance.sync_chat_url(project_slug);
            },
            error: function () {
            }
        });
    }
}

export const prompt_draft_save = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_draft_save());
