// jsmods/project/obs/project_snapshot_observer.js —— workspace 線 project_snapshot → 套用＋ACK
// 職責：訂閱 workspace_stream "project"（完整快照＋version＋stream_id）→ 三段套用（增加／刪除／修正）＋排位 → 回 ACK
// 上游：project 域 bind_events（開域一次）
// 下游：registry().sc_stream().workspace().subscribe()；bridge POST /workspace_ack
// 結構：eager 單例 closure factory（域接駁動態 import 解構後呼叫 init）

class _project_snapshot_observer {
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

        this.unsubscribe = workspace_stream_instance.subscribe("project", this.apply_snapshot.bind(this));

        return this;
    }

    // 收到完整快照（{stream_id, version, snapshot}）
    // 契約：後台推嚟嘅一定係最新 → 直接套用，唔比版本（重推／換線都照套；套用本身冪等）
    async apply_snapshot(event_payload) {
        const snapshot_payload = event_payload || {};
        const snapshot = snapshot_payload.snapshot || {};
        const snapshot_version = Number(snapshot_payload.version || 0);

        await this.apply_project_cards(snapshot);

        await this.send_ack(String(snapshot_payload.stream_id || ""), "project", snapshot_version);
    }

    // 套用快照——三段分開：① 增加 ② 刪除 ③ 修正（之後交 project 域排位）
    async apply_project_cards(snapshot) {
        // *** : 步驟 1——先增加；補齊缺失，後面兩段就唔會撞到新卡
        await this.add_project_cards(snapshot);

        // *** : 步驟 2——再刪除；只保留文件仍然有嘅卡
        this.remove_project_cards(snapshot);

        // *** : 步驟 3——最後修正；卡已齊，逐張覆寫欄位
        this.update_project_cards(snapshot);

        // *** : 步驟 4——排位（按 data-update_time；交 project 域既有動作）
        const project_instance = await registry().sc_ai_projects().project();
        const project_list_rank_refresh_instance = await project_instance.act().project_list_rank_refresh();

        project_list_rank_refresh_instance.project_list_rank_refresh();
    }

    // 卡片身份（歸檔卡帶 archive@<slug> 前綴——文件用純 slug）
    strip_context(identity_text) {
        const raw_text = String(identity_text || "");

        // 判定：冇前綴 → 原值
        if (raw_text.indexOf("@") === -1) {
            return raw_text;
        }

        return raw_text.slice(raw_text.indexOf("@") + 1);
    }

    // 找卡（兩種身份形：純 slug／歸檔 archive@<slug>）
    _find_project_card(project_slug) {
        const j_plain_card = jQuery(".sc_project_card[data-project_slug='" + project_slug + "']");

        // 判定：純形搵到 → 回
        if (j_plain_card.length > 0) {
            return j_plain_card;
        }

        return jQuery(".sc_project_card[data-project_slug='archive@" + project_slug + "']");
    }

    // ① 增加（文件有、畫面冇）——逐張向後端取卡 HTML 再尾插
    async add_project_cards(snapshot) {
        const project_slug_list = Object.keys(snapshot);
        const project_instance = await registry().sc_ai_projects().project();
        const project_item_insert_instance = await project_instance.act().project_item_insert();

        for (let project_index = 0; project_index < project_slug_list.length; project_index++) {
            const project_slug = project_slug_list[project_index];

            // 判定：卡已在 → 交「修正」段處理
            if (this._find_project_card(project_slug).length > 0) {
                continue;
            }

            await project_item_insert_instance.project_item_insert(project_slug);
        }
    }

    // ② 刪除（畫面有、文件冇）
    remove_project_cards(snapshot) {
        const card_nodes = document.querySelectorAll(".sc_project_list .sc_project_card");

        // 逐張卡核對（文件已經冇 → 拆）
        for (let card_index = 0; card_index < card_nodes.length; card_index++) {
            const card_node = card_nodes[card_index];
            const card_project_slug = this.strip_context(card_node.getAttribute("data-project_slug"));

            // 判定：冇身份 → 跳過
            if (card_project_slug === "") {
                continue;
            }

            // 判定：文件仍有 → 保留
            if (snapshot[card_project_slug] !== undefined) {
                continue;
            }

            card_node.remove();
        }
    }

    // ③ 修正（兩邊都有）——覆寫卡面欄位
    update_project_cards(snapshot) {
        const project_slug_list = Object.keys(snapshot);

        for (let project_index = 0; project_index < project_slug_list.length; project_index++) {
            const project_slug = project_slug_list[project_index];
            const project_entry = snapshot[project_slug] || {};
            const j_project_card = this._find_project_card(project_slug);

            // 判定：卡唔在 → 跳過
            if (j_project_card.length === 0) {
                continue;
            }

            const project_name = String(project_entry.name || "");
            const project_model = String(project_entry.model || "");
            const project_chat_count = Number(project_entry.chat_count || 0);
            const project_updated_at = String(project_entry.updated_at || "");

            // 卡面數量（chat_count）
            j_project_card.find(".sc_project_card__chat_count").attr("data-count", String(project_chat_count));
            j_project_card.find(".sc_project_card__chat_count b").text(String(project_chat_count));

            // 判定：有名稱 → 更新卡面名（Home 卡有 icon，唔覆寫）
            if (project_name !== "" && project_slug !== "home") {
                j_project_card.find(".sc_project_card__name").text(project_name);
            }

            // 判定：有模型 → 更新卡面模型（title 同步，保持一致）
            if (project_model !== "") {
                j_project_card.find(".sc_project_card__model").text(project_model);
                j_project_card.find(".sc_project_card__model").attr("title", project_model);
            }

            // 判定：有更新時間 → 落 attr（畫面文字由清單渲染負責）
            if (project_updated_at !== "") {
                j_project_card.attr("data-update_time", project_updated_at);
            }
        }
    }

    // 回 ACK（POST 去 bridge；失敗唔理——後台會重推）
    async send_ack(stream_id, event_name, version) {
        // 判定：冇 stream_id（舊 payload／線未開）→ 唔送
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

export const project_snapshot_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _project_snapshot_observer());
