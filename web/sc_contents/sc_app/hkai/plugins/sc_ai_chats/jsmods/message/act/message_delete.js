// jsmods/message/act/message_delete.js —— 刪除訊息流程（act 層——一檔一事）
// 職責：message_delete（panel 解析＋確認護衛 → 連線交 handler；成功結果本機側同檔處理）／message_delete_success（成功結果處理——畫面移除該訊息 wrapper；對話已清 → 無痕切走）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_delete {
    constructor() {}

    // 刪除整則訊息（message_delete——data-confirm_msg 確認後呼叫 API；成功即從畫面移除）
    // ※ 方法名唯一：JS class 同名方法＝最後一個為準（曾經同名 overload——撳掣會 call 到成功處理而 TypeError 靜默死）
    async message_delete(j_button) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        const j_message_panel = j_button.closest("[data-message_id]");

        // 判定：找不到訊息容器 → 不動作
        if (!j_message_panel.length) {
            return;
        }

        // 判定：使用者未確認 → 不動作
        if (!await shared.sc_web_utils().is_confirmed(j_button)) {
            return;
        }

        // registry 取值（靜態——直回單例）
        const projects = registry().sc_ai_projects();

        // project_slug：SSOT＝projects 域（唯一取法）；chat_sid：SSOT＝chats 域（唯一取法）
        const project_instance = await projects.project();
        const project_slug = project_instance.get_project_slug();
        const chats = registry().sc_ai_chats();

        const payload = {};
        payload.project_slug = project_slug;
        const chat_instance = await chats.chat();

        payload.chat_sid     = chat_instance.get_chat_sid();
        payload.message_id   = j_message_panel.attr("data-message_id");

        // 橋接駁（同域 req 橋——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const message_delete_instance = await message_instance.req().message_delete();

        const thisObject = this;

        await message_delete_instance.message_delete(payload, {
            // 成功 → 從畫面移除（對話已清 → 無痕切走）
            success: function (response_data) {
                thisObject.message_delete_success(payload, j_message_panel, response_data);
            },
            // 後端失敗／網路失敗 → 提示後中止（畫面不動）
            error: async function () {
                // 動態載入（registry——用先載）
                const i18n = registry().sc_i18n();

                toastr.error(i18n.get("message.delete_failed"));
            }
        });
    }

    // 刪除結果處理（成功 → 從畫面移除該訊息 wrapper；對話已清 → 無痕切走）
    async message_delete_success(payload, j_message_panel, response_data) {
        j_message_panel.remove();

        // 判定：整個對話已被刪除（剛刪掉最後一則——後端連對話一起清）→ 無痕導向專案根（/project_slug/）
        if (response_data.chat_deleted) {
            // 橋接駁（chat 域 act 橋——跨域新方式）
            const chat_instance_cross = await registry().sc_ai_chats().chat();
            const chat_item_switch_instance = await chat_instance_cross.act().chat_item_switch();

            await chat_item_switch_instance.chat_item_switch("");
        }
    }
}

export const message_delete = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_delete());
