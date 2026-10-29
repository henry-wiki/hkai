// jsmods/message/act/message_stream.js —— 串流開關（act 層——一檔一事）
// 職責：open(project_slug, message_id, response_index)——砌 URL、開 SSE；stop()——關連線
//       SSE 事件（delta／status／reset）→ 本檔直接叫 message_poll（渲染唯一入口）
// 上游：prompt act_prompt_send（送出後）／message act message_poll（工作中訊息）；下游：sc_stream start／stop、message act message_poll
// 結構同本域家族：export const + eager 單例 closure factory（動態 import——零 .default）

class _message_stream {
    constructor() {}

    // 開串流（回 bool＝有冇開；未啟用／冇 base_url／message_id 無效 → false）
    async open(project_slug, message_id, response_index) {
        const stream_config = registry().sc_stream().get_config();

        // 判定：未啟用串流 → 唔開
        if (stream_config.enabled !== true) {
            return false;
        }

        const stream_base_url = stream_config.base_url || "";

        // 判定：冇 base_url → 唔開
        if (stream_base_url === "") {
            return false;
        }

        // 判定：message_id 無效 → 唔開
        if (message_id < 1) {
            return false;
        }

        const stream_url = stream_base_url
            + "/stream?project_slug=" + encodeURIComponent(project_slug)
            + "&message_id=" + encodeURIComponent(message_id)
            + "&response_index=" + encodeURIComponent(response_index);

        const message_instance_stream = await registry().sc_ai_chats().message();
        const stream_singleton = await message_instance_stream.act().message_stream_singleton();

        return stream_singleton.start(stream_url, ["delta", "status", "reset"], this._handle_change.bind(this));
    }

    // 關串流（未有連線＝no-op）
    async stop() {
        const message_instance_stream = await registry().sc_ai_chats().message();
        const stream_singleton = await message_instance_stream.act().message_stream_singleton();

        stream_singleton.stop();
    }

    // SSE 有「產出中」訊號（delta／status／reset）→ 叫 poll（已跑＝即時補一輪；未跑＝重啟）
    async _handle_change() {
        const message_instance_poll = await registry().sc_ai_chats().message();
        const message_poll_instance = await message_instance_poll.act().message_poll();

        // 判定：poll 已在跑 → 即時補一輪（唔重入）
        if (message_poll_instance.running === true) {
            message_poll_instance.poll_soon();

            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();
        const poll_chat_sid = chat_instance.get_chat_sid();

        message_poll_instance.message_poll(poll_chat_sid);
    }
}

export const message_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_stream());
