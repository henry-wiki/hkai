// jsmods/message/obs/message_polling_observer.js —— workspace 線 polling 事件 → UI 對賬（開關）
// 職責：訂閱 workspace_stream "polling"（{stream_id, version, snapshot}）→ 按後端事實校正專案卡／chat 卡標記、當前對話 working 旗標、主按鈕兩態 → 回 ACK
// 上游：message 域 bind_events（開域一次）
// 下游：registry().sc_stream().workspace().subscribe()；prompt act prompt_send_button_state；bridge POST /workspace_ack
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _message_polling_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;

        // 當前對話上次見到嘅 polling 數（變咗＝後台推咗隊頭 → 重拉等待列 UI）
        this.last_chat_poll_count = -1;
    }

    // 登記（重複呼叫只登記一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const workspace_stream_instance = await registry().sc_stream().workspace();

        this.unsubscribe = workspace_stream_instance.subscribe("polling", this.apply_polling_event.bind(this));

        return this;
    }

    // 收到 polling 事件（{stream_id, version, snapshot:{projects, chats}}——同其他快照同一套 ACK）
    async apply_polling_event(event_payload) {
        const polling_event = event_payload || {};
        const snapshot_payload = polling_event.snapshot || {};
        const snapshot_version = Number(polling_event.version || 0);

        // 第一步：直接套用（後台推嚟嘅一定係最新；套用本身冪等）
        await this.apply_polling_snapshot(snapshot_payload);

        // 第二步：回 ACK（後台據此停止重推）
        await this.send_ack(String(polling_event.stream_id || ""), "polling", snapshot_version);
    }

    // 套用 polling 現況（後端事實：專案內每 chat 仍處理中嘅數目）
    async apply_polling_snapshot(polling_payload) {
        const project_poll_counts = polling_payload.projects || {};
        const chat_poll_counts = polling_payload.chats || {};

        // 第一步：專案卡＋chat 卡標記按後端事實校正（全站範圍）
        this.refresh_project_cards(project_poll_counts);
        this.refresh_chat_cards(chat_poll_counts);

        const chat_instance = await registry().sc_ai_chats().chat();
        const current_chat_sid = String(chat_instance.get_chat_sid() || "");
        const current_chat_entry = chat_poll_counts[current_chat_sid] || {};
        const current_chat_poll_count = Number(current_chat_entry.polling_counts || 0);

        // 第二步：本對話已冇 polling → 清走殘留 working 旗標（解決「回應完結但唔熄」）
        if (current_chat_poll_count === 0) {
            jQuery(".sc_thread__viewport .message_wrapper[data-working='1']").attr("data-working", "0");
        }

        // 第三步：本對話 polling 數有變（後台推咗隊頭）→ 重拉等待列 UI（純讀，唔派工）
        if (current_chat_poll_count !== this.last_chat_poll_count) {
            this.last_chat_poll_count = current_chat_poll_count;

            const queue_instance = await registry().sc_ai_chats().queue();
            const queue_list_instance = await queue_instance.act().queue_list();

            await queue_list_instance.queue_list();
        }

        // 第四步：主按鈕兩態交唯一決策者重算（唔自己寫判斷）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_state_instance = await prompt_instance.act().prompt_send_button_state();

        prompt_send_button_state_instance.refresh();
    }

    // 清單重繪之後重套標記（DOM 換走晒，唔會再有 polling 事件推過嚟）
    async reapply_from_last() {
        const workspace_stream_instance = await registry().sc_stream().workspace();
        const last_payloads = workspace_stream_instance.last_payloads || {};
        const last_payload = last_payloads["polling"] || null;

        // 判定：未收過 polling 事件 → 冇嘢可套
        if (last_payload === null) {
            return;
        }

        const snapshot_payload = last_payload.snapshot || {};

        this.refresh_project_cards(snapshot_payload.projects || {});
        this.refresh_chat_cards(snapshot_payload.chats || {});
    }

    // 專案卡：全量比對（全站計數；冇數＝清標記）
    refresh_project_cards(project_poll_counts) {
        const j_project_cards = jQuery(".sc_project_card");

        for (let card_index = 0; card_index < j_project_cards.length; card_index++) {
            const j_project_card = jQuery(j_project_cards[card_index]);
            const card_project_slug = String(j_project_card.attr("data-project_slug") || "");
            const project_entry = project_poll_counts[card_project_slug] || {};
            const project_poll_count = Number(project_entry.polling_counts || 0);

            // 判定：後端話仲有處理中 → 加標記
            if (project_poll_count > 0) {
                j_project_card.addClass("polling");

                continue;
            }

            j_project_card.removeClass("polling");
        }
    }

    // chat 卡：全量比對（有數＝polling；唔喺 map＝清標記）
    refresh_chat_cards(chat_poll_counts) {
        const j_chat_cards = jQuery(".sc_chat_card");

        for (let card_index = 0; card_index < j_chat_cards.length; card_index++) {
            const j_chat_card = jQuery(j_chat_cards[card_index]);
            const card_chat_sid = String(j_chat_card.attr("data-chat_sid") || "");
            const chat_entry = chat_poll_counts[card_chat_sid] || {};
            const chat_poll_count = Number(chat_entry.polling_counts || 0);

            // 判定：後端話仲有處理中 → 加標記
            if (chat_poll_count > 0) {
                j_chat_card.addClass("polling");

                continue;
            }

            // 否則 → 除標記（解決「已經冇做但標記唔熄」）
            j_chat_card.removeClass("polling");
        }
    }

    // 回 ACK（POST 去 bridge；失敗唔理——後台會重推）
    async send_ack(stream_id, event_name, version) {
        // 判定：冇 stream_id → 唔送
        if (stream_id === "") {
            return;
        }

        const stream_config = registry().sc_stream().get_config();
        const bridge_base_url = stream_config.base_url || "";

        // 判定：冇 bridge base_url → 唔送
        if (bridge_base_url === "") {
            return;
        }

        const ack_payload = {};
        ack_payload.stream_id = stream_id;
        ack_payload.event = event_name;
        ack_payload.version = version;

        // UI 現況一齊報上（後台比對「文件 vs 畫面」——收斂迴圈靠佢）
        const observed_stream_instance = await registry().sc_stream().workspace();

        ack_payload.observed = await observed_stream_instance.collect_observed(event_name);

        // 判定：ACK 送出失敗 → 靜默（後台重推機制會補）
        try {
            await fetch(bridge_base_url + "/workspace_ack", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(ack_payload),
            });
        } catch (ack_error) {
            return;
        }
    }
}

export const message_polling_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_polling_observer());
