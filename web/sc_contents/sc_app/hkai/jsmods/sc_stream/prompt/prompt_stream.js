// jsmods/sc_stream/prompt/prompt_stream.js —— 草稿專用 SSE 連線（單例）
// 職責：open(project_slug, chat_sid, view_id)——開 /prompt_stream/；收到 prompt event → 只處理本頁對話草稿；close()——關線
// 上游：jsmods/bootstrap.js（開頁開線）＋ plugins/sc_ai_chats/jsmods/sc_ai_chats.js（換對話重開）
// 下游：stream/act/act_prompt_updater（還原 composer）

class _prompt_stream {
    constructor() {
        // 連線與網址（唯一出口——狀態由 open／close 管理）
        this.event_source = null;
        this.stream_url = "/request/hkai/prompt_stream/";

        // 開線令牌（open 期間再 open／close → 舊連線作廢）
        this.stream_token = 0;

        // 已啟動旗標（事件 handler 先檢查——close 後殘留事件唔處理）
        this.enabled = false;
    }

    // 開線（URL 由本模組砌——caller 只交三個身份值）
    open(project_slug, chat_sid, view_id) {
        const thisObject = this;

        thisObject.close();

        // 判定：瀏覽器唔支援 EventSource → 唔開
        if (typeof EventSource === "undefined") {
            return;
        }

        thisObject.stream_token = thisObject.stream_token + 1;
        const open_token = thisObject.stream_token;

        thisObject.enabled = true;

        const stream_url = thisObject.stream_url
            + "?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid)
            + "&view_id=" + encodeURIComponent(view_id);

        const event_source = new EventSource(stream_url);

        // open（handshake——冇資料要處理）
        event_source.addEventListener("open", function () {
        });

        // close（伺服器主動收線——EventSource 會自動重連）
        event_source.addEventListener("close", function () {
        });

        // 收到 prompt → 交 apply_change_event（只認本頁草稿）
        event_source.addEventListener("prompt", async function (event) {
            // 判定：已關線 → 唔處理
            if (thisObject.enabled === false) {
                return;
            }

            // 判定：open 期間已被取代 → 唔處理
            if (thisObject.stream_token !== open_token) {
                return;
            }

            let prompt_change_event = null;

            // 判定：解析失敗 → 跳過
            try {
                prompt_change_event = JSON.parse(event.data);
            } catch (parse_error) {
                return;
            }

            await thisObject.apply_change_event(prompt_change_event, project_slug, chat_sid);
        });

        thisObject.event_source = event_source;
    }

    // 套用 prompt 變更（兩層：project_slug → chat_sid；只處理本頁對話）
    async apply_change_event(change_event, project_slug, chat_sid) {
        const change_detail = change_event.prompt || null;

        // 判定：冇 prompt 段 → 唔動作
        if (change_detail === null) {
            return;
        }

        const item_changes = change_detail.changes || {};
        const project_prompt_changes = item_changes[project_slug] || {};
        const prompt_change_items = project_prompt_changes[chat_sid];

        // 判定：本頁草稿冇變 → 唔動作
        if (prompt_change_items === undefined) {
            return;
        }

        const stream_instance = await registry().sc_state_stream().stream();
        const prompt_updater_instance = await stream_instance.act().act_prompt_updater();

        const writer_view_id = String(change_detail.view_id || "");

        await prompt_updater_instance.apply_prompt_stream_delta(prompt_change_items, writer_view_id);
    }

    // 關線（停／重開前先清）
    close() {
        this.enabled = false;
        this.stream_token = this.stream_token + 1;

        // 判定：有連線 → 關閉
        if (this.event_source !== null) {
            this.event_source.close();
            this.event_source = null;
        }
    }
}

export const prompt_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_stream());
