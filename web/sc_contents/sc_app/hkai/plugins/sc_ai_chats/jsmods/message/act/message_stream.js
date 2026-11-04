// jsmods/message/act/message_stream.js —— 訊息串流（act 層——一檔一事）
// 職責：開／關 SSE；按事件（ready／turn／append／state／done）直接貼文字上畫面
// 上游：頁面載入（回應中卡片）／prompt act_prompt_send（送出成功）
// 下游：sc_stream start／stop；turn 自己開新段（唔問後端）；done 只收尾（唔發請求）

class _message_stream {
    constructor() {
        // 本串流 identity（開線時記——URL 只用 identity，message_id 只作畫面綁定）
        this.project_slug = "";
        this.chat_sid = "";
        this.prompt_id = "";
        this.message_id = 0;

        // 各段全文緩衝（append 累加——餵 writer 用）
        this.seg_texts = {};

        // 本輪仲未開段嘅類型（turn 設 true——首次寫入時開新段；防新一輪貼入上一段）
        this.turn_new_kinds = {};

        // 每秒用時 ticker（全頁一個；ready 時起）
        this.tick_timer = null;

        // 本輪工具段（turn 時清空——每輪一個）
        this.tool_segment = null;

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

        const stream_singleton = await registry().sc_stream().message();

        sc_console("message", "stream").log("URL", stream_url);

        return stream_singleton.start(
            stream_url,
            ["ready", "append", "turn", "turn_done", "tool", "state", "log", "done"],
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
        const stream_singleton = await registry().sc_stream().message();

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
        this.turn_new_kinds = {};
    }

    // 用時計：起每秒 ticker＋落本卡片基準（未有基準時用當下時間）
    // 上游：_apply_ready（每條線接上時）
    // 下游：start_tick_timer／tick_elapsed
    _start_elapsed_clock() {
        this.start_tick_timer();

        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const j_total_elapsed = j_message_panel.find(".total_elapsed").first();

        // 判定：已有基準（後端帶過秒數）→ 唔覆蓋
        if (Number(j_total_elapsed.attr("data-elapsed_ts")) > 0) {
            return;
        }

        j_total_elapsed.attr("data-elapsed_ts", Date.now());
    }

    // 起每秒 ticker（只起一次——全頁一個）
    // 上游：_start_elapsed_clock
    // 下游：tick_elapsed（每秒）
    start_tick_timer() {
        // 判定：已起 → 唔重複
        if (this.tick_timer) {
            return;
        }

        const thisObject = this;

        this.tick_timer = setInterval(function () {
            thisObject.tick_elapsed();
        }, 1000);
    }

    // 用時 tick（掃工作中卡片：總用時＋段用時＋權限倒數；每秒一次）
    // 上游：start_tick_timer
    // 下游：format_elapsed_sec／_tick_permission_countdowns
    tick_elapsed() {
        const thisObject = this;

        // 總用時（每張工作緊嘅卡片一個）
        jQuery(".message_wrapper[data-working='1'] .total_elapsed").each(function () {
            const j_total_elapsed = jQuery(this);

            const elapsed_sec = Number(j_total_elapsed.attr("data-elapsed_sec"));
            const elapsed_ts  = Number(j_total_elapsed.attr("data-elapsed_ts"));

            // 判定：未有基準（未落過時間戳）→ 唔動
            if (elapsed_ts === 0) {
                return;
            }

            const now_elapsed_sec = elapsed_sec + Math.floor((Date.now() - elapsed_ts) / 1000);

            j_total_elapsed.find(".total_elapsed_time").text(thisObject.format_elapsed_sec(now_elapsed_sec));
        });

        // 段用時（thinking／result 計時器）
        jQuery(".message_wrapper[data-working='1'] .response_elapsed").each(function () {
            const j_response_elapsed = jQuery(this);

            const elapsed_sec = Number(j_response_elapsed.attr("data-elapsed_sec"));
            const elapsed_ts  = Number(j_response_elapsed.attr("data-elapsed_ts"));

            // 判定：未有基準（未落過時間戳）→ 唔動
            if (elapsed_ts === 0) {
                return;
            }

            const now_elapsed_sec = elapsed_sec + Math.floor((Date.now() - elapsed_ts) / 1000);

            j_response_elapsed.text(thisObject.format_elapsed_sec(now_elapsed_sec));
        });

        this._tick_permission_countdowns();
    }

    // 權限卡倒數（每秒一次）
    _tick_permission_countdowns() {
        jQuery(".permission_prompt[data-deadline_ts]").each(function () {
            const j_permission_prompt = jQuery(this);

            const deadline_ts = Number(j_permission_prompt.attr("data-deadline_ts"));

            // 判定：無有效期限 → 唔動
            if (!deadline_ts) {
                return;
            }

            const remain_sec = Math.max(0, Math.ceil(deadline_ts - Date.now() / 1000));

            const j_permission_countdown = j_permission_prompt.find(".permission_prompt__countdown");

            const remain_suffix = j_permission_countdown.attr("data-suffix") || "";

            j_permission_countdown.text(remain_sec + "s" + remain_suffix);
        });
    }

    // 秒 → m:ss（段用時／總用時共用）
    format_elapsed_sec(elapsed_sec) {
        const elapsed_minutes = Math.floor(elapsed_sec / 60);
        const elapsed_seconds = elapsed_sec % 60;

        return elapsed_minutes + ":" + String(elapsed_seconds).padStart(2, "0");
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

        // 判定：tool → 本輪工具調用（一條命令一格）
        if (event_name === "tool") {
            this._apply_tool_event(payload);

            return;
        }

        // 判定：turn_done → 該回合該型已完結（用完整內容重寫該段＋停寫）
        if (event_name === "turn_done") {
            await this._apply_turn_done(payload);

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

    // 取段（kind＝thinking／result）：換輪後第一次寫入＝開新段；其餘寫返最後一段；冇段＝造骨架
    _get_segment(response_kind) {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 冇段
        if (j_message_panel.length === 0) {
            return jQuery();
        }

        const j_agent_message = j_message_panel.find(".agent_message").first();

        // 模型顯示文（wrapper attribute——同後端工具列同一口徑）
        const message_model_text = String(j_message_panel.attr("data-model_text") || "");

        // 判定：本輪首次寫入呢一型 → 先落旗標（下面決定開新段抑或重用空段）
        const is_turn_first_write = this.turn_new_kinds[response_kind] === true;

        this.turn_new_kinds[response_kind] = false;

        let j_segment = j_agent_message.find(".seg--" + response_kind).last();

        // 判定：換輪首次寫入 且 最後一段已用過 → 唔可以再寫入（開新段）
        if (is_turn_first_write === true && this._is_segment_used(j_segment) === true) {
            j_segment = jQuery();
        }

        // 判定：未有可寫段 → 造骨架（thinking／result 兩種結構，同後端輸出一致）
        if (j_segment.length === 0) {
            let segment_html = "";

            // 判定：thinking 段 → 工具列喺上、計時格獨立、冇 seg__head、有水印
            if (response_kind === "thinking") {
                segment_html = "<div class='seg seg--thinking' data-response_id='' data-start_at='' data-response_status='' data-content_length='0'>"
                    + "<div class='seg__body'>"
                    + "<div class='seg__bar'>"
                    + "<button type='button' class='seg__action' data-action='thinking_zoom' data-zoom_scope='.seg--thinking' data-zoom_target='[data-text=thinking]' data-zoom_title='Thinking' title='Zoom'><i class='fa-solid fa-up-right-and-down-left-from-center'></i></button>"
                    + "<button type='button' class='seg__action' data-action='message_copy' data-copy_scope='.seg--thinking' data-copy_target='[data-text=thinking]' title='Copy'><i class='fa-regular fa-copy'></i></button>"
                    + "</div>"
                    + "<div class='seg__elapsed'><span class='response_elapsed' data-response_id='' data-elapsed_sec='0' data-elapsed_ts='" + Date.now() + "'>0:00</span></div>"
                    + "<div class='seg__scroll'><div data-text='thinking' class='seg__text'></div></div>"
                    + "</div>"
                    + "<div class='seg__mark'><div>thinking</div></div>"
                    + "</div>";
            } else {
                segment_html = "<div class='seg seg--result' data-response_id='' data-start_at='' data-response_status='' data-content_length='0'>"
                    + "<div class='seg__head'></div>"
                    + "<div class='seg__body'>"
                    + "<div class='seg__scroll noscroll'><div data-text='result' class='seg__text sc_markdown_body'></div></div>"
                    + "<div class='seg__bar'>"
                    + "<div class='seg__bar_actions'>"
                    + "<button type='button' class='seg__action' data-action='response_zoom' data-zoom_scope='[data-message_id]' data-zoom_target='[data-text=result]' data-zoom_title='Answer' title='Zoom'>"
                    + "<i class='fa-solid fa-up-right-and-down-left-from-center'></i>"
                    + "</button>"
                    + "<button type='button' class='seg__action' data-action='message_copy' data-copy_scope='[data-message_id]' data-copy_target='[data-text=result]' title='Copy'>"
                    + "<i class='fa-regular fa-copy'></i>"
                    + "</button>"
                    + "</div>"
                    + "<span class='response_elapsed' data-response_id='' data-elapsed_sec='0' data-elapsed_ts='" + Date.now() + "'>0:00</span>"
                    + "</div>"
                    + "</div>"
                    + "</div>";
            }

            j_agent_message.append(segment_html);

            j_segment = j_agent_message.find(".seg--" + response_kind).last();

            // 判定：result 段且有模型資訊 → 補 message_model（工具列右邊，同後端一致）
            if (response_kind === "result" && message_model_text !== "") {
                const j_bar_actions = j_segment.find(".seg__bar_actions").first();
                const j_model_text = jQuery("<span class='message_model'></span>").text(message_model_text);

                j_bar_actions.append(j_model_text);
            }
        }

        return j_segment;
    }

    // 段用過未（有內容 或 已綁真 response_id 即算用過——重連攞返嘅空段可以重用）
    _is_segment_used(j_segment) {
        // 判定：冇段 → 當用過（呼叫端要開新段）
        if (j_segment.length === 0) {
            return true;
        }

        const segment_response_id = String(j_segment.attr("data-response_id") || "");

        // 判定：已綁真身份 → 用過
        if (segment_response_id !== "") {
            return true;
        }

        const segment_text = j_segment.find(".seg__text").text().trim();

        return segment_text !== "";
    }

    // ready：貼全文（餵 writer——自動接續印）
    async _apply_ready(payload) {
        this.message_id = Number(payload.message_id || this.message_id);

        this._start_elapsed_clock();

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

    // turn_done：該回合該型已完結 → 用完整內容重寫該段（Markdown 最終化）然後停寫
    // 上游：bridge SSE turn_done（{kind, text}——每段只發一次）
    // 下游：_get_segment／_get_writer／writer.write＋complete
    async _apply_turn_done(payload) {
        const response_kind = String(payload.kind || "");
        const full_text = String(payload.text || "");

        // 判定：只認內文兩型 → 其餘唔理
        if (response_kind !== "thinking" && response_kind !== "result") {
            return;
        }

        this.seg_texts[response_kind] = full_text;

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

        // 先停寫（避免打字動畫覆蓋最終渲染）
        if (j_seg_text[0].writer_instance) {
            j_seg_text[0].writer_instance.stop();
        }

        j_seg_text.text(full_text);

        // 判定：result 段 → 終態完整渲染（同頁面載入同一條管線：editblock → sc_markdown）
        if (response_kind === "result") {
            j_seg_text[0].sc_raw_text = full_text;

            const j_message_panel = this._get_panel();
            const message_instance = await registry().sc_ai_chats().message();
            const message_render_instance = await message_instance.act().message_render();

            await message_render_instance.render_single_markdown(j_message_panel);
        }

        // 脫離 writer（done 唔會再重繪呢段）
        j_seg_text[0].writer_instance = null;
    }

    // tool 事件（{seq,tool_call_id,phase,tool,input_full,output_full,status}）→ 本輪工具段加／更新一格
    // 上游：bridge SSE tool（tools.jsonl 逐行）
    // 下游：_get_tool_segment／DOM
    _apply_tool_event(tool_record) {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const tool_call_id = String(tool_record.tool_call_id || "");

        // 判定：冇 id → 唔做
        if (tool_call_id === "") {
            return;
        }

        const j_tool_segment = this._get_tool_segment();

        // 判定：冇工具段（建唔到）→ 唔做
        if (j_tool_segment.length === 0) {
            return;
        }

        const j_tool_text = j_tool_segment.find("[data-text='tool']").first();

        let j_tool_item = j_tool_text.find(".tool_item[data-tool_call_id='" + tool_call_id + "']").first();

        // 判定：未見過 → 建一格
        if (j_tool_item.length === 0) {
            j_tool_item = this._build_tool_item(tool_record);

            j_tool_text.append(j_tool_item);
        }

        const record_phase = String(tool_record.phase || "start");

        // 判定：start → 記低命令原文
        if (record_phase === "start") {
            const tool_input = String(tool_record.input_full || "");

            // 判定：有輸入 → 寫入命令原文（後續 in_progress 冇輸入，唔清走）
            if (tool_input !== "") {
                j_tool_item.find(".tool_item__input").first().text(this._extract_tool_command(tool_input));
            }

            // 記開始時間（用時顯示用）
    
            return;
        }

        // done → 更新狀態（同輸出）
        j_tool_item.find(".tool_item__status").first().text(String(tool_record.status || ""));

        this._freeze_tool_segment_when_done(j_tool_segment, String(tool_record.datetime || ""));

        const tool_output = String(tool_record.output_full || "");

        // 判定：有輸出 → 出輸出塊
        if (tool_output !== "") {
            const j_tool_output = j_tool_item.find(".tool_item__output").first();

            j_tool_output.text(tool_output);
            j_tool_output.removeAttr("hidden");
        }
    }

    // 由 {'command': '...'} 抽命令原文（唔係 dict 就原樣回）
    _extract_tool_command(raw_input) {
        const raw_text = String(raw_input || "").trim();

        // 判定：唔係 command dict → 原樣
        const command_match = raw_text.match(/^\{\s*'command'\s*:\s*'([\s\S]*)'\s*\}$/);

        // 判定：冇配對 → 原樣
        if (command_match === null) {
            return raw_text;
        }

        return command_match[1];
    }

    // 取（或建立）本輪工具段（結構同後端一致：head／scroll／bar＋copy）
    _get_tool_segment() {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 冇段
        if (j_message_panel.length === 0) {
            return jQuery();
        }

        // 判定：本輪已有工具段 → 重用
        if (this.tool_segment !== null && this.tool_segment.closest(".message_wrapper").length > 0) {
            return this.tool_segment;
        }

        const j_agent_message = j_message_panel.find(".agent_message").first();

        const segment_html = "<div class='seg seg--tool'>"
            + "<div class='seg__head'></div>"
            + "<div class='seg__body'>"
            + "<div class='seg__scroll noscroll'>"
            + "<div data-text='tool' class='seg__text seg__text--tool'></div>"
            + "</div>"
            + "<div class='seg__bar'>"
            + "<div class='seg__bar_actions'></div>"
            + "<span class='response_elapsed' data-response_id='' data-elapsed_sec='0' data-elapsed_ts='" + Date.now() + "'>0:00</span>"
            + "</div>"
            + "</div>"
            + "</div>";

        j_agent_message.append(segment_html);

        this.tool_segment = j_agent_message.find(".seg--tool").last();
        this.tool_segment.attr("data-started_ms", String(Date.now()));

        return this.tool_segment;
    }

    // 砌一格工具（head＝#seq／工具名／狀態；主體＝命令原文 pre）
    _build_tool_item(tool_record) {
        const j_tool_item = jQuery("<div class='tool_item'></div>");

        j_tool_item.attr("data-tool_call_id", String(tool_record.tool_call_id || ""));

        const j_tool_head = jQuery("<div class='tool_item__head'></div>");
        const j_tool_seq = jQuery("<span class='tool_item__seq'></span>").text("#" + String(tool_record.seq || ""));
        const j_tool_name = jQuery("<span class='tool_item__name'></span>").text(String(tool_record.tool || ""));
        const j_tool_status = jQuery("<span class='tool_item__status'></span>").text(String(tool_record.status || ""));

        j_tool_head.append(j_tool_seq);
        j_tool_head.append(j_tool_name);
        j_tool_head.append(j_tool_status);

        j_tool_item.append(j_tool_head);

        const tool_command_text = this._extract_tool_command(tool_record.input_full);

        const j_tool_line = jQuery("<div class='tool_item__line'></div>");
        const j_tool_input = jQuery("<pre class='tool_item__input'></pre>").text(tool_command_text);
        const j_tool_copy = jQuery("<button type='button' class='seg__action tool_item__copy' data-action='message_copy' data-copy_scope='.tool_item' data-copy_target='.tool_item__input' title='Copy'><i class='fa-regular fa-copy'></i></button>");

        j_tool_line.append(j_tool_input);
        j_tool_line.append(j_tool_copy);

        const j_tool_output = jQuery("<pre class='tool_item__output' hidden></pre>");

        j_tool_item.append(j_tool_line);
        j_tool_item.append(j_tool_output);

        return j_tool_item;
    }

    // turn：換輪（本輪兩型都未開段——首次寫入由 _get_segment 開新段；唔再問後端攞骨架）
    async _apply_turn(payload) {
        sc_console("message", "stream").log("新一輪", payload.index);

        this.turn_switching = true;
        this.seg_texts = {};

        // 本輪 thinking／result 都未開段（寫入時各開一個新段）
        this.turn_new_kinds["thinking"] = true;
        this.turn_new_kinds["result"] = true;

        // 新一輪 → 上一輪工具段收邊（補狀態＋凍結用時），下一個工具段另開
        this._close_tool_items("completed");

        // 判定：有上一輪工具段 → 凍結
        if (this.tool_segment !== null) {
            this._freeze_tool_segment_when_done(this.tool_segment, "");
        }

        this.tool_segment = null;

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

    // done：收尾（狀態同步＋全部段印完；唔再問後端攞 HTML）
    async _apply_done(payload) {
        this._apply_state({ status: payload.status });

        this._complete_all_writers();

        this._close_tool_items(payload.status);

        await this._render_mermaid_if_any();

        await this.stop();
    }

    // 工具段用時凍結（全部格都有狀態＝本段完成 → 定住秒數，唔再跳）
    // 上游：_apply_tool_event（done）
    // 下游：DOM（data-elapsed_ts=0 令 ticker 跳過）
    _freeze_tool_segment_when_done(j_tool_segment, finished_text) {
        // 判定：冇段 → 唔做
        if (j_tool_segment.length === 0) {
            return;
        }

        // 判定：仲有格未有狀態 → 未完成，唔凍結
        if (j_tool_segment.find(".tool_item__status").filter(function () { return jQuery(this).text().trim() === ""; }).length > 0) {
            return;
        }

        const j_elapsed = j_tool_segment.find(".response_elapsed").first();

        // 判定：已凍結 → 唔再做
        if (String(j_elapsed.attr("data-elapsed_ts")) === "0") {
            return;
        }

        const started_ms = Number(j_tool_segment.attr("data-started_ms") || 0);

        // 判定：冇開始基準 → 唔做
        if (started_ms < 1) {
            return;
        }

        const finished_ms = Date.parse(String(finished_text).replace(" ", "T"));
        const end_ms = isNaN(finished_ms) ? Date.now() : finished_ms;

        const elapsed_sec = Math.max(0, Math.round((end_ms - started_ms) / 1000));

        j_elapsed.text(this.format_elapsed_sec(elapsed_sec));
        j_elapsed.attr("data-elapsed_ts", "0");
    }

    // 收尾：本卡有 mermaid → 交 message_render 排隊渲染（同頁面載入同一條管線）
    // 上游：_apply_done
    // 下游：message_render.message_contains／render_single_mermaid
    async _render_mermaid_if_any() {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const message_instance = await registry().sc_ai_chats().message();
        const message_render_instance = await message_instance.act().message_render();

        const contains = message_render_instance.message_contains(j_message_panel);

        // 判定：冇 mermaid → 唔做
        if (contains.mermaid !== true) {
            return;
        }

        message_render_instance.render_single_mermaid(j_message_panel);
    }

    // 收尾：未收到 done 嘅工具格，用訊息終態補狀態（同 DB 渲染一致）
    // 上游：_apply_done
    // 下游：DOM
    _close_tool_items(message_status) {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 唔做
        if (j_message_panel.length === 0) {
            return;
        }

        const close_status_text = String(message_status || "completed");

        // 逐格工具：狀態空白 → 補
        j_message_panel.find(".seg--tool .tool_item__status").each(function () {
            const j_status = jQuery(this);

            // 判定：已有狀態 → 唔覆蓋
            if (j_status.text().trim() !== "") {
                return;
            }

            j_status.text(close_status_text);
        });
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

            writer_options.render_html = function (raw_text) { return markdown.render_full(raw_text); };

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
