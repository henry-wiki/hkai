// jsmods/chat_option/act/submit_chat_options.js —— chat options 提交（act 層——一檔一事）
// 職責：submit_chat_options（唯一提交點——由 model_info 容器組 payload → 寫入活動 prompt_draft（prompt 域草稿寫入）；同上次提交一樣＝唔提交（changed or not））
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _submit_chat_options {
    constructor() {
        // 上次提交咗嘅 payload JSON（去重——同上次一樣＝唔提交）
        this.last_submitted_json = null;
    }

    // 提交 chat options（observer 見真變叫；同值重複嘅紀錄喺呢度濾）
    async submit_chat_options() {
        // 橋接駁（selector 域 act——讀容器用）
        const model_selector_instance = await (await registry().sc_ai_providers()).model_selector();
        const model_context_instance = await model_selector_instance.act().model_context();

        const projects = registry().sc_ai_projects();

        const chat_options_payload = {};

        const chat_instance = await registry().sc_ai_chats().chat();

        chat_options_payload.chat_sid = chat_instance.get_chat_sid();
        const project_instance = await projects.project();
        chat_options_payload.project_slug = project_instance.get_project_slug();
        chat_options_payload.provider_id = model_context_instance.get_context_provider_id();
        chat_options_payload.model_id = model_context_instance.get_context_model_id();
        chat_options_payload.reasoning_effort = model_context_instance.get_context_reasoning_effort();
        chat_options_payload.chat_mode = model_context_instance.get_context_chat_mode();

        // 去重（setAttribute 同值都會有紀錄——同上次提交一樣＝唔提交）
        const chat_options_payload_json = JSON.stringify(chat_options_payload);

        // 判定：同上次一樣 → 唔提交
        if (chat_options_payload_json === this.last_submitted_json) {
            return;
        }

        this.last_submitted_json = chat_options_payload_json;

        // 落地（prompt 域草稿寫入——即寫；寫入活動 prompt_draft）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_draft_save_instance = await prompt_instance.act().prompt_draft_save();

        prompt_draft_save_instance.prompt_draft_save(true);
    }
}

export const submit_chat_options = (function (instance) {
    return function () {

        return instance;
    };
})(new _submit_chat_options());
