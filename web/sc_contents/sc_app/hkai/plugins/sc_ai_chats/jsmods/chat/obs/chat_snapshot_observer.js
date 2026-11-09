// jsmods/chat/obs/chat_snapshot_observer.js —— workspace 線 chat 事件 → 套用＋ACK
// 職責：訂閱 workspace_stream "chat"（完整快照＋version＋stream_id）→ 三段套用（增加／刪除／修正）→ 回 ACK（帶畫面現況）
// 上游：chat 域 bind_events（開域一次）
// 下游：registry().sc_stream().workspace().subscribe()；bridge POST /workspace_ack
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _chat_snapshot_observer {
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

        this.unsubscribe = workspace_stream_instance.subscribe("chat", this.apply_snapshot.bind(this));

        return this;
    }

    // 收到完整快照（{stream_id, version, snapshot}）
    // 契約：後台推嚟嘅一定係最新 → 直接套用，唔比版本（重推／換線都照套；套用本身冪等）
    async apply_snapshot(event_payload) {
        const snapshot_payload = event_payload || {};
        const snapshot = snapshot_payload.snapshot || {};
        const snapshot_version = Number(snapshot_payload.version || 0);

        await this.apply_chat_cards(snapshot);

        await this.send_ack(String(snapshot_payload.stream_id || ""), "chat", snapshot_version);
    }

    // 卡片身份（歸檔卡帶 archive@<sid> 前綴——文件用純 sid，要剝）
    strip_context(identity_text) {
        const raw_text = String(identity_text || "");

        // 判定：冇前綴 → 原值
        if (raw_text.indexOf("@") === -1) {
            return raw_text;
        }

        return raw_text.slice(raw_text.indexOf("@") + 1);
    }

    // 找卡（兩種身份形：純 sid／歸檔 archive@<sid>）
    _find_chat_card(chat_sid) {
        const j_plain_card = jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + chat_sid + "']");

        // 判定：純形搵到 → 回
        if (j_plain_card.length > 0) {
            return j_plain_card;
        }

        return jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='archive@" + chat_sid + "']");
    }

    // 套用快照——三段分開（一眼要見到三組，各做一件事）
    //   ① 增加：文件有、畫面冇 → 補卡
    //   ② 刪除：畫面有、文件冇（或語境唔啱）→ 拆卡
    //   ③ 修正：兩邊都有 → 覆寫欄位
    async apply_chat_cards(snapshot) {
        // 標準取法：專案 slug 交 project 域；清單語境交 chat 域（唔自己解 URL）
        const project_instance = await registry().sc_ai_projects().project();
        const chat_instance = await registry().sc_ai_chats().chat();

        const current_project_slug = project_instance.get_project_slug();
        const current_context = chat_instance.get_chat_list_context_from_url(location.href);

        // *** : 步驟 1——先增加；補齊缺失，後面兩段就唔會撞到新卡
        await this.add_chat_cards(snapshot, current_project_slug, current_context);

        // *** : 步驟 2——再刪除；只保留「文件仍然有＋語境相符」嘅卡
        this.remove_chat_cards(snapshot, current_context);

        // *** : 步驟 3——最後修正；卡已齊，逐張覆寫欄位
        this.update_chat_cards(snapshot, current_context);
    }

    // ① 增加（文件有、畫面冇）——只補本專案＋語境相符嘅卡
    async add_chat_cards(snapshot, current_project_slug, current_context) {
        const chat_sid_list = Object.keys(snapshot);

        for (let chat_index = 0; chat_index < chat_sid_list.length; chat_index++) {
            const chat_sid = chat_sid_list[chat_index];
            const chat_entry = snapshot[chat_sid] || {};

            // 判定：卡已在 → 交「修正」段處理
            if (this._find_chat_card(chat_sid).length > 0) {
                continue;
            }

            // 判定：唔屬本專案 → 唔補（清單只顯示當前專案）
            if (String(chat_entry.project_slug || "") !== current_project_slug) {
                continue;
            }

            // 判定：語境唔夾 → 唔補
            if (this.is_context_mismatch(chat_entry, current_context) === true) {
                continue;
            }

            await this.insert_chat_card(chat_sid);
        }
    }

    // ② 刪除（畫面有、文件冇／語境唔啱）
    remove_chat_cards(snapshot, current_context) {
        const card_nodes = document.querySelectorAll(".sc_chat_list .sc_chat_card");

        // 逐張卡核對（唔屬當前清單嘅一律拆走）
        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];
            const card_chat_sid = this.strip_context(card_node.getAttribute("data-chat_sid"));

            // 判定：冇身份 → 跳過
            if (card_chat_sid === "") {
                continue;
            }

            const chat_entry = snapshot[card_chat_sid];

            // 判定：文件已經冇呢個對話 → 拆卡
            if (chat_entry === undefined) {
                card_node.remove();
                continue;
            }

            // 判定：語境唔夾（例如已歸檔但仍喺普通列面）→ 拆卡
            if (this.is_context_mismatch(chat_entry, current_context) === true) {
                card_node.remove();
            }
        }
    }

    // ③ 修正（兩邊都有）——覆寫卡面欄位
    update_chat_cards(snapshot, current_context) {
        const chat_sid_list = Object.keys(snapshot);

        for (let chat_index = 0; chat_index < chat_sid_list.length; chat_index++) {
            const chat_sid = chat_sid_list[chat_index];
            const chat_entry = snapshot[chat_sid] || {};

            const j_chat_card = this._find_chat_card(chat_sid);

            // 判定：卡唔在（補卡失敗／唔屬本專案）→ 跳過
            if (j_chat_card.length === 0) {
                continue;
            }

            // 判定：語境唔夾 → 交「刪除」段處理
            if (this.is_context_mismatch(chat_entry, current_context) === true) {
                continue;
            }

            this.apply_chat_card_fields(j_chat_card, chat_entry);
        }
    }

    // 語境唔夾（歸檔卡只喺歸檔列面；普通卡只喺普通列面）
    is_context_mismatch(chat_entry, current_context) {
        const chat_status = String(chat_entry.status || "");
        const is_archived = (chat_status === "archived");

        // 判定：歸檔卡但而家係普通列面 → 唔夾
        if (is_archived === true && current_context !== "archive") {
            return true;
        }

        // 判定：普通卡但而家係歸檔列面 → 唔夾
        if (is_archived === false && current_context === "archive") {
            return true;
        }

        return false;
    }

    // 修正卡面欄位（title／msg_count／updated_at＋相對時間）
    apply_chat_card_fields(j_chat_card, chat_entry) {
        // 判定：冇卡 → 唔做
        if (j_chat_card.length === 0) {
            return;
        }

        const chat_title = String(chat_entry.title || "");
        const chat_msg_count = Number(chat_entry.msg_count || 0);
        const chat_updated_at = String(chat_entry.updated_at || "");

        // 判定：有標題 → 更新卡面標題（title 同步）
        if (chat_title !== "") {
            j_chat_card.find(".sc_chat_card__title").text(chat_title);
            j_chat_card.find(".sc_chat_card__title").attr("title", chat_title);
        }

        j_chat_card.find(".sc_chat_card__msg_count").attr("data-count", String(chat_msg_count));
        j_chat_card.find(".sc_chat_card__msg_count b").text(String(chat_msg_count));

        // 判定：有更新時間 → 落 attr ＋ 重寫相對時間文字
        if (chat_updated_at !== "") {
            j_chat_card.attr("data-update_time", chat_updated_at);
            j_chat_card.find(".sc_chat_card__updated").text(registry().shared().sc_web_utils().get_format_relative_time(chat_updated_at));
        }
    }

    // 取單張卡 HTML → 尾插（位置交 rank 升位）
    async insert_chat_card(chat_sid) {
        const chat_instance = await registry().sc_ai_chats().chat();
        const chat_item_fetch_instance = await chat_instance.act().chat_item_fetch();

        await chat_item_fetch_instance.chat_item_fetch(chat_sid, {
            success: function (response_data) {
                const result_html = response_data.html || {};
                const chat_item_html = String(result_html.chat_item || "");

                // 判定：後端冇交 HTML → 唔插
                if (chat_item_html === "") {
                    return;
                }

                const j_new_card = jQuery(chat_item_html);
                const new_chat_sid = String(j_new_card.attr("data-chat_sid") || "");

                // 判定：等待期間已由別路補上 → 唔重複插
                if (jQuery(".sc_chat_list .sc_chat_card[data-chat_sid='" + new_chat_sid + "']").length > 0) {
                    return;
                }

                jQuery(".sc_chat_list").append(j_new_card);
            },
            error: function () {
                // 失敗 → 唔補（後台收斂迴圈會再推）
                return;
            }
        });
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

export const chat_snapshot_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _chat_snapshot_observer());
