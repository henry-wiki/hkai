// jsmods/message/evt/permission_decide.js —— 權限提示 Allow／Deny 綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（permission_decide）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 結構：eager 單例 closure factory（具名 export——entry init 動態 import 解構後呼叫 init()）

class _permission_decide {
    constructor() {}

    // 綁定註冊（由 entry init 呼叫——一次；selector 委派）
    init() {
        // 權限提示卡嘅 Allow／Deny 掣（data-decision 分辨兩態）
        jQuery(document)
            .off("click.sc_ai_chats_message_permission_decide")
            .on("click.sc_ai_chats_message_permission_decide", "[data-action='permission_decide']", this.permission_decide.bind(this));
    }

    // 決定（prepare data（卡嘅 request_id＋掣嘅 decision＋apply_all 旗標＋訊息 id）→ 交 act/permission_decide）
    async permission_decide(event) {
        const j_decide_button = jQuery(event.currentTarget);
        const j_permission_prompt = j_decide_button.closest(".permission_prompt");

        // 判定：唔係權限卡內嘅掣 → 唔處理
        if (!j_permission_prompt.length) {
            return;
        }

        const j_message_wrapper = j_permission_prompt.closest(".message_wrapper[data-message_id]");

        // 判定：揾唔到所屬訊息 → 唔處理
        if (!j_message_wrapper.length) {
            return;
        }

        // 套用到全部（checkbox → apply_all 數字旗標——勾＝1／唔勾＝0；decision 保持單字從掣讀）
        const request_decision = j_decide_button.attr("data-decision");
        const j_apply_all_box = j_permission_prompt.find(".permission_prompt__apply_all_box");

        let request_apply_all = 0;

        // 判定：checkbox 勾咗 → 套用到全部（apply_all=1）
        if (j_apply_all_box.prop("checked")) {
            request_apply_all = 1;
        }

        const request_data = {
            message_id: j_message_wrapper.attr("data-message_id"),
            request_id: j_permission_prompt.attr("data-request_id"),
            decision:   request_decision,
            apply_all:  request_apply_all,
        };

        // mini loading（click 等待期——交 act 收尾除）
        j_decide_button.addClass("loading");

        // 橋接駁（同域 act——新方式）
        const message_instance = await registry().sc_ai_chats().message();
        const permission_decide_instance = await message_instance.act().permission_decide();

        permission_decide_instance.permission_decide(request_data, {
            j_permission_prompt: j_permission_prompt,
            j_decide_button: j_decide_button,
        });
    }
}

export const permission_decide = (function (instance) {
    return function () {

        return instance;
    };
})(new _permission_decide());
