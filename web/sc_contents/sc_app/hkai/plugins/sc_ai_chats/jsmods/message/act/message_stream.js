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

        // 本輪輪次（SSE 事件帶 response_index——段骨架據此標記，插入位置精準）
        this.current_response_index = 0;

        // 各段全文緩衝（append 累加——餵 writer 用）
        this.seg_texts = {};

        // 每秒用時 ticker（全頁一個；ready 時起）
        this.tick_timer = null;

        // 本輪工具段（turn 時清空——每輪一個）
        this.tool_segment = null;

        // 換輪中（turn 事件處理期間——append 先入緩衝，完成後才寫）
        this.turn_switching = false;

        // 換輪期間暫存嘅 append
        this.pending_appends = [];

        // 已套用嘅活動日誌（uid → true；防重複累加）
        this.applied_activity_uids = {};

        // 最後收到幀嘅時間（生存監察——3 秒無幀＝假死）
        this.last_event_ms = 0;

        // 事件處理鏈（嚴格 FIFO——async handler 唔可以交錯，否則換訊息／派工期間次序會亂）
        this.event_chain = Promise.resolve();

        // 生存監察 timer（全頁一個）
        this.liveness_timer = null;
    }

    // 頁面載入：對話有工作中卡片 → 開一條對話級線（identity＝project_slug／chat_sid）
    async start_for_page() {
        const j_working_panels = jQuery(".message_wrapper[data-working='1']");

        sc_console("message", "stream").log("頁面載入——工作中卡片", j_working_panels.length);

        // 判定：有工作中卡片 → 開線（排隊中冇輸出，唔需要線）
        if (j_working_panels.length > 0) {
            const page_identity = this._get_page_identity();

            await this.open(page_identity.project_slug, page_identity.chat_sid);
        }

    }

    // 頁面 identity（由網址前兩段取——project_slug／chat_sid）
    _get_page_identity() {
        const path_segments = window.location.pathname.split("/").filter(function (path_segment) { return path_segment !== ""; });

        const page_identity = {};
        page_identity.project_slug = path_segments.length > 0 ? path_segments[0] : "";
        page_identity.chat_sid = path_segments.length > 1 ? path_segments[1] : "";

        return page_identity;
    }

    // 開串流（回 bool＝有冇開；identity＝project_slug／chat_sid——一條對話一條線）
    async open(project_slug, chat_sid) {
        const stream_config = registry().sc_stream().get_config();

        sc_console("message", "stream").log("open 請求", project_slug, chat_sid);

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
        if (project_slug === "" || chat_sid === "") {
            return false;
        }

        const stream_url = stream_base_url
            + "/bridge_sse_for_message?project_slug=" + encodeURIComponent(project_slug)
            + "&chat_sid=" + encodeURIComponent(chat_sid);

        this.project_slug = project_slug;
        this.chat_sid = chat_sid;
        this.seg_texts = {};
        this.applied_activity_uids = {};

        const stream_singleton = await registry().sc_stream().message();

        sc_console("message", "stream").log("URL", stream_url);

        const thisObject = this;

        const is_opened = stream_singleton.start(
            stream_url,
            ["ready", "append", "turn", "turn_done", "tool", "state", "log", "done"],
            function (event_name, event_payload) {
                thisObject._enqueue_event(event_name, event_payload);
            },
        );

        // 判定：開線成功 → 起生存監察（3 秒冇幀＝假死 → 重建）
        if (is_opened === true) {
            this.last_event_ms = Date.now();

            this._start_liveness_timer();
        }

        return is_opened;
    }

    // 事件入鏈（嚴格 FIFO；一個 handler 未完唔准下一幀插入）
    // 上游：sc_stream on_event
    // 下游：_handle_event
    _enqueue_event(event_name, event_payload) {
        const thisObject = this;

        this.event_chain = this.event_chain
            .then(function () {
                return thisObject._handle_event(event_name, event_payload);
            })
            .catch(function (event_error) {
                sc_console("message", "stream").error("事件處理失敗", event_name, event_error);
            });
    }

    // 生存監察（每 3 秒內要有任何幀，ping 已算——冇 = 假死 → 重建）
    // 上游：open（開線成功）
    // 下游：_rebuild_stream
    _start_liveness_timer() {
        // 判定：已有 timer → 唔重複
        if (this.liveness_timer !== null) {
            return;
        }

        const thisObject = this;

        this.liveness_timer = setInterval(function () {
            thisObject._check_stream_alive();
        }, 1000);
    }

    // 檢查生存（只睇連線狀態——冇 ping 之後「無幀」唔等於死線）
    // 上游：生存監察 timer（每 1 秒）
    // 下游：_rebuild_stream（線已關／已死才重建）
    async _check_stream_alive() {
        // 判定：冇 identity（未開線）→ 唔做
        if (this.project_slug === "" || this.chat_sid === "") {
            return;
        }

        const stream_singleton = await registry().sc_stream().message();

        // 判定：線仍開住 → 唔動
        if (stream_singleton.is_open()) {
            return;
        }

        sc_console("message", "stream").log("線路已關／已死 → 重建");

        await this._rebuild_stream();
    }

    // 重建串流（舊線由後台 token 機制踢走——前端唔需要等關閉）
    async _rebuild_stream() {
        this.last_event_ms = Date.now();

        // 先確實關走舊線（否則 start() 見同 URL 只換 callback，永遠唔會重連）
        const stream_singleton = await registry().sc_stream().message();

        stream_singleton.stop();

        await this.open(this.project_slug, this.chat_sid);
    }

    // 關串流（清 identity／緩衝＋停生存監察——切換對話或專案時舊事件一律作廢）
    async stop() {
        const stream_singleton = await registry().sc_stream().message();

        stream_singleton.stop();

        // 判定：有生存監察 timer → 停
        if (this.liveness_timer !== null) {
            clearInterval(this.liveness_timer);

            this.liveness_timer = null;
        }

        this.project_slug = "";
        this.chat_sid = "";
        this.prompt_id = "";
        this.message_id = 0;
        this.last_event_ms = 0;
        this.seg_texts = {};
        this.pending_appends = [];
        this.applied_activity_uids = {};
        this.turn_switching = false;
        this.tool_segment = null;
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

        // 生存訊號（收到任何內容幀＝線仍生存）
        this.last_event_ms = Date.now();

        // 事件自帶身份（對話級線——多條訊息共用一條線，靠 message_id 定位卡片）
        const event_message_id = Number(payload.message_id || 0);

        // 判定：有 message_id → 綁定本輪目標卡片
        if (event_message_id > 0) {
            this.message_id = event_message_id;
        }

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

    // 取卡片（message_id 優先——對話級線多條訊息共用；冇帶＝用當前綁定）
    _get_panel(message_id) {
        const panel_message_id = Number(message_id || 0) > 0 ? Number(message_id) : this.message_id;

        return jQuery(".message_wrapper[data-message_id='" + panel_message_id + "']").first();
    }

    // 按輪次插入新段：搵第一個「index 更大」嘅段（thinking／result／tool 一齊比），插佢前面；否則貼尾
    // 上游：_get_segment／_get_tool_segment
    // 下游：DOM（.agent_message 內段次序）
    _insert_segment_by_index(j_agent_message, j_new_segment, segment_index) {
        // 判定：冇有效輪次 → 貼尾
        if (Number.isFinite(segment_index) === false) {
            j_agent_message.append(j_new_segment);

            return;
        }

        const j_later_segment = j_agent_message.find(".seg--thinking, .seg--result, .seg--tool").filter(function () {
            const later_index = Number(jQuery(this).attr("data_response_index"));

            // 判定：冇有效輪次嘅段 → 唔參與比較
            return Number.isFinite(later_index) && later_index > segment_index;
        }).first();

        // 判定：搵到更大輪次 → 插佢前面
        if (j_later_segment.length > 0) {
            j_later_segment.before(j_new_segment);

            return;
        }

        j_agent_message.append(j_new_segment);
    }

    // 取段（kind＝thinking／result）：一律以輪次 index 為準（同一輪同型只有一段；查唔到＝造新段）
    _get_segment(response_kind, response_index) {
        const j_message_panel = this._get_panel();

        // 判定：卡片未喺畫面 → 冇段
        if (j_message_panel.length === 0) {
            return jQuery();
        }

        const j_agent_message = j_message_panel.find(".agent_message").first();

        // 模型顯示文（wrapper attribute——同後端工具列同一口徑）
        const message_model_text = String(j_message_panel.attr("data-model_text") || "");

        // payload 有冇帶輪次（有＝一切以它為準；冇＝ready 全文重播，用當前輪）
        const has_response_index = Number.isFinite(Number(response_index));

        const segment_index = has_response_index ? Number(response_index) : this.current_response_index;

        let j_segment = jQuery();

        // 判定：有輪次 index → 只認同一輪嘅段（唔受換輪旗標影響；查唔到＝該輪未有段）
        if (Number.isFinite(segment_index)) {
            j_segment = j_agent_message.find(".seg--" + response_kind + "[data_response_index='" + segment_index + "']").last();
        }

        // 判定：payload 冇帶 index 且查唔到 → 只可沿用「未有輪次標記」嘅舊段
        if (!has_response_index && j_segment.length === 0) {
            const j_last_segment = j_agent_message.find(".seg--" + response_kind).last();
            const last_segment_index = String(j_last_segment.attr("data_response_index") || "");

            // 判定：最後一段帶住輪次（屬其他輪）→ 唔沿用，交下面造新段
            if (j_last_segment.length > 0 && last_segment_index === "") {
                j_segment = j_last_segment;
            }
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

            // *** : 步驟 3——造好骨架；有輪次就按 index 排位插入（thinking／result／tool 同一條規則）
            const j_new_segment = jQuery(segment_html);

            this._insert_segment_by_index(j_agent_message, j_new_segment, segment_index);

            j_segment = j_new_segment;

            // 段帶輪次（同一輪同型只有一段——前端據此精準寫入）
            j_segment.attr("data_response_index", Number.isFinite(segment_index) ? String(segment_index) : "");

            // 判定：result 段且有模型資訊 → 補 message_model（工具列右邊，同後端一致）
            if (response_kind === "result" && message_model_text !== "") {
                const j_bar_actions = j_segment.find(".seg__bar_actions").first();
                const j_model_text = jQuery("<span class='message_model'></span>").text(message_model_text);

                j_bar_actions.append(j_model_text);
            }
        }

        return j_segment;
    }

    // 段身份／狀態落 DOM（payload 由 bridge poll entry 帶落）
    // 上游：_write_segment_text／_apply_turn_done
    // 下游：DOM（data_response_index／data-start_at／data-response_status／data-content_length）
    _apply_segment_meta(j_segment, payload) {
        // 判定：冇段 → 唔做
        if (j_segment.length === 0) {
            return;
        }

        const segment_payload = payload || {};
        const segment_start_at = String(segment_payload.start_at || "");
        const segment_status = String(segment_payload.response_status || "");
        const segment_content_length = Number(segment_payload.content_length || 0);
        const segment_response_index = Number(segment_payload.response_index);

        // 判定：有輪次 index → 落 attr（多輪資料同時吐出時靠佢對位）
        if (Number.isFinite(segment_response_index)) {
            j_segment.attr("data_response_index", String(segment_response_index));
        }

        // 判定：有開始時間 → 落 attr
        if (segment_start_at !== "") {
            j_segment.attr("data-start_at", segment_start_at);
        }

        // 判定：有狀態 → 落 attr
        if (segment_status !== "") {
            j_segment.attr("data-response_status", segment_status);
        }

        // 判定：有內容長度 → 落 attr
        if (segment_content_length > 0) {
            j_segment.attr("data-content_length", String(segment_content_length));
        }
    }

    // ready：貼全文（餵 writer——自動接續印）
    async _apply_ready(payload) {
        this.message_id = Number(payload.message_id || this.message_id);
        this.current_response_index = Number(payload.index || this.current_response_index);

        this._start_elapsed_clock();

        this._apply_state({ status: payload.status });

        // 段身份／狀態（ready 帶落——每型一份；連輪次一齊餵，段一建立就有 meta，唔使等之後 append／turn_done）
        const ready_segment_meta_map = payload.segment_meta || {};

        const thinking_segment_meta = {};
        thinking_segment_meta.response_index = this.current_response_index;
        Object.assign(thinking_segment_meta, ready_segment_meta_map.thinking || {});

        const result_segment_meta = {};
        result_segment_meta.response_index = this.current_response_index;
        Object.assign(result_segment_meta, ready_segment_meta_map.result || {});

        await this._write_segment_text("thinking", String(payload.thinking || ""), thinking_segment_meta);
        await this._write_segment_text("result", String(payload.result || ""), result_segment_meta);

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

            // 錯誤級 → 同時喺回答區補一個錯誤段（同 DB 版 seg--error 一致；重載後見同一段）
            if (String(log_entry.level || "").toLowerCase() === "error") {
                this._append_error_segment(j_message_panel, String(log_entry.message || ""));
            }
        }
    }

    // 錯誤段（即時顯示——同一段文字只補一次）
    _append_error_segment(j_message_panel, error_text) {
        // 判定：冇文字 → 唔做
        if (error_text === "") {
            return;
        }

        const j_agent_message = j_message_panel.find(".agent_message");

        // 判定：冇回答槽 → 唔做
        if (j_agent_message.length === 0) {
            return;
        }

        // 判定：同一段文字已存在 → 唔重複
        const existing_text_list = j_agent_message.find(".seg--error .seg__text");

        for (let existing_index = 0; existing_index < existing_text_list.length; existing_index++) {
            // 判定：文字一樣 → 收工
            if (String(jQuery(existing_text_list[existing_index]).text() || "") === error_text) {
                return;
            }
        }

        const j_error_segment = jQuery("<div class='seg seg--error'></div>");
        j_error_segment.append("<div class='seg__icon'>⚠️</div>");
        j_error_segment.append(jQuery("<div class='seg__text' data-text='error'></div>").text(error_text));

        j_agent_message.append(j_error_segment);
    }

    // append：增量入緩衝 → 餵 writer（全文；writer 自動追）
    async _apply_append(payload) {
        const response_kind = String(payload.kind || "");
        const append_text = String(payload.text || "");

        // 判定：冇內容 → 唔做
        if (append_text === "") {
            return;
        }

        // 判定：整段文字重播（ready 快照已有同一全文）→ 跳過（防重複）
        if (this.seg_texts[response_kind] === append_text) {
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

        await this._write_segment_text(response_kind, this.seg_texts[response_kind], payload);
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

        // 判定：冇內容 → 唔造空殼（該輪冇 thinking／result 屬正常，唔好留空格）
        if (full_text === "") {
            return;
        }

        const j_segment = this._get_segment(response_kind, payload.response_index);

        // 判定：冇段 → 唔做
        if (j_segment.length === 0) {
            return;
        }

        this._apply_segment_meta(j_segment, payload);

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

        // 段身份（輪次／狀態／長度）落 DOM——多輪工具同時吐時對位
        this._apply_segment_meta(j_tool_segment, tool_record);

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

        // *** : 步驟 2——工具段同樣按 index 插入（thinking／result／tool 用同一條規則）
        const j_new_tool_segment = jQuery(segment_html);

        this._insert_segment_by_index(j_agent_message, j_new_tool_segment, this.current_response_index);

        this.tool_segment = j_new_tool_segment;
        this.tool_segment.attr("data-started_ms", String(Date.now()));

        // 段帶輪次（多輪工具同時吐時靠佢對位）
        this.tool_segment.attr("data_response_index", String(this.current_response_index));

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

        this.current_response_index = Number(payload.index || this.current_response_index);

        this.turn_switching = true;
        this.seg_texts = {};

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
    _apply_state(payload) {        const j_message_panel = this._get_panel();

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

    // done：該條訊息收尾（狀態同步＋全部段印完）——線照開住，等下條訊息
    async _apply_done(payload) {
        this._apply_state({ status: payload.status });

        this._complete_all_writers();

        this._close_tool_items(payload.status);

        await this._render_mermaid_if_any();

        // 該條訊息完結 → 清本條緩衝（線唔關；下一條訊息重新起）
        this.seg_texts = {};
        this.pending_appends = [];
        this.applied_activity_uids = {};
        this.turn_switching = false;
        this.tool_segment = null;

        // 收尾：即刻推隊頭（後端先對賬終態再前移——回新氣泡＋整排隊列；前端唯一砌氣泡者）
        const queue_instance = await registry().sc_ai_chats().queue();
        const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

        await queue_dispatch_instance.queue_dispatch(this.chat_sid);
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
    async _write_segment_text(response_kind, full_text, payload) {
        this.seg_texts[response_kind] = full_text;

        // 判定：冇內容 → 唔做（保留後端骨架）
        if (full_text === "") {
            return;
        }

        const segment_payload = payload || {};
        const j_segment = this._get_segment(response_kind, segment_payload.response_index);

        // 判定：冇段 → 唔做
        if (j_segment.length === 0) {
            return;
        }

        // 段身份／狀態落 DOM（bridge 帶落——前端只照寫）
        this._apply_segment_meta(j_segment, segment_payload);

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
