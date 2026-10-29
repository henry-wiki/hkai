// jsmods/stream/act/act_prompt_updater.js —— 草稿變動套用（act 層——一檔一事）
// 職責：收 health_stream 的 prompt change → 還原 composer／清空輸入框；唔判斷版本

class _act_prompt_updater {
    constructor() {}

    // 草稿有變 → 依 change 類型套用（deleted＝清空；其餘＝還原 composer）
    async apply_prompt_change(draft_change) {
        // 判定：本頁草稿被刪（另一邊送出）→ 清輸入框＋附件
        if (draft_change.type === "deleted") {
            await this.clear_composer();

            return;
        }

        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_draft_restore_instance = await prompt_instance.evt().prompt_draft_restore();

        await prompt_draft_restore_instance.prompt_draft_restore(true);
    }

    // 清輸入框＋附件 chips（內部用——apply_prompt_change 的 deleted 分支）
    async clear_composer() {
        const prompt_instance = await registry().sc_ai_chats().prompt();

        const attach_instance = await prompt_instance.evt().prompt_attach();
        attach_instance.restore_from_draft([]);

        const j_input = jQuery("#sc_input");

        // 判定：有輸入框 → 清空
        if (j_input.length > 0) {
            j_input.val("");
        }

        const send_toggle_instance = await prompt_instance.act().prompt_send_button_toggle();
        send_toggle_instance.prompt_send_button_toggle();

        const height_observer_instance = await prompt_instance.obs().prompt_input_height_observer();
        height_observer_instance.refresh_height();
    }
}

export const act_prompt_updater = (function (instance) {
    return function () {

        return instance;
    };
})(new _act_prompt_updater());
