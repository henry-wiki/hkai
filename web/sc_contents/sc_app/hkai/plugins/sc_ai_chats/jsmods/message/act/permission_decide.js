// jsmods/message/act/permission_decide.js —— 權限決定送出與結果處理（act 層——一 act 一檔）
// 職責：permission_decide（接線 req/permission_decide——成功：卡即收（poll 下一拍亦唔再出）；失敗：還原可再撳）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _permission_decide {
    constructor() {}

    // 送出決定（收 request_data＋ui_context——連線交 req；結果處理喺本檔）
    async permission_decide(request_data, ui_context) {
        const thisObject = this;

        // 橋接駁（同域 req 橋——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const permission_decide_instance = await message_instance.req().permission_decide();

        await permission_decide_instance.permission_decide(request_data, {
            // 成功 → 卡即收（後端已寫決定檔——poll 拍唔會再出）
            success: function (result) {
                thisObject.permission_decide_success(ui_context, result);
            },
            // 失敗（網路／後端拒）→ 還原可再撳
            error: function (result) {
                thisObject.permission_decide_error(ui_context, result);
            }
        });
    }

    // 成功處理：收卡（淡出移除——決定已生效）
    permission_decide_success(ui_context, result) {
        ui_context.j_permission_prompt.fadeOut(150, function () {
            jQuery(this).remove();
        });
    }

    // 失敗處理：除 loading（維持現狀——等 poll 或再撳）
    permission_decide_error(ui_context, result) {
        ui_context.j_decide_button.removeClass("loading");
    }
}

export const permission_decide = (function (instance) {
    return function () {

        return instance;
    };
})(new _permission_decide());
