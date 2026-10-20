// jsmods/queue/act/queue_dispatch.js —— 等待列派工與回收（act 層——一 act 一檔；主動作＝queue_dispatch，queue_cancel 緊密相關同檔）
// 職責：queue_dispatch（poll 完了觸發——接線 req/queue_dispatch；成功：新訊息氣泡 insert＋隊列整排替換）／queue_cancel（接線 req/queue_cancel——送出＋結果處理：整排隊列 UI 換新）／queue_clear（接線 req/queue_clear——清空等待列；成功：整排隊列 UI 換新）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _queue_dispatch {
    constructor() {}

    // 派工（poll 完了——收尾＋下一條進場；後端做嘢，前端只鋪結果）
    async queue_dispatch(chat_sid) {
        const thisObject = this;

        const request_data = {};
        request_data.chat_sid = chat_sid;

        // 橋接駁（同域 req 橋——新方式）
        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_dispatch_instance = await queue_instance.req().queue_dispatch();

        await queue_dispatch_instance.queue_dispatch(request_data, {
            // 成功 → 結果處理（新訊息氣泡＋隊列 UI）
            success: function (response_data) {
                thisObject.handle_shift_result(response_data);
            },
            // 失敗（網路／後端拒）→ 不動作（下一拍 poll 會再觸發——收尾／進場後端冪等）
            error: function () {
            }
        });
    }

    // 結果處理：後端帶新訊息氣泡 → insert（同送出回應同一渲染源）；帶隊列 UI → 整排替換
    async handle_shift_result(response_data) {
        const result_html = response_data.html || {};

        // 判定：後端帶新訊息氣泡（有訊息進場）→ insert
        if (result_html.new_message) {
            // 橋接駁（prompt 域 act 橋——跨域新方式）
            const prompt_instance = await registry().sc_ai_chats().prompt();
            const prompt_send_instance = await prompt_instance.act().prompt_send();

            prompt_send_instance.append_render_message(result_html.new_message, response_data);
        }

        // 判定：後端帶隊列 UI → 整排替換
        if (result_html.queue_list) {
            // 橋接駁（同域 act——新方式）
            const queue_instance = await registry().sc_ai_chats().queue();
            const queue_list_instance = await queue_instance.act().queue_list();

            queue_list_instance.refresh_queue_list(result_html.queue_list);
        }
    }
    // 等待列 chip 回收（排隊期身份＝queue_uid——未進場即未成 DB 訊息，行一去即乾淨）
    async queue_cancel(queue_uid, ui_context) {
        const request_data = {};
        request_data.queue_uid = queue_uid;

        // 橋接駁（同域 req 橋——新方式）
        const queue_instance_cancel = await registry().sc_ai_chats().queue();
        const queue_cancel_instance = await queue_instance_cancel.req().queue_cancel();

        await queue_cancel_instance.queue_cancel(request_data, {
            // 成功 → 整排隊列 UI 換新（後端渲染——唯一砌 UI 者）；mini loading 先除
            success: async function (response_data) {
                ui_context.j_cancel_button.removeClass("loading");

                const result_html = response_data.html || {};

                // 判定：後端帶隊列 UI → 整排替換
                if (result_html.queue_list) {
                    // 橋接駁（同域 act——新方式）
                    const queue_instance_ui = await registry().sc_ai_chats().queue();
                    const queue_list_instance = await queue_instance_ui.act().queue_list();

                    queue_list_instance.refresh_queue_list(result_html.queue_list);
                }
            },
            // 失敗（網路／後端拒）→ chip 維持（等 poll 現況）；mini loading 除（可再撳）
            error: function () {
                ui_context.j_cancel_button.removeClass("loading");
            }
        });
    }

    // 等待列清空（整排——成個排隊檔清；成功：整排隊列 UI 換新）
    async queue_clear() {
        // 橋接駁（同域 req 橋——新方式）
        const queue_instance_clear = await registry().sc_ai_chats().queue();
        const queue_clear_instance = await queue_instance_clear.req().queue_clear();

        await queue_clear_instance.queue_clear({}, {
            // 成功 → 整排隊列 UI 換新（後端渲染——唯一砌 UI 者）
            success: async function (response_data) {
                const result_html = response_data.html || {};

                // 判定：後端帶隊列 UI → 整排替換
                if (result_html.queue_list) {
                    // 橋接駁（同域 act——新方式）
                    const queue_instance_ui = await registry().sc_ai_chats().queue();
                    const queue_list_instance = await queue_instance_ui.act().queue_list();

                    queue_list_instance.refresh_queue_list(result_html.queue_list);
                }
            },
            // 失敗（網路／後端拒）→ 不動作
            error: function () {
            }
        });
    }
}

export const queue_dispatch = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue_dispatch());
