// jsmods/prompt/evt/evt_prompt_draft_restore.js —— 草稿還原綁定（factory 單例——一檔一事）
// 職責：頁面載入 → draft_fetch（meta＋附件）→ 回填輸入框＋附件 chips（另一裝置／重載即見）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

// 判定草稿欄位有冇提交（有 key 且非 null；""＝有提交但清空）
const has_draft_field = function (draft_meta, field_key) {
    // 判定：冇呢個 key → 未提交
    if (!Object.prototype.hasOwnProperty.call(draft_meta, field_key)) {
        return false;
    }

    // 判定：值係 null → 未提交
    if (draft_meta[field_key] === null) {
        return false;
    }

    return true;
};

class _evt_prompt_draft_restore {
    constructor() {}

    // 啟動（由 prompt evt init 呼叫——一次）
    init() {
        this.prompt_draft_restore();
    }

    // 讀草稿並回填（冇草稿＝靜默；composer 未載入 → 等 load_prompt hook 再叫）
    async prompt_draft_restore(is_force_apply) {
        const thisObject = this;

        // 判定：composer 未載入（data-loaded 唔係 1）→ 唔做（hook 會再叫）
        if (jQuery(".sc_composer").attr("data-loaded") !== "1") {
            return;
        }

        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        let project_slug = project_instance.get_project_slug();

        const prompt_instance = await registry().sc_ai_chats().prompt();

        // 判定：URL 冇專案（home）→ 用 composer 專案
        if (project_slug === "") {
            project_slug = prompt_instance.get_composer_project_slug();
        }

        let chat_sid = await prompt_instance.get_active_chat_sid();

        // 判定：未配 chat_sid（新 prompt 未 init）→ 唔查（冇草稿可讀）
        if (chat_sid === "") {
            return;
        }

        const fetch_instance = await prompt_instance.req().draft_fetch();

        let fetch_payload = {};
        fetch_payload.project_slug = project_slug;
        fetch_payload.chat_sid = chat_sid;

        let fetch_result = null;

        await new Promise(function (resolve) {
            fetch_instance.draft_fetch(fetch_payload, {
                success: function (result) {
                    fetch_result = result;
                    resolve();
                },
                error: function () {
                    resolve();
                }
            });
        });

        // 判定：冇草稿（404）→ 立即建立草稿（後端分配新 prompt_id UUIDv7）
        if (fetch_result === null) {
            await thisObject.create_empty_draft(project_slug, chat_sid, prompt_instance);

            return;
        }

        // 判定：攞到回應但 meta 空 → 同樣立即建立
        if (Object.keys(fetch_result.meta || {}).length < 1) {
            await thisObject.create_empty_draft(project_slug, chat_sid, prompt_instance);

            return;
        }

        await thisObject.apply_draft(fetch_result, is_force_apply);
    }

    // 冇草稿時立即建立（prompt_id 由後端 sc_uuid7 產生）
    async create_empty_draft(project_slug, chat_sid, prompt_instance) {
        const thisObject = this;
        const create_instance = await prompt_instance.req().draft_create();

        const create_payload = {};
        create_payload.project_slug = project_slug;
        create_payload.chat_sid = chat_sid;

        await new Promise(function (resolve) {
            create_instance.draft_create(create_payload, {
                success: function (result) {
                    resolve();
                },
                error: function () {
                    resolve();
                }
            });
        });
    }

    // 回填文字＋附件
    async apply_draft(draft_result, is_force_apply) {
        const draft_meta = draft_result.meta || {};
        let draft_text = "";

        // 判定：空 meta（冇草稿）→ 只清附件 chips（保留後端已渲染嘅預設）
        if (Object.keys(draft_meta).length < 1) {
            const attach_instance_empty = await registry().sc_ai_chats().prompt().then(function (prompt_instance_item) {
                return prompt_instance_item.evt().prompt_attach();
            });

            attach_instance_empty.restore_from_draft([]);

            return;
        }

        // 判定：meta 有文字 → 取出
        if (typeof draft_meta.prompt_text === "string") {
            draft_text = draft_meta.prompt_text;
        }

        let j_input = jQuery("#sc_input");

        const prompt_instance = await registry().sc_ai_chats().prompt();

        // 判定：輸入框空 或 強制覆蓋 → 以草稿為準（草稿係空字串＝清空）
        if (j_input.length > 0 && (j_input.val() === "" || is_force_apply === true)) {
            j_input.val(draft_text);

            // UI 同步（直接叫兩支——唔經 input 事件，避免觸發草稿寫入）
            const prompt_act_instance = prompt_instance.act();
            const send_toggle_instance = await prompt_act_instance.prompt_send_button_toggle();
            send_toggle_instance.prompt_send_button_toggle();

            const prompt_obs_instance = prompt_instance.obs();
            const height_observer_instance = await prompt_obs_instance.prompt_input_height_observer();
            height_observer_instance.refresh_height();
        }

        const attach_instance = await prompt_instance.evt().prompt_attach();

        // 一律用後端清單重畫（空清單 → 收起附件區）
        attach_instance.restore_from_draft(draft_result.attachments || []);

        await this.apply_draft_model(draft_meta);
    }

    // 還原 model（provider／model／effort）＋chat_mode（hidden fields＋顯示＋按鈕）
    async apply_draft_model(draft_meta) {
        const chat_option_instance = await registry().sc_ai_chats().chat_option();
        const model_info_observer_instance = await chat_option_instance.obs().model_info_observer();

        // 暫停觀察器（還原寫值唔算用戶修改——唔提交）
        model_info_observer_instance.pause();

        const providers_instance = await registry().sc_ai_providers();
        const model_selector_instance = await providers_instance.model_selector();
        const model_context_instance = await model_selector_instance.act().model_context();

        // 判定：provider_id 有提交（有 key 且非 null）→ 寫返；""＝清空、冇 key／null＝未提交
        if (has_draft_field(draft_meta, "provider_id")) {
            model_context_instance.set_provider_id(String(draft_meta.provider_id));
        }

        // 判定：model_id 有提交 → 寫返
        if (has_draft_field(draft_meta, "model_id")) {
            model_context_instance.set_model_id(String(draft_meta.model_id));
        }

        // 判定：reasoning_effort 有提交 → 寫返
        if (has_draft_field(draft_meta, "reasoning_effort")) {
            model_context_instance.set_reasoning_effort(String(draft_meta.reasoning_effort));
        }

        // 判定：chat_mode 有提交 → 寫返 hidden field
        if (has_draft_field(draft_meta, "chat_mode")) {
            model_context_instance.set_chat_mode(String(draft_meta.chat_mode));
        }

        // model 顯示欄刷新（provider＋model＋effort）
        const refresh_display_instance = await model_selector_instance.act().refresh_model_display();
        await refresh_display_instance.refresh_model_display_info();

        // 判定：chat_mode 有提交才刷新按鈕（"" ＝ 清空 → 回第一粒；冇 key／null ＝ 唔動）
        if (has_draft_field(draft_meta, "chat_mode")) {
            const refresh_chat_mode_instance = await chat_option_instance.act().refresh_chat_mode_active();
            refresh_chat_mode_instance.refresh_chat_mode_active(String(draft_meta.chat_mode));
        }

        // 恢復觀察器（丟棄還原期間嘅 mutation）
        model_info_observer_instance.resume();
    }
}

export const prompt_draft_restore = (function (instance) {
    return function () {

        return instance;
    };
})(new _evt_prompt_draft_restore());
