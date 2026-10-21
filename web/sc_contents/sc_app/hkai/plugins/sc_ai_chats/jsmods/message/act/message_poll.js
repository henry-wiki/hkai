// jsmods/message/act/message_poll.js —— 輪詢引擎＋回應更新管線（act 層——一檔一事）
// 職責：message_poll（guard 防重複——啟動）／message_poll_detect（按載入回應偵測——有效對話＋有未完成先起；判定全喺度）／stop_poll_message／start_elapsed_ticker＋tick_elapsed＋format_elapsed_sec（每秒用時 counter——秒→m:ss）／poll_once（一輪：抓任務 → 有任務更新／冇任務＋有排隊派工 → 排下一輪）
//       ／update_response（回應更新入口——status／working 同步＋log＋分段打字＋終態收尾）／update_response_log／update_response_sections＋write_response_section／force_complete_all_thinking（終態停寫＋md 化＋派 message_poll_completed 勾）
// 備註：由 health 偵測到 message_poll job → entry 轉發 start；入場傳「要 poll 邊個對話」（呼叫端定）——每輪發請求前配對當前頁面（URL）；配對唔到／未選＝即停
//       續航唯一理由＝有嘢做（本輪有任務 或 現行對話有排隊）；皆無 → 停；acquired_responses＝每段已獲取緩衝（全文累積＋mb 字元長度——起 poll 由畫面 attribute 種、每輪由後端帶返；每輪申報，後台據此裁增量）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_poll {
    constructor() {
        this.interval = 2000;       // 每輪間隔（毫秒）
        this.running = false;
        this.running_timer_id = null;
        this.elapsed_timer_id = null;   // 用時計（每秒 tick——工作緊嘅訊息 counter）

        // 已獲取緩衝＋申報（per 訊息 per 段——全文累積／response_id／mb 字元長度（後端帶）；增量協議用）
        this.acquired_responses = {};

        // SSE 加速器（有 delta 即觸發一次 poll；停 poll 一併停；純 fallback 靠 poll）
        this.poll_chat_sid = "";
        this.stream_instance = null;

        // 重入防護（SSE 通知可能同計時器同一刻觸發——只准一輪在途）
        this.polling = false;
        this.poll_pending = false;
    }

    // 啟動（框架 stub——重複防護喺 message_poll() 內 running 旗標）
    init() {
        return this;
    }

    // 停止輪詢（清排程——失敗／完成皆走呢度）
    stop_poll_message() {
        if (this.running_timer_id) {
            clearTimeout(this.running_timer_id);
        }
        // 用時計（跟輪詢收——唔再輪詢就唔使 tick）
        if (this.elapsed_timer_id) {
            clearInterval(this.elapsed_timer_id);
            this.elapsed_timer_id = null;
        }
        this.running = false;

        // 判定：有 SSE 連線 → 一併停（唔再收通知）
        if (this.stream_instance) {
            this.stream_instance.stop();
            this.stream_instance = null;
        }
    }

    // 訂閱 SSE（只跟最後一條工作中訊息——task.working="1"；冇＝唔開）
    async watch_stream(tasks) {
        const message_instance = await registry().sc_ai_chats().message();
        const message_stream_instance = await message_instance.act().message_stream();

        let working_message_id = null;

        // 逐條任務找工作中訊息（最後一條＝最新）
        for (let task_index = 0; task_index < tasks.length; task_index++) {
            const task = tasks[task_index];
            const task_data = task.data || {};

            // 判定：工作中（後端旗標）→ 記住 message_id
            if (task.working === "1") {
                working_message_id = task_data.message_id;
            }
        }

        // 判定：冇工作中訊息 → 關掉殘留連線（唔開串流）
        if (working_message_id === null) {
            message_stream_instance.stop();
            this.stream_instance = null;

            return false;
        }

        const project_slug = this.get_project_slug_from_url();
        const thisObject = this;

        const stream_started = message_stream_instance.start(project_slug, working_message_id, function () {
            thisObject.poll_soon();
        });

        this.stream_instance = message_stream_instance;

        return stream_started;
    }

    // 立即跑一輪 poll（SSE 通知用——清走待跑計時器，避免兩輪重疊）
    poll_soon() {
        // 判定：未有對話 sid → 唔跑
        if (this.poll_chat_sid === "") {
            return;
        }

        // 判定：有排程 → 先清
        if (this.running_timer_id) {
            clearTimeout(this.running_timer_id);
        }

        // 判定：本輪仍在途 → 記低，等本輪完即刻再跑
        if (this.polling) {
            this.poll_pending = true;

            return;
        }

        this.running_timer_id = setTimeout(
            this.poll_once.bind(this, this.poll_chat_sid),
            50,
        );
    }

    // 由 URL 取 project_slug（/proj-xxx/chat_sid/ 第一段）
    get_project_slug_from_url() {
        const path_segments = location.pathname.split("/").filter(function (path_segment) { return path_segment !== ""; });

        // 判定：冇第一段 → 空
        if (path_segments.length === 0) {
            return "";
        }

        return path_segments[0];
    }

    // 用時計啟動（每秒 tick——工作緊嘅訊息 counter 遞增；已開 → 唔重開）
    start_elapsed_ticker() {
        // 判定：計時器已開 → 唔重開（guard）
        if (this.elapsed_timer_id) {
            return;
        }

        this.elapsed_timer_id = setInterval(this.tick_elapsed.bind(this), 1000);
    }

    // 秒 → 顯示時間（m:ss——段用時／總用時共用同一格式）
    format_elapsed_sec(elapsed_sec) {
        const elapsed_minutes = Math.floor(elapsed_sec / 60);
        const elapsed_seconds = elapsed_sec % 60;

        return elapsed_minutes + ":" + String(elapsed_seconds).padStart(2, "0");
    }

    // 用時計 tick（掃工作緊嘅訊息 counter——基準＋經過秒數遞增）
    // 註：時間戳讀取用 Number（~~ 係 32-bit 位元運算——毫秒大數會被截斷出錯）
    tick_elapsed() {
        const thisObject = this;

        jQuery(".message_wrapper[data-working='1'] .total_elapsed").each(function () {
            const j_total_elapsed = jQuery(this);

            const elapsed_sec = Number(j_total_elapsed.attr("data-elapsed_sec"));
            const elapsed_ts  = Number(j_total_elapsed.attr("data-elapsed_ts"));

            // 判定：未有基準（未收過 poll）→ 唔動
            if (elapsed_ts === 0) {
                return;
            }

            const now_elapsed_sec = elapsed_sec + Math.floor((Date.now() - elapsed_ts) / 1000);

            j_total_elapsed.find(".total_elapsed_time").text(thisObject.format_elapsed_sec(now_elapsed_sec));
        });

        // 段用時 tick（thinking／result／summarize 計時器——基準＋經過秒數遞增；m:ss）
        jQuery(".message_wrapper[data-working='1'] .response_elapsed").each(function () {
            const j_response_elapsed = jQuery(this);

            const elapsed_sec = Number(j_response_elapsed.attr("data-elapsed_sec"));
            const elapsed_ts  = Number(j_response_elapsed.attr("data-elapsed_ts"));

            // 判定：未有基準（未收過 poll／已凍結）→ 唔動
            if (elapsed_ts === 0) {
                return;
            }

            const now_elapsed_sec = elapsed_sec + Math.floor((Date.now() - elapsed_ts) / 1000);
            j_response_elapsed.text(thisObject.format_elapsed_sec(now_elapsed_sec));
        });

        // 權限提示倒數（每秒——剩餘秒數；未決定嘅卡先有）
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

    // 開始輪詢（直接起——入場即傳「要 poll 邊個對話」；每輪再配對當前頁面）
    message_poll(poll_chat_sid) {
        // 已在輪詢中 → 不重複啟動（guard 只擋外部重複啟動）
        if (this.running) {
            return this;
        }

        this.running = true;

        // 種緩衝（起 poll 先由畫面 attribute 收返現況——poll 段；種唔到＝嗰段全量重傳）
        this.seed_acquired_responses();

        // 用時計（每秒——工作緊嘅訊息 counter 遞增）
        this.start_elapsed_ticker();

        this.poll_once(poll_chat_sid);

        return this;
    }

    // 種已獲取緩衝（起 poll 時——filter 出仲要 poll 嘅段，由畫面讀返：text＝畫面全文、content_length＝後端 attribute 值；申報鍵＝response_id）
    seed_acquired_responses() {
        const thisObject = this;

        const j_sc_thread__viewport = jQuery(".sc_thread__viewport");

        const response_selectors = [".seg--thinking", ".seg--result"].join(",");

        const j_response_sections = j_sc_thread__viewport.find(response_selectors);

        // 逐 poll 段（已完結＝唔喺範圍——唔使種）
        j_response_sections.each(function () {
            const j_response_section = jQuery(this);

            const j_message_wrapper = j_response_section.closest(".message_wrapper[data-message_id]");

            // 段所屬訊息列（message_id 喺最近祖先）
            const message_id = j_message_wrapper.attr("data-message_id");

            // 段身份（data-response_id——申報配對鍵）
            const response_id  = parseInt(j_response_section.attr("data-response_id"), 10) || 0;

            // 判定：緩衝已有 → 唔覆蓋（可能已累積）
            if (
                thisObject.acquired_responses[message_id]
                &&  thisObject.acquired_responses[message_id][response_id]
            ) {
                return;
            }

            // 內容元素（每段一個 .seg__text——容器已知，直接搵）
            const j_response_content = j_response_section.find(".seg__text");

            // 判定：本訊息首見 → 起緩衝桶
            if (!thisObject.acquired_responses[message_id]) {
                thisObject.acquired_responses[message_id] = {};
            }

            // 種入（身份／全文／長度——畫面＋後端 attribute 同一把尺）
            thisObject.acquired_responses[message_id][response_id] = {
                response_id:    response_id,
                text:           j_response_content.text(),
                content_length: parseInt(j_response_section.attr("data-content_length"), 10) || 0,
            };
        });
    }

    // 按載入回應起 poll（有效對話（normal）＋有未完成（polling_count > 0）先起——回應直接拋入；其餘＝唔起）
    message_poll_detect(response_data) {
        // 判定：唔係有效對話（非 normal——錯 sid／本機空窗）→ 唔起
        if (response_data.chat_sid_state !== "normal") {
            return this;
        }

        // 判定：冇未完成訊息（polling_count ≤ 0）→ 睇有冇排隊；有＝即刻按正常派工流程放行
        if (response_data.polling_count <= 0) {
            // 判定：等待列有項目（空閒＋有排隊）→ 立即派工（同用戶撳掣同一 request）
            if (jQuery(".sc_composer__queue_list .queue_item").length > 0) {
                const thisObject = this;

                (async function () {
                    const queue_instance = await registry().sc_ai_chats().queue();
                    const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

                    await queue_dispatch_instance.queue_dispatch(response_data.chat_sid);

                    // 派工後即刻起 poll（正常輪詢接手——同平時送出一樣）
                    thisObject.message_poll(response_data.chat_sid);
                })();
            }

            return this;
        }

        // 起輪詢（入場＝該對話 sid）
        this.message_poll(response_data.chat_sid);
    }

    // 一輪輪詢（入場收「要 poll 邊個對話」→ 配對當前頁面（URL）→ 抓任務 → 更新回應內容 → 排下一輪）
    async poll_once(poll_chat_sid) {
        // 判定：上一輪仍在途 → 唔重入（SSE 通知同計時器可能撞）
        if (this.polling) {
            return;
        }

        this.polling = true;

        // registry 取值（靜態——直回單例）
        const chats = registry().sc_ai_chats();

        const thisObject = this;

        // 配對（發請求前——唔係獲取後）：要 poll 嘅對話 vs 當前瀏覽嗰版（URL——SSOT）——唔一致＝已切換／非當前 → 即停（唔發請求）
        const chat_instance = await chats.chat();

        const curr_chat_sid = chat_instance.get_chat_sid();

        // 判定：配對唔到（要 poll 嘅唔係當前頁面）→ 即停（唔發請求）
        if (poll_chat_sid !== curr_chat_sid) {
            thisObject.polling = false;
            thisObject.stop_poll_message();

            return;
        }

        // 判定：未選對話（sid 空——唔應該輪詢）→ 記錯即停（唔發請求；後端對空 sid 一律回 400）
        if (poll_chat_sid === "") {
            console.error("message_poll: chat_sid is empty, poll stopped");

            thisObject.polling = false;
            thisObject.stop_poll_message();

            return;
        }

        // 記住本輪對話（SSE 通知時用返同一個 sid 跑 poll）
        thisObject.poll_chat_sid = poll_chat_sid;

        // 橋接駁（req／queue act 橋——先攞 instance；連線交 handler：POST message_poll → 收到嘅資料派去本機側程序）
        const message_instance = await chats.message();
        const message_poll_instance = await message_instance.req().message_poll();

        const queue_instance = await chats.queue();
        const queue_list_instance     = await queue_instance.act().queue_list();
        const queue_dispatch_instance = await queue_instance.act().queue_dispatch();

        // 已獲取申報（per 訊息 per 段——response_id＋已收 mb 字元長度；增量協議：後台據此裁走已獲取部分）
        const acquired_payload = {};

        // 逐訊息建申報
        const acquired_message_ids = Object.keys(thisObject.acquired_responses);
        for (let message_index = 0; message_index < acquired_message_ids.length; message_index++) {
            const message_id = acquired_message_ids[message_index];
            const acquired_sections = thisObject.acquired_responses[message_id];

            acquired_payload[message_id] = {};

            // 逐段（response_id）建申報條目
            const acquired_response_ids = Object.keys(acquired_sections);
            for (let acquired_id_index = 0; acquired_id_index < acquired_response_ids.length; acquired_id_index++) {
                const acquired_response_id = acquired_response_ids[acquired_id_index];

                acquired_payload[message_id][acquired_response_id] = {
                    response_id:    acquired_sections[acquired_response_id].response_id,
                    content_length: acquired_sections[acquired_response_id].content_length,
                };
            }
        }

        await message_poll_instance.message_poll(poll_chat_sid, acquired_payload, {
            success: async function (result) {
                thisObject.polling = false;

                const tasks = result.tasks || [];

                // 隊列整排替換（後端渲染 UI——shift 出列即反映；唔逐條剔）
                // 判定：後端帶完整隊列 UI → 整排 bind（queue_list() 內含空值守衛）
                if (
                    result.html
                    &&  result.html.queue_list
                ) {
                    queue_list_instance.refresh_queue_list(result.html.queue_list);
                }

                const queue_count = jQuery(".sc_composer__queue_list .queue_item").length;

                // 判定：本輪有任務 → 逐條更新回應內容（畫面冇該訊息 wrapper ＝ 唔注入——update_response 自己判）
                if (tasks.length > 0) {
                    for (let task_index = 0; task_index < tasks.length; task_index++) {
                        const task = tasks[task_index];

                        thisObject.update_response(task);
                    }

                    // 訂閱 SSE（工作中訊息有 delta 即多跑一輪；bridge 死＝純 poll 照舊）
                    await thisObject.watch_stream(tasks);
                // 否則＝本輪冇任務 → 睇有冇排隊
                } else {
                    // 判定：冇排隊 → 冇嘢做：停止（一輪唯一續／停出口）
                    if (queue_count === 0) {
                        thisObject.stop_poll_message();
                        return;
                    }

                    // 判定：有排隊 → 發派工請求（進場；每拍重試——後端冪等）
                    queue_dispatch_instance.queue_dispatch(poll_chat_sid);
                }

                // 判定：SSE 期間有新內容 → 即刻再跑一輪（唔等 2 秒）
                if (thisObject.poll_pending) {
                    thisObject.poll_pending = false;
                    thisObject.poll_soon();

                    return;
                }

                // 仍有嘢做（本輪有任務／有排隊）→ 排下一輪
                thisObject.running_timer_id = setTimeout(
                    thisObject.poll_once.bind(thisObject, poll_chat_sid),
                    thisObject.interval,
                );
            },
            error: function () {
                thisObject.polling = false;

                // 失敗（null＝網路／ok:false）→ 停止輪詢（避免無限重試；日後由 health 偵測新 jobs 再啟動）
                thisObject.stop_poll_message();
            }
        });
    }

    // 更新回應內容（入口——食單一 task object）：依 status 分流（log/segments 兩子函數）
    // 畫面無該 message 的 wrapper → 唔處理（等下次重載全量渲染）
    async update_response(task) {
        const task_data = task.data || {};

        const message_instance = await registry().sc_ai_chats().message();

        let j_message_panel = message_instance.get_j_message_panel(task_data.message_id);

        // 判定：畫面無該 wrapper → 唔處理（等下次重載全量渲染）
        if (!j_message_panel.length) {
            return false;
        }

        // 判定：後端帶原始 FSM 狀態 → 同步（等待列 chips／停止目標判定用——render 時只係初始值）
        if (task.status) {
            j_message_panel.attr("data-status", task.status);
        }

        // 判定：後端帶工作中標記 → 同步（主按鈕兩態判準——JS 零 status 清單）
        if (task.working !== undefined) {
            j_message_panel.attr("data-working", task.working);
        }

        // 狀態變 → 刷新主按鈕兩態（prompt 域唯一決策者——跨域橋接駁）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_toggle_instance = await prompt_instance.act().prompt_send_button_toggle();

        prompt_send_button_toggle_instance.prompt_send_button_toggle();

        // 有資料就吐（各 updater 自帶「無資料 → 不動」）
        this.update_response_log(task, j_message_panel);

        await this.update_response_sections(task, j_message_panel);

        // 權限提示（pending 先出——後端砌好 HTML；已決（task.permission 空）→ 收起）
        this.update_response_permission(task, j_message_panel);

        // 判定：已完結（後台 working="0"）→ 全部段立即寫完（停寫——writer.complete）
        if (task.working === "0") {
            this.force_complete_all_thinking(j_message_panel);
        }

        return true;
    }

    // 權限提示更新（pending：未出過 → 插卡；已決（task.permission 空）→ 收卡）
    update_response_permission(task, j_message_panel) {
        const permission = task.permission || null;

        const j_agent_message  = j_message_panel.find(".agent_message");
        const j_existing_prompt = j_agent_message.find(".permission_prompt").last();

        // 判定：本輪有 pending 請求 → 出卡
        if (permission) {
            const request_id = String(permission.request_id || "");

            // 判定：已係同一請求嘅卡 → 唔重插（卡內容靜態——後端一次砌好）
            if (j_existing_prompt.length && j_existing_prompt.attr("data-request_id") === request_id) {
                return;
            }

            // 判定：有卡 HTML → 換入／插入（後端砌——前端只 insert）
            if (permission.html) {
                if (j_existing_prompt.length) {
                    j_existing_prompt.replaceWith(permission.html);
                } else {
                    j_agent_message.append(permission.html);
                }
            }

            return;
        }

        // 判定：已無 pending（已決／超時）→ 有卡就收
        if (j_existing_prompt.length) {
            j_existing_prompt.remove();
        }
    }

    // log 子函數：活動日誌——全部行保留（新行 prepend 落頂；容器 column＋overflow hidden 由 CSS 負責）
    update_response_log(task, j_message_panel) {
        const log_entries = task.logs || [];

        // 活動列骨架（log_message／activity_monitor）由後端砌（即時訊息一律帶）——前端唔補建
        const j_activity_monitor = j_message_panel.find(".activity_monitor");
        const j_activity_lines = j_message_panel.find(".activity_lines");

        // 判定：未完結（後台 working="1"）→ 顯示 loading dots
        if (task.working !== "0") {
            j_activity_monitor.addClass("loading");

            // 總用時 counter 元素（工作緊——更新基準；ticker 每秒據此遞增）
            const j_total_elapsed = j_message_panel.find(".total_elapsed");

            // 判定：有 counter 元素 → 更新
            if (j_total_elapsed.length) {
                // 判定：後端帶咗秒數 → 落基準（秒數＋當下時間戳）＋現值
                if (task.elapsed_sec !== undefined) {
                    j_total_elapsed.attr("data-elapsed_sec", task.elapsed_sec);
                    j_total_elapsed.attr("data-elapsed_ts", Date.now());
                    j_total_elapsed.find(".total_elapsed_time").text(this.format_elapsed_sec(task.elapsed_sec));
                }
            }
        // 判定：已完結 → 熄 loading；完成（success="1"）再多一步淡出 log 行
        } else {
            j_activity_monitor.removeClass("loading");
        }

        // activity_lines：全部保留——只追加未顯示嘅行（容器 column-reverse：追加＝顯示喺最頂）
        if (log_entries.length > 0) {
            const old_log_index = ~~j_activity_monitor.attr("data-log_index");

            // 判定：冇新行 → 停
            if (old_log_index >= log_entries.length)
                return;

            // 只顯示最新一條（uid＝log 索引；同 uid 已存在 → 唔動）
            const log_entry = log_entries[log_entries.length - 1];
            const log_uid = log_entry.activity_uid || "";

            // 判定：同 uid 已存在 → 唔需要替換
            if (j_activity_lines.find("[data-activity_uid='" + log_uid + "']").length) {
                return;
            }

            const j_new_line = jQuery("<div class='activity_line'></div>");
            j_new_line.addClass("activity_level_" + log_entry.level);
            j_new_line.attr("data-activity_uid", log_uid);
            j_new_line.attr("data-log_event", log_entry.event || "");
            j_new_line.attr("data-log_status", (log_entry.level || "").toLowerCase());
            j_new_line.text(log_entry.message);

            // 判定：已有行 → 直接取代；未有 → 插入
            const j_existing_line = j_activity_lines.find(".activity_line");
            if (j_existing_line.length) {
                j_existing_line.first().replaceWith(j_new_line);
            } else {
                j_activity_lines.append(j_new_line);
            }

            j_activity_monitor.attr("data-log_index", log_entries.length);
        }
    }

    // 回應段統一渲染（thinking／result 同一套處理）：逐段走 write_response_section（增量接緩衝 → 段元素 get-or-create → writer get-or-create → write 打字）
    async update_response_sections(task, j_message_panel) {
        const task_data = task.data || {};

        const message_id = task_data.message_id || 0;

        const response_sections = task_data.responses || [];
        if (response_sections.length === 0) {
            return;
        }
        const j_agent_message = j_message_panel.find(".agent_message");


        // 逐段處理（每段一個 writer——thinking／result 皆打字；writer 掛在段元素）
        for (let section_index = 0; section_index < response_sections.length; section_index++) {
            const response_section = response_sections[section_index];
            await this.write_response_section(j_agent_message, response_section, message_id);
        }
    }

    // 單段打字：增量接上已獲取緩衝 → 段元素不存在才建立 → writer 不存在才建立（set_options）→ write 全文（自動接續印）
    async write_response_section(j_agent_message, response_section, message_id) {
        // 動態載入（registry——用先載）
        const markdown = await registry().sc_markdown();

        // 橋接駁（同域 act——新方式）
        const message_instance_writer = await registry().sc_ai_chats().message();
        const message_writer_instance = await message_instance_writer.act().message_writer();

        const response_id = response_section.response_id;

        // 已獲取緩衝（per 訊息 per 段——response_id 鍵）：payload content＝增量 → 接上緩衝成全文；長度由後端帶返（mb 字元——同一把尺）

        // 判定：本訊息首見 → 起緩衝桶
        if (!this.acquired_responses[message_id]) {
            this.acquired_responses[message_id] = {};
        }

        let acquired_entry = this.acquired_responses[message_id][response_id];

        // 判定：首見（無緩衝）→ 起新條
        if (!acquired_entry) {
            acquired_entry = {
                response_id:    response_id,
                text:           "",
                content_length: 0,
            };
            this.acquired_responses[message_id][response_id] = acquired_entry;
        }

        acquired_entry.text += response_section.content;

        // 長度＝後端話事（段全文 mb 字元長——每輪帶返；前端唔自己計）
        if (response_section.content_length !== undefined) {
            acquired_entry.content_length = response_section.content_length;
        }

        // 判定：後端帶 response_id → 記低（申報身份）
        if (response_section.response_id) {
            acquired_entry.response_id = response_section.response_id;
        }

        // 全文＝緩衝（writer 食全文——自動接續）
        const full_content_text = acquired_entry.text;

        // 段型正規化（payload 變體 → canonical token——唯一入口；indexed「response.{n}.{kind}」取尾段、其餘查表）
        let section_token = response_section.type;

        // 判定：indexed 格式（response.{n}.{kind}）→ 取尾段（thinking／answer）
        if (response_section.type.indexOf("response.") === 0) {
            const section_token_parts = response_section.type.split(".");
            section_token = section_token_parts[section_token_parts.length - 1];
        }

        // 段型 token 表（變體 → canonical：indexed 尾段名／扁平 response 系／summarize 系）
        const section_token_map = {
            thinking:           "thinking",
            answer:             "result",
            response_thinking:  "thinking",
            response_result:    "result",
            response_answer:    "result",
            summarize_thinking: "sum_thinking",
            summarize_result:   "sum_result",
        };
        section_token = section_token_map[section_token];

        // 1. 段元素：搵（data-response_id 直配——計時格 .response_elapsed 除外；未存在＝新段：收養送出嗰陣空 id 嗰件，否則插後端骨架）
        const section_element_selector_map = {
            thinking:     ".seg--thinking",
            result:       ".seg--result",
            sum_thinking: ".seg--sum_thinking",
            sum_result:   ".seg--sum_result",
        };

        let j_response_section = j_agent_message.find("[data-response_id='" + response_id + "']").not(".response_elapsed").last();

        // 判定：搵唔到（送出窗口——response 列未開、id 空）→ 收養同型空 id 嗰件（補返真 id）
        if (!j_response_section.length) {
            const j_empty_id_section = j_agent_message.find(section_element_selector_map[section_token] + "[data-response_id='']").last();

            // 判定：有得收養 → 補 id
            if (j_empty_id_section.length) {
                j_empty_id_section.attr("data-response_id", response_id);
                j_response_section = j_empty_id_section;
            }
        }

        // 判定：真係新段（容器未存在）→ 插入後端骨架（骨架出世已帶真 id）
        if (!j_response_section.length) {
            // 判定：無骨架（非即時訊息）→ 唔動
            if (!response_section.skeleton_html) {
                return null;
            }

            j_agent_message.append(response_section.skeleton_html);
            j_response_section = j_agent_message.find("[data-response_id='" + response_id + "']").not(".response_elapsed").last();
        }

        // 段盒（class 開關目標——thinking／result＝命中嗰件自己；summarize＝命中內層 div → 升返上容器）
        let j_section_box = j_response_section;

        // 判定：summarize 段（命中內層）→ 盒＝最近嗰個 .seg--sum
        if (section_token.indexOf("sum_") === 0) {
            j_section_box = j_response_section.closest(".seg--sum");
        }

        // 2. 內容元素（依 token——summarize 兩型同容器、不同 DIV）
        const content_selector_map = {
            thinking:     "[data-text='thinking']",
            result:       "[data-text='result']",
            sum_thinking: "[data-text='sum_thinking']",
            sum_result:   "[data-text='sum_result']",
        };
        const content_selector = content_selector_map[section_token];
        const j_response_content = j_response_section.find(content_selector).addBack(content_selector);

        // 3. writer：內容元素上無 instance 才建立（set_options 一次——時長 2000ms、寫完回調）
        let writer_instance = j_response_content[0].writer_instance;
        if (!writer_instance) {
            writer_instance = message_writer_instance.get_writer_instance();
            j_response_content[0].writer_instance = writer_instance;

            // markdown 政策：只有 result 段做 markdown（thinking／summarize 一律純文字）
            const is_markdown_section = (section_token === "result");

            const writer_options = {
                element: j_response_content[0],
                target_duration_dec: 2000,
                on_complete: async function () {
                    // 寫完 → 退出生成中模式（標記除）
                    j_section_box.removeClass("is-working");
                },
            };

            // 判定：result 段 → 帶 markdown 渲染回調（其餘＝純文字逐字）
            if (is_markdown_section) {
                writer_options.render_html = function (raw_text) { return markdown.render_streaming(raw_text); };
            }

            writer_instance.set_options(writer_options);

            // 判定：result 段 → markdown 輸出容器（CSS white-space: normal——覆蓋純文字 pre-wrap）
            if (is_markdown_section) {
                j_response_content.addClass("sc_markdown_body");
            }
        }

        // 保留原始 markdown 文字（終態最終渲染用——全文＝緩衝；原文是唯一真相）
        j_response_content[0].sc_raw_text = full_content_text;

        // 段長 attribute 跟返（每輪更新——下一輪申報／重讀一致）
        j_response_section.attr("data-content_length", acquired_entry.content_length);

        // 段狀態 attribute 跟返（每輪更新——前台節點狀態同後台一致；初值由後端 render）
        if (response_section.status !== undefined) {
            j_response_section.attr("data-response_status", response_section.status);
        }

        // 段用時計時器：後端每輪帶基準秒＋段狀態——生成中校正基準（跳秒）；completed → 凍結（ts=0 停跳）
        const j_response_elapsed = j_agent_message.find(".response_elapsed[data-response_id='" + response_id + "']");

        // 判定：段有計時器元素（後端已出）→ 更新
        if (j_response_elapsed.length) {
            // 判定：本輪有帶段用時 → 落基準＋現值
            if (response_section.elapsed_sec !== undefined) {
                const is_section_completed = (response_section.status === "completed");
                j_response_elapsed.attr("data-elapsed_sec", response_section.elapsed_sec);
                j_response_elapsed.attr("data-elapsed_ts", is_section_completed ? 0 : Date.now());
                j_response_elapsed.text(this.format_elapsed_sec(response_section.elapsed_sec));
            }
        }

        // 4. 寫入最新全文（writer 自動接續——補齊差距）；thinking 段標記生成中（.is-working）
        if (section_token === "thinking") {
            j_response_section.addClass("is-working");
        }
        // 判定：有內容 → 解除隱藏（空段唔顯示——骨架出世帶 hidden）
        if (full_content_text !== "") {
            j_section_box.removeClass("hidden");
        }
        // 判定：有新增增量 → 餵全文（writer 自動接續印；冇新增＝唔動避免空轉）
        if (response_section.content !== "") {
            writer_instance.write(full_content_text);
        }



        return writer_instance;
    }

    // 終態停寫：該 panel 全部段（thinking＋result）立即寫完（writer.complete——跨訊息安全：走 DOM）
    async force_complete_all_thinking(j_message_panel) {
        // 動態載入（registry——用先載）
        const markdown = await registry().sc_markdown();

        // 終態：退出生成中模式（標記除）
        j_message_panel.find(".seg--thinking").removeClass("is-working");

        // writer 掛在「內容元素」上（summarize 兩型各自一個 DIV）——先收尾（complete 會覆寫畫面）
        const j_response_contents = j_message_panel.find("[data-text='thinking'], [data-text='result'], [data-text='sum_thinking'], [data-text='sum_result']");
        j_response_contents.each(function (index, content_element) {
            if (content_element.writer_instance) {
                content_element.writer_instance.complete();
            }
        });

        // 終態：內容 markdown 化（marked → DOMPurify → HTML；mermaid 僅此時渲染）——必須在 writer 收尾之後
        // 只有 result 段做（thinking／summarize 一律純文字）；每則訊息只做一次（終態 poll 每 2 秒重複送 → 用旗標擋掉重複渲染）
        const j_markdown_targets = j_message_panel.find("[data-text='result']");
        j_markdown_targets.each(function (index, target_element) {
            // 判定：已最終渲染過 → 跳過（避免重複耗費）
            if (target_element.sc_rendered) {
                return;
            }

            target_element.sc_rendered = true;
            markdown.render_element(jQuery(target_element));
        });

        // 終態收結（render_status 軸——每則一次）：冇 mermaid＝md 完即 ready；有 mermaid＝交排隊（ready 由 rendered_message_mermaid 掛鈎收結）
        const current_render_status = j_message_panel.attr("data-render_status");

        // 判定：已推過（markdown／ready——終態每 2 秒重複送）→ 跳過（免重複派排隊）
        if (["markdown", "ready"].includes(current_render_status)) {
            return;
        }

        // 動態載入（registry——用先載）
        const message_instance = await registry().sc_ai_chats().message();
        const message_render_instance = await message_instance.act().message_render();

        // 判定：該條 render_status（冇 mermaid＝ready；有 mermaid＝markdown 等 mermaid）
        await message_render_instance.refresh_render_status(j_message_panel);

        // 判定：有 mermaid → 交排隊渲染（ready 由 rendered_message_mermaid 掛鈎收結）
        const contains = message_render_instance.message_contains(j_message_panel);
        if (contains.mermaid) {
            message_render_instance.render_single_mermaid(j_message_panel);
        }
    }
}

export const message_poll = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_poll());
