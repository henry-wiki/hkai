// jsmods/message/act/message_stream.js —— 訊息串流（act 層——一檔一事）
// 職責：開／關 SSE；按事件（ready／turn／append／state／done）直接貼文字上畫面
// 上游：頁面載入（回應中卡片）／prompt act_prompt_send（送出成功）
// 下游：sc_stream start／stop；turn／done 交 message_poll.render_once 補骨架同收尾

import { sc_console } from "../../../../../jsmods/sc_console/sc_console.js";

class _message_stream {
    constructor() {
        // 本串流 identity（開線時記——URL 只用 identity，message_id 只作畫面綁定）
        this.project_slug = "";
        this.chat_sid = "";
        this.prompt_id = "";
        this.message_id = 0;

        // 各段全文緩衝（append 累加——餵 writer 用）
        this.seg_texts = {};

        // 已開線嘅 prompt（防重開）
        this.opened_prompt_ids = {};

        // 換輪中（turn 事件處理期間——append 先入緩衝，完成後才寫）
        this.turn_switching = false;

        // 換輪期間暫存嘅 append
        this.pending_appends = [];

        // 已套用嘅活動日誌（uid → true；防重複累加）
        this.applied_activity_uids = {};
    }

    // 頁面載入：逐條「回應中」卡片開線（data-working="1"；index 由 PHP 帶）
    async start_for_page() {
        const j_working_panels = jQuery(".message_wrapper[data-working='1']");

        sc_console("message", "stream").log("頁面載入——待開線卡片", j_working_panels.length);

        for (let panel_index = 0; panel_index < j_working_panels.length; panel_index++) {
            const j_message_panel = j_working_panels.eq(panel_index);

            const panel_message_id = Number(j_message_panel.attr("data-message_id") || 0);
            const panel_response_index = Number(j_message_panel.attr("data-response_index") || 0);

            // 判定：冇 message_id → 唔開
            if (panel_message_id < 1) {
                continue;
            }

            await this.open_for_panel(panel_message_id, panel_response_index);
        }

        await this._dispatch_queue_if_any();
    }

    // 載入時有排隊項目 → 派工（舊 message_poll_detect 行為——唔另立邏輯）
    async _dispatch_queue_if_any() {
        const j_queue_items = jQuery(".sc_composer__queue_list .queue_item");

        // 判定：冇排隊 → 唔做
        if (j_queue_items.length === 0) {
            return;
        }

        const chat_instance = await registry().sc_ai_chats().chat();

        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

        await queue_dispatch_instance.queue_dispatch(chat_instance.get_chat_sid());
    }

    // 開串流（回 bool＝有冇開；identity＝project_slug／chat_sid／prompt_id）
    async open(project_slug, chat_sid, prompt_id, response_index) {
        const stream_config = registry().sc_stream().get_config();

        sc_console("message", "stream").log("open 請求", project_slug, chat_sid, prompt_id, response_index);

        // 判定：未啟用串流 → 唔開
        if (stream_config.enabled !== true) {
            return false;
        }

        const stream_base_url = stream_config.base_url || "";

        // 判定：冇 base_url → 唔開
        if (stream_base_url === "") {
            return false;
        }

        // 判定：identity 不齊 → 唔開
        if (project_slug === "" || chat_sid === "" || prompt_id === "") {
            return false;
        }

        const stream_url = stream_base_url
            + "/bridge_sse_for_message?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid)
            + "&prompt_id=" + encodeURIComponent(prompt_id)
            + "&response_index=" + encodeURIComponent(response_index);

        this.project_slug = project_slug;
        this.chat_sid = chat_sid;
        this.prompt_id = prompt_id;
        this.seg_texts = {};
        this.opened_prompt_ids[String(prompt_id)] = true;
        this.applied_activity_uids = {};

        const message_instance_stream = await registry().sc_ai_chats().message();
        const stream_singleton = await message_instance_stream.act().message_stream_singleton();

        sc_console("message", "stream").log("URL", stream_url);

        return stream_singleton.start(
            stream_url,
            ["ready", "append", "turn", "state", "log", "done"],
            this._handle_event.bind(this),
        );
    }

    // 由卡片資訊開線（identity 由 URL＋卡片 data-prompt_id 取——零域依賴）
    async open_for_panel(message_id, response_index) {
        const path_segments = window.location.pathname.split("/").filter(function (path_segment) { return path_segment !== ""; });

        let project_slug = "";
        let chat_sid = "";

        // 判定：有第一段 → 用佢做 project_slug
        if (path_segments.length > 0) {
            project_slug = path_segments[0];
        }

        // 判定：有第二段 → 用佢做 chat_sid
        if (path_segments.length > 1) {
            chat_sid = path_segments[1];
        }

        const j_message_panel = jQuery(".message_wrapper[data-message_id='" + message_id + "']").first();
        const panel_prompt_id = String(j_message_panel.attr("data-prompt_id") || "");

        // 判定：冇 prompt_id → 唔開
        if (panel_prompt_id === "") {
            return false;
        }

        return this.open(project_slug, chat_sid, panel_prompt_id, response_index);
    }

    // 關串流（清本條 identity／緩衝——切換對話或專案時舊事件一律作廢）
    async stop() {
        const message_instance_stream = await registry().sc_ai_chats().message();
        const stream_singleton = await message_instance_stream.act().message_stream_singleton();

        stream_singleton.stop();

        this.project_slug = "";
        this.chat_sid = "";
        this.prompt_id = "";
        this.message_id = 0;
        this.seg_texts = {};
        this.opened_prompt_ids = {};
        this.pending_appends = [];
        this.applied_activity_uids = {};
        this.turn_switching = false;
    }

    // 事件入口（名＋內容由 sc_stream 傳入）
    async _handle_event(event_name, event_payload) {
        const payload = event_payload || {};

        sc_console("message", "stream").log("事件", event_name, payload);

        // 判定：ready → 貼上當刻全文
        if (event_name === "ready") {
            await this._apply_ready(payload);

            return;
        }

        // 判定：append → 續貼增量
        if (event_name === "append") {
            await this._apply_append(payload);

            return;
        }

        // 判定：turn → 換輪（交 poll 單次渲染補新段骨架）
        if (event_name === "turn") {
            await this._apply_turn(payload);

            return;
        }

        // 判定：state → 同步卡片狀態
        if (event_name === "state") {
            this._apply_state(payload);

            return;
        }

        // 判定：log → 活動日誌單行累加
        if (event_name === "log") {
            this._apply_log(payload);

            return;
        }

        // 判定：done → 最後一次完整渲染再收線
        if (event_name === "done") {
            await this._apply_done(payload);
        }
    }

    // 取卡片
    _get_panel() {
        return jQuery(".message_wrapper[data-message_id='" + this.message_id + "']").first();
    }

    // 取段（kind＝thinking／result；冇就照骨架造一個）
    _get_segment(response_kind) {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 冇段
        if (j_message_panel.length === 0) {
            return jQuery();
        }

        const j_agent_message = j_message_panel.find(".agent_message").first();

        let j_segment = j_agent_message.find(".seg--" + response_kind).last();

        // 判定：未有段 → 造骨架（同後端 seg 結構一致；data-text＝writer 定位）
        if (j_segment.length === 0) {
            const segment_html = "<div class='seg seg--" + response_kind + "' data-response_id=''>"
                + "<div class='seg__body'>"
                + "<div class='seg__scroll noscroll'>"
                + "<div class='seg__text' data-text='" + response_kind + "'></div>"
                + "</div>"
                + "</div>"
                + "</div>";

            j_agent_message.append(segment_html);

            j_segment = j_agent_message.find(".seg--" + response_kind).last();
        }

        return j_segment;
    }

    // ready：貼全文（餵 writer——自動接續印）
    async _apply_ready(payload) {
        this.message_id = Number(payload.message_id || this.message_id);

        this._apply_state({ status: payload.status });

        await this._write_segment_text("thinking", String(payload.thinking || ""));
        await this._write_segment_text("result", String(payload.result || ""));

        // 由 SSE 重建活動日誌（避免同後端初次 render 重複疊加）
        const ready_log_entries = payload.activity_log || [];

        // 判定：帶咗完整日誌 → 清空現有行再重建
        if (ready_log_entries.length > 0) {
            const j_ready_panel = this._get_panel();

            // 判定：卡片在場 → 清走舊行
            if (j_ready_panel.length > 0) {
                j_ready_panel.find(".activity_lines").empty();
            }

            this.applied_activity_uids = {};
        }

        this._apply_log({ activity_log: ready_log_entries });
    }

    // log：活動日誌——同一行文字累加（只顯示最新結果）
    _apply_log(payload) {
        const log_entries = payload.activity_log || [];

        // 判定：冇日誌 → 唔做
        if (log_entries.length === 0) {
            return;
        }

        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const j_activity_lines = j_message_panel.find(".activity_lines");

        // 判定：冇日誌容器 → 唔做
        if (j_activity_lines.length === 0) {
            return;
        }

        for (let log_index = 0; log_index < log_entries.length; log_index++) {
            const log_entry = log_entries[log_index];
            const log_uid = String(log_entry.activity_uid || "");

            // 判定：已套用過 → 跳過
            if (this.applied_activity_uids[log_uid] === true) {
                continue;
            }

            this.applied_activity_uids[log_uid] = true;

            // 每條記錄一行（保留自己嘅階段——唔合併）
            const j_new_line = jQuery("<div class='activity_line'></div>");

            j_new_line.attr("data-activity_uid", log_uid);
            j_new_line.attr("data-log_event", log_entry.event || "");
            j_new_line.attr("data-log_status", log_entry.level || "");
            j_new_line.attr("class", "activity_line activity_level_" + (log_entry.level || "info"));
            j_new_line.text(log_entry.message || "");
            j_activity_lines.append(j_new_line);
        }
    }

    // append：增量入緩衝 → 餵 writer（全文；writer 自動追）
    async _apply_append(payload) {
        const response_kind = String(payload.kind || "");
        const append_text = String(payload.text || "");

        // 判定：冇內容 → 唔做
        if (append_text === "") {
            return;
        }

        // 判定：換輪進行中 → 先暫存（避免寫落上一輪段）
        if (this.turn_switching === true) {
            this.pending_appends.push(payload);

            return;
        }

        // 判定：本段未有緩衝 → 起一個
        if (!this.seg_texts[response_kind]) {
            this.seg_texts[response_kind] = "";
        }

        this.seg_texts[response_kind] += append_text;

        await this._write_segment_text(response_kind, this.seg_texts[response_kind]);
    }

    // turn：換輪（補骨架；新段由後端重新渲染）
    async _apply_turn(payload) {
        sc_console("message", "stream").log("新一輪", payload.index);

        this.turn_switching = true;
        this.seg_texts = {};

        const message_instance_poll = await registry().sc_ai_chats().message();
        const message_poll_instance = await message_instance_poll.act().message_poll();

        const chat_instance = await registry().sc_ai_chats().chat();

        message_poll_instance.render_once(chat_instance.get_chat_sid());

        this.turn_switching = false;

        const pending_appends = this.pending_appends;

        this.pending_appends = [];

        // 逐條暫存 append：換輪完成後補寫
        for (let pending_index = 0; pending_index < pending_appends.length; pending_index++) {
            await this._apply_append(pending_appends[pending_index]);
        }
    }

    // state：同步卡片屬性
    _apply_state(payload) {
        const j_message_panel = this._get_panel();

        // 判定：冇卡片 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const next_status = String(payload.status || "");

        j_message_panel.attr("data-status", next_status);

        // 判定：狀態已到終態 → 清 working 旗標（純 CSS 閘門用）
        if (next_status === "completed" || next_status === "failed" || next_status === "cancelled") {
            j_message_panel.attr("data-working", "0");
        }
    }

    // done：最後一次完整渲染（Markdown／工具列交返 poll 渲染器）
    async _apply_done(payload) {
        this._apply_state({ status: payload.status });

        this._complete_all_writers();

        const message_instance_poll = await registry().sc_ai_chats().message();
        const message_poll_instance = await message_instance_poll.act().message_poll();

        const chat_instance = await registry().sc_ai_chats().chat();

        message_poll_instance.render_once(chat_instance.get_chat_sid());

        await this.stop();
    }

    // 段文字：餵 writer（全文；自動接續印）；result 段帶 markdown 串流渲染
    async _write_segment_text(response_kind, full_text) {
        this.seg_texts[response_kind] = full_text;

        // 判定：冇內容 → 唔做（保留後端骨架）
        if (full_text === "") {
            return;
        }

        const j_segment = this._get_segment(response_kind);

        // 判定：冇段 → 唔做
        if (j_segment.length === 0) {
            return;
        }

        const j_seg_text = j_segment.find("[data-text='" + response_kind + "']").first();

        // 判定：冇內容節點 → 唔做
        if (j_seg_text.length === 0) {
            return;
        }

        const writer_instance = await this._get_writer(j_seg_text, response_kind);

        writer_instance.write(full_text);
    }

    // 取（或建立）writer（每內容元素一個——同舊渲染同一套引擎）
    async _get_writer(j_seg_text, response_kind) {
        const content_element = j_seg_text[0];

        // 判定：已掛 writer → 直接重用
        if (content_element.writer_instance) {
            return content_element.writer_instance;
        }

        const message_instance_writer = await registry().sc_ai_chats().message();
        const message_writer_instance = await message_instance_writer.act().message_writer();

        const writer_instance = message_writer_instance.get_writer_instance();

        const writer_options = {
            element:             content_element,
            target_duration_dec: 2000,
        };

        // 判定：result 段 → markdown 串流渲染（同舊渲染一致）
        if (response_kind === "result") {
            const markdown = await registry().sc_markdown();

            writer_options.render_html = function (raw_text) { return markdown.render_streaming(raw_text); };

            j_seg_text.addClass("sc_markdown_body");
        }

        writer_instance.set_options(writer_options);

        content_element.writer_instance = writer_instance;

        return writer_instance;
    }

    // 全部段立即寫完（done 用）
    _complete_all_writers() {
        const j_message_panel = this._get_panel();

        // 判定：冇卡片 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        j_message_panel.find(".seg__text").each(function () {
            // 判定：有 writer → 立即寫完
            if (this.writer_instance) {
                this.writer_instance.complete();
            }
        });
    }
}

export const message_stream = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_stream());
