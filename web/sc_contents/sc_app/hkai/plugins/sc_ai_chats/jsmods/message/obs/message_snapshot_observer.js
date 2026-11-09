// jsmods/message/obs/message_snapshot_observer.js —— workspace 線 message 事件 → 套用＋ACK
// 職責：訂閱 workspace_stream "message"（當前對話訊息快照＋version＋stream_id）→ 三段套用（增加／刪除／修正）→ 回 ACK
// 上游：message 域 bind_events（開域一次）
// 下游：registry().sc_stream().workspace().subscribe()；bridge POST /workspace_ack
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _message_snapshot_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;
    }

    // 登記（重複呼叫只登記一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const workspace_stream_instance = await registry().sc_stream().workspace();

        this.unsubscribe = workspace_stream_instance.subscribe("message", this.apply_snapshot.bind(this));

        return this;
    }

    // 收到完整快照（{stream_id, version, snapshot}）
    // 契約：後台推嚟嘅一定係最新 → 直接套用，唔比版本
    async apply_snapshot(event_payload) {
        const snapshot_payload = event_payload || {};
        const snapshot = snapshot_payload.snapshot || {};
        const snapshot_version = Number(snapshot_payload.version || 0);

        // *** : 步驟 1——先確認快照屬當前對話；換對話後舊線仲會推舊對話快照，照套就會誤刪／誤補
        const is_current_chat = await this._is_snapshot_for_current_chat();

        // 判定：唔屬當前對話 → 唔套用（只回 ACK，等新線推新對話快照）
        if (is_current_chat === false) {
            await this.send_ack(String(snapshot_payload.stream_id || ""), "message", snapshot_version);

            return;
        }

        this.apply_message_cards(snapshot);

        await this.send_ack(String(snapshot_payload.stream_id || ""), "message", snapshot_version);
    }

    // 快照係唔係屬於當前對話（比對連線 chat_sid 同 URL chat_sid）
    async _is_snapshot_for_current_chat() {
        const workspace_stream_instance = await registry().sc_stream().workspace();
        const stream_chat_sid = String(workspace_stream_instance.chat_sid || "");

        const chat_instance = await registry().sc_ai_chats().chat();
        const current_chat_sid = chat_instance.get_url_chat_sid();

        // 判定：連線未帶 chat_sid（專案根／歸檔列面）→ 唔當錯
        if (stream_chat_sid === "") {
            return true;
        }

        return stream_chat_sid === current_chat_sid;
    }

    // 套用快照——兩段：① 刪除 ② 修正
    // 為何冇「增加」：載入訊息屬主鍵職責（首次載入／換對話／換專案），快照觀察器只負責校正與拆除
    apply_message_cards(snapshot) {
        // *** : 步驟 1——先刪除（畫面有、文件冇）
        this.remove_message_cards(snapshot);

        // *** : 步驟 2——再修正（狀態／working 旗標）
        this.update_message_cards(snapshot);
    }

    // ② 刪除（畫面有、文件冇）——加守衛，避免換窗／未載入時把訊息全部拆走
    remove_message_cards(snapshot) {
        const viewport_chat_sid = String(jQuery(".sc_thread__viewport").attr("data-chat_sid") || "");

        // 判定：快照係空（後台未讀到／未載入）→ 唔准拆（留住舊卡，等下一個真快照）
        if (Object.keys(snapshot).length === 0) {
            return;
        }

        // 判定：訊息窗未完成載入 → 唔准拆
        if (String(jQuery(".sc_thread__viewport").attr("data-loaded") || "") !== "1") {
            return;
        }

        const current_url_chat_sid = (new URL(location.href).pathname.split("/")[2]) || "";

        // 判定：畫面主鍵同 URL 唔一致（DOM 仲係上一個對話）→ 唔准拆
        if (viewport_chat_sid !== current_url_chat_sid) {
            return;
        }

        const card_nodes = document.querySelectorAll(".sc_thread__viewport .message_wrapper");

        // 逐張卡核對（文件已經冇 → 拆；樂觀泡泡唔算）
        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];

            // 判定：樂觀泡泡 → 跳過（本地未確認）
            if (String(card_node.getAttribute("data-optimistic") || "") !== "") {
                continue;
            }

            const message_id = String(card_node.getAttribute("data-message_id") || "");

            // 判定：冇身份 → 跳過
            if (message_id === "") {
                continue;
            }

            // 判定：文件仍有 → 保留
            if (snapshot[message_id] !== undefined) {
                continue;
            }

            card_node.remove();
        }
    }

    // ③ 修正（兩邊都有）——狀態／working 旗標
    update_message_cards(snapshot) {
        const message_id_list = Object.keys(snapshot);

        for (let message_index = 0; message_index < message_id_list.length; message_index++) {
            const message_id = message_id_list[message_index];
            const message_entry = snapshot[message_id] || {};
            const message_status = String(message_entry.status || "");
            const j_message_card = jQuery(".sc_thread__viewport .message_wrapper[data-message_id='" + message_id + "']");

            // 判定：卡唔喺畫面 → 跳過
            if (j_message_card.length === 0) {
                continue;
            }

            // 判定：已有樂觀泡泡（本地未確認）→ 唔覆寫
            if (String(j_message_card.attr("data-optimistic") || "") !== "") {
                continue;
            }

            // 判定：有狀態 → 落 data-status
            if (message_status !== "") {
                j_message_card.attr("data-status", message_status);
            }

            // 判定：終態 → 清 working 旗標
            if (message_status === "completed" || message_status === "failed" || message_status === "cancelled" || message_status === "aborted") {
                j_message_card.attr("data-working", "0");

                continue;
            }

            // 判定：非終態且有狀態 → 當工作中
            if (message_status !== "") {
                j_message_card.attr("data-working", "1");
            }
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

export const message_snapshot_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_snapshot_observer());
