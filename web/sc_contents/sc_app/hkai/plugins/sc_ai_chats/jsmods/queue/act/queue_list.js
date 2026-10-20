// jsmods/queue/act/queue_list.js —— 等待列更新（act 層——一檔一事）
// 職責：queue_list（叫 queue_list 拎最新等待列 → 收到交 refresh_queue_list 替換）／refresh_queue_list（直接食 queue_list——整排替換；緊密相關）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _queue_list {
    constructor() {}

    // 等待列更新（叫 handler 拎最新等待列——收到 → 本機整排替換）
    async queue_list() {
        // 橋接駁（同域 req 橋——新方式）
        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_list_instance = await queue_instance.req().queue_list();

        const thisObject = this;

        return queue_list_instance.queue_list({
            success: function (result) {
                thisObject.refresh_queue_list(result.html.queue_list);
            },
            error: function (result) {
                console.error("queue_list failed", result);
            }
        });
    }

    // 等待列整排替換（直接食 queue_list——replaceWith）
    refresh_queue_list(queue_list) {
        // 判定：無 UI 內容（未帶）→ 不動作（唔清走容器）
        if (!queue_list) {
            return;
        }

        const j_queue_list = jQuery(".sc_composer__queue_list");

        // 判定：容器唔存在（未選專案）→ 不動作
        if (!j_queue_list.length) {
            return;
        }

        j_queue_list.replaceWith(queue_list);
    }
}

export const queue_list = (function (instance) {
    return function () {

        return instance;
    };
})(new _queue_list());
