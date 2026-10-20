// sc_app/shared/jsmods/sc_alert/sc_alert.js —— 通用訊息彈窗模組（ESM 模組；Alert Modal ＋ Toast）
// 載入：hkai/jsmods/bootstrap.js → registry.js → shared/jsmods/shared.js（ESM 鏈——自動載入）
// 取用：registry().shared().sc_alert()——例：
//   sc_alert_ins.warning("標題", "訊息").then(function (close_reason) { ... })
//   sc_alert_ins.toast({ type: "warning", message: "Token 快到上限" })
//   sc_alert_ins.notify(backend_json)              // 直食後端 JSON
// 職責：統一全 app 提示視窗——六種類型（error／warning／success／primary／dark／light）：
//   ① show(options) → Promise（關閉原因：confirm／cancel／x／backdrop／escape／auto／clear）
//   ② 六個快捷方法（error／warning／success／primary／dark／light——參數：({...}) ／（"訊息"）／（標題, 訊息, options））
//   ③ toast(options) → 右上角通知（堆疊／倒數進度條／hover 暫停；duration 0 = 不自動關）
//   ④ notify(json) ⑤ guess(text)（關鍵字分類）⑥ clear()／dismiss_toasts()
// 樣式：同目錄 sc_alert.css（class 結構＝sc_ 前綴＋snake：sc_alert_*／sc_toast_*——同 sc_btn／sc_form／sc_empty_state 慣例）

// 六種類型（對外 API 名——快捷方法同名）
const ALERT_TYPES = ["error", "warning", "success", "primary", "dark", "light"];

// 類型別名（normalize_type 用——相容常見叫法：info／danger／ok…）
const ALERT_TYPE_ALIASES = {
    info: "primary", notice: "warning", danger: "error", fail: "error", failure: "error",
    ok: "success", secondary: "light", neutral: "light", muted: "dark", system: "dark",
    message: "primary", default: "light"
};

// 各類型預設標題（未傳 title 時用）
const ALERT_DEFAULT_TITLES = {
    error: "錯誤 · Error",
    warning: "警告 · Warning",
    success: "成功 · Success",
    primary: "通知 · Info",
    dark: "系統訊息 · System",
    light: "提示 · Tip"
};

// SVG 外框組裝（各類型圖示共用同一外框——線條風格）
function build_icon_svg(svg_inner) {
    return "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' " +
        "stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>" + svg_inner + "</svg>";
}

// 類型圖示（六款）
const ALERT_ICONS = {
    error: build_icon_svg("<path d='M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86L7.86 2z'/><line x1='12' y1='8' x2='12' y2='12'/><line x1='12' y1='16' x2='12.01' y2='16'/>"),
    warning: build_icon_svg("<path d='m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/>"),
    success: build_icon_svg("<circle cx='12' cy='12' r='10'/><path d='m9 12 2 2 4-4'/>"),
    primary: build_icon_svg("<circle cx='12' cy='12' r='10'/><line x1='12' y1='16' x2='12' y2='12'/><line x1='12' y1='8' x2='12.01' y2='8'/>"),
    dark: build_icon_svg("<path d='M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9'/><path d='M10.3 21a1.94 1.94 0 0 0 3.4 0'/>"),
    light: build_icon_svg("<path d='M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5'/><line x1='9' y1='18' x2='15' y2='18'/><line x1='10' y1='22' x2='14' y2='22'/>")
};

// 空值判定（undefined／null——多處共用）
function is_empty_value(check_value) {
    // undefined → 空
    if (check_value === undefined) {
        return true;
    }

    return check_value === null;
}

// 純物件判定（typeof object 且非 null——多處共用）
function is_plain_object(check_value) {
    // null → 非物件
    if (check_value === null) {
        return false;
    }

    return typeof check_value === "object";
}

// HTML 轉義（訊息文字防注入；html:true 才跳過）
function escape_html(text_value) {
    const safe_text = String(is_empty_value(text_value) ? "" : text_value);
    const escape_map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

    return safe_text.replace(/[&<>"']/g, function (match_char) {
        return escape_map[match_char];
    });
}

// 依關鍵字猜類型（error → warning → success → 其餘 primary——檢查順序：安全優先）
function guess_type(text_value) {
    const safe_text = String(is_empty_value(text_value) ? "" : text_value);

    // 錯誤關鍵字（traceback／fatal／失敗…）→ error
    if (/fatal|traceback|exception|critical|panic|segfault|uncaught|fail\w*|error\w*|\bdenied\b|\brefused\b|timed?\s?out|exit (code|status) \d+|失敗|錯誤|無法/i.test(safe_text)) {
        return "error";
    }

    // 警告關鍵字（warning／deprecated／注意…）→ warning
    if (/warn\w*|deprecated|caution|obsolete|\bnotice\b|警告|注意/i.test(safe_text)) {
        return "warning";
    }

    // 成功關鍵字（success／完成／已送出…）→ success
    if (/success\w*|succeeded|completed?|\bdone\b|\bpassed\b|\bcommit\w*|applied|\bok\b|成功|完成|已送出/i.test(safe_text)) {
        return "success";
    }

    return "primary";
}

// 內容似程式輸出判定（traceback／fatal error 等——自動用等寬區塊顯示）
function looks_like_code(text_value) {
    return /traceback|fatal error|deprecated:|warning:|error:|exception|on line \d+|line \d+(, in |\))/i.test(text_value);
}

// 類型正規化（六種類型 → 原樣；別名 → 對應；其餘 → primary）
function normalize_type(type_value) {
    const raw_type = String(is_empty_value(type_value) ? "" : type_value).toLowerCase().trim();

    // 六種類型之一 → 原樣
    if (ALERT_TYPES.indexOf(raw_type) !== -1) {
        return raw_type;
    }

    // 別名表命中 → 對應類型
    if (ALERT_TYPE_ALIASES[raw_type] !== undefined) {
        return ALERT_TYPE_ALIASES[raw_type];
    }

    return "primary";
}


class _sc_alert {
    constructor() {
        this.message_queue = [];    // 等待顯示的 modal（{ options, resolve }——多則自動排隊）
        this.active_modal = null;   // 目前開啟中（{ close: function }）
        this.live_toasts = [];      // 存活中的 toast handle（{ el, dismiss }）
    }

    /* ==================== Modal ==================== */

    // 顯示 modal（多則自動排隊）——回傳 Promise（resolve 值＝關閉原因）
    show(options) {
        const thisObject = this;
        const alert_options = this.normalize_options(options);

        return new Promise(function (resolve) {
            thisObject.message_queue.push({ options: alert_options, resolve: resolve });

            // 無開啟中 modal → 立即渲染下一則
            if (thisObject.active_modal === null) {
                thisObject.render_next();
            }
        });
    }

    // 六個快捷方法（error／warning／success／primary／dark／light）
    error(first_arg, second_arg, third_arg) {
        return this.show_shortcut("error", first_arg, second_arg, third_arg);
    }
    warning(first_arg, second_arg, third_arg) {
        return this.show_shortcut("warning", first_arg, second_arg, third_arg);
    }
    success(first_arg, second_arg, third_arg) {
        return this.show_shortcut("success", first_arg, second_arg, third_arg);
    }
    primary(first_arg, second_arg, third_arg) {
        return this.show_shortcut("primary", first_arg, second_arg, third_arg);
    }
    dark(first_arg, second_arg, third_arg) {
        return this.show_shortcut("dark", first_arg, second_arg, third_arg);
    }
    light(first_arg, second_arg, third_arg) {
        return this.show_shortcut("light", first_arg, second_arg, third_arg);
    }

    // 快捷方法參數收斂（{...} ／單字串＝訊息 ／標題＋訊息＋options）
    show_shortcut(alert_type, first_arg, second_arg, third_arg) {
        let shortcut_options = {};

        // 第一參數為物件 → 直接當 options
        if (is_plain_object(first_arg)) {
            shortcut_options = first_arg;
        }
        // 只有一個參數（無第二、三）→ 視為訊息字串
        else if (is_empty_value(second_arg) && is_empty_value(third_arg)) {
            shortcut_options = { message: first_arg };
        }
        // 標題＋訊息
        else {
            shortcut_options = { title: first_arg, message: second_arg };
        }

        // 第三參數為物件 → 補進未定義欄位
        if (is_plain_object(third_arg)) {
            for (const option_key in third_arg) {
                // 已定義欄位 → 不覆蓋
                if (shortcut_options[option_key] !== undefined) {
                    continue;
                }
                shortcut_options[option_key] = third_arg[option_key];
            }
        }

        shortcut_options.type = alert_type;

        return this.show(shortcut_options);
    }

    // 選項正規化（字串＝訊息；未給 type 依內容猜；補齊預設值）
    normalize_options(options) {
        let raw_options = options;

        // 傳字串 → 視為訊息
        if (typeof raw_options === "string") {
            raw_options = { message: raw_options };
        }

        // 非物件（undefined／null／數字等）→ 空物件
        if (is_plain_object(raw_options) === false) {
            raw_options = {};
        }

        // 類型：明確指定優先；未指定 → 依 message／title 關鍵字猜
        let alert_type = raw_options.type;

        // 未指定 type → 猜
        if (is_empty_value(alert_type)) {
            let guess_source = raw_options.message;

            // message 空 → 改用 title 猜
            if (is_empty_value(guess_source)) {
                guess_source = raw_options.title;
            }

            alert_type = guess_type(guess_source);
        }

        const normalized_type = normalize_type(alert_type);

        // 標題：未給 → 依類型預設
        let alert_title = raw_options.title;

        // 未給 title → 用類型預設標題
        if (is_empty_value(alert_title)) {
            alert_title = ALERT_DEFAULT_TITLES[normalized_type];
        }

        // 訊息本體（未給 → 空字串）
        let alert_message = raw_options.message;

        // 未給 message → 空字串
        if (is_empty_value(alert_message)) {
            alert_message = "";
        }

        // 確認鈕文字（未給 → 確定）
        let confirm_text = raw_options.confirm_text;

        // 未給 confirm_text → 預設「確定」
        if (is_empty_value(confirm_text)) {
            confirm_text = "確定";
        }

        // 取消鈕文字（未給 → null＝不顯示取消鈕）
        let cancel_text = raw_options.cancel_text;

        // 未給 cancel_text → null（不顯示）
        if (is_empty_value(cancel_text)) {
            cancel_text = null;
        }

        // 自動關閉秒數 → 毫秒（0／未給 → 不自動關）
        let auto_close_ms = 0;

        // 秒數 > 0 → 轉毫秒
        if (Number(raw_options.auto_close) > 0) {
            auto_close_ms = Math.round(Number(raw_options.auto_close) * 1000);
        }

        // 視窗最大寬度（px；未給／非正數 → 460）
        let window_width = parseInt(raw_options.width, 10);

        // 非正數 → 預設 460
        if (!(window_width > 0)) {
            window_width = 460;
        }

        // 關閉方式開關（=== false 才關；未給 → 開）
        let backdrop_close = true;
        if (raw_options.backdrop_close === false) {
            backdrop_close = false;
        }

        let escape_close = true;
        if (raw_options.escape_close === false) {
            escape_close = false;
        }

        let show_close = true;
        if (raw_options.show_close === false) {
            show_close = false;
        }

        // 回呼（只有 function 才收）
        let on_open = null;
        if (typeof raw_options.on_open === "function") {
            on_open = raw_options.on_open;
        }

        let on_close = null;
        if (typeof raw_options.on_close === "function") {
            on_close = raw_options.on_close;
        }

        return {
            type: normalized_type,
            title: alert_title,
            message: alert_message,
            html: raw_options.html === true,
            code: raw_options.code,
            confirm_text: confirm_text,
            cancel_text: cancel_text,
            auto_close_ms: auto_close_ms,
            backdrop_close: backdrop_close,
            escape_close: escape_close,
            show_close: show_close,
            width: window_width,
            on_open: on_open,
            on_close: on_close
        };
    }

    // 佇列取下一個並渲染（關閉後會再呼叫——串接排隊）
    render_next() {
        const next_item = this.message_queue.shift();

        // 佇列空 → 不做
        if (next_item === undefined) {
            return;
        }

        this.render_modal(next_item);
    }

    // 建 modal DOM＋綁事件（關閉時 resolve(item.resolve)——七種關閉原因）
    render_modal(item) {
        const thisObject = this;
        const alert_options = item.options;

        // 遮罩容器（fixed 置中——建立即 flex；進場靠 .visible 過渡）
        const j_overlay = document.createElement("div");
        j_overlay.className = "sc_alert_overlay";
        j_overlay.setAttribute("data-type", alert_options.type);

        // 標題（html:true → 原樣；否則轉義）
        let title_html = "";
        if (alert_options.html === true) {
            title_html = String(alert_options.title);
        } else {
            title_html = escape_html(alert_options.title);
        }

        // 訊息本體（可能為空——空則唔加區塊）
        const message_html = this.build_message_html(alert_options);

        // 關閉鈕（show_close 開才出）
        let close_button_html = "";
        if (alert_options.show_close === true) {
            close_button_html = "<button type='button' class='sc_alert_close' data-alert_role='close' aria-label='關閉'>✕</button>";
        }

        // 取消鈕（cancel_text 有值才出）
        let cancel_button_html = "";
        if (alert_options.cancel_text !== null) {
            cancel_button_html = "<button type='button' class='sc_btn' data-alert_role='cancel'>" +
                escape_html(alert_options.cancel_text) + "</button>";
        }

        // 倒數列（auto_close 開才出）
        let timer_html = "";
        if (alert_options.auto_close_ms > 0) {
            timer_html = "<div class='sc_alert_timer' aria-hidden='true'><span class='sc_alert_timer_bar'></span></div>";
        }

        // 視窗組裝（陣列收集再一次 join——同 app HTML 產出慣例）
        const window_html = [];
        window_html.push("<div class='sc_alert_window' role='alertdialog' aria-modal='true' style='max-width: " + alert_options.width + "px'>");
            window_html.push("<header class='sc_alert_header'>");
                window_html.push("<div class='sc_alert_icon'>" + ALERT_ICONS[alert_options.type] + "</div>");
                window_html.push("<div class='sc_alert_heading'>");
                    window_html.push("<div class='sc_alert_title'>" + title_html + "</div>");

                    // 有訊息才加內容區塊
                    if (message_html !== "") {
                        window_html.push("<div class='sc_alert_message'>" + message_html + "</div>");
                    }
                window_html.push("</div>");
                window_html.push(close_button_html);
            window_html.push("</header>");
            window_html.push("<footer class='sc_alert_footer'>");
                window_html.push(cancel_button_html);
                window_html.push("<button type='button' class='sc_btn sc_alert_confirm' data-alert_role='confirm'>" +
                    escape_html(alert_options.confirm_text) + "</button>");
            window_html.push("</footer>");
            window_html.push(timer_html);
        window_html.push("</div>");

        // 半透明底（click 關閉——目標為 mask 本身先算）
        j_overlay.innerHTML = "<div class='sc_alert_mask'></div>" + window_html.join("");

        document.body.appendChild(j_overlay);
        document.documentElement.classList.add("sc_alert_lock");

        // 下一幀加 .visible（觸發進場過渡）
        requestAnimationFrame(function () {
            j_overlay.classList.add("visible");
        });

        let close_done = false;
        let auto_close_timer_id = null;

        // 關閉（單一出口——每則 modal 只關一次）
        function close_modal(close_reason) {
            // 已關過 → 不重複
            if (close_done === true) {
                return;
            }
            close_done = true;

            // 有倒數計時器 → 清掉
            if (auto_close_timer_id !== null) {
                clearTimeout(auto_close_timer_id);
                auto_close_timer_id = null;
            }

            document.removeEventListener("keydown", handle_keydown, true);
            j_overlay.classList.remove("visible");

            // 佇列空 → 解鎖頁面捲動
            if (thisObject.message_queue.length === 0) {
                document.documentElement.classList.remove("sc_alert_lock");
            }

            // 退場過渡完才移出 DOM
            setTimeout(function () {
                // 仲喺 DOM 先移除
                if (j_overlay.parentNode !== null) {
                    j_overlay.parentNode.removeChild(j_overlay);
                }
            }, 190);

            // on_close 回呼（不阻斷關閉流程）
            if (alert_options.on_close !== null) {
                try {
                    alert_options.on_close(close_reason);
                } catch (close_callback_error) {
                    console.error("sc_alert on_close failed", close_callback_error.message);
                }
            }

            item.resolve(close_reason);
            thisObject.active_modal = null;

            // 稍等再渲染下一則（等退場）
            setTimeout(function () {
                thisObject.render_next();
            }, 60);
        }

        // 鍵盤（Esc 關閉／Enter 確認——焦點唔喺視窗內先算）
        function handle_keydown(key_event) {
            // Esc → 關閉（選項允許時）
            if (key_event.key === "Escape") {
                // escape_close 關 → 不處理
                if (alert_options.escape_close === true) {
                    key_event.preventDefault();
                    close_modal("escape");
                }
                return;
            }

            // Enter → 確認
            if (key_event.key === "Enter") {
                const j_focused = document.activeElement;
                let focus_inside = false;

                // 有焦點元素先查係唔係喺視窗內
                if (j_focused !== null) {
                    focus_inside = j_overlay.contains(j_focused);
                }

                // 焦點唔喺視窗內（如 body）→ Enter 當確認
                if (focus_inside === false) {
                    key_event.preventDefault();
                    close_modal("confirm");
                }
            }
        }
        document.addEventListener("keydown", handle_keydown, true);

        // 撳半透明底 → 關閉（backdrop_close 允許時）
        j_overlay.addEventListener("mousedown", function (mouse_event) {
            const j_target = mouse_event.target;

            // 只認 mask 本體（視窗內嘅點擊唔算）
            if (j_target.classList.contains("sc_alert_mask") === false) {
                return;
            }

            // backdrop_close 關 → 不處理
            if (alert_options.backdrop_close === false) {
                return;
            }

            close_modal("backdrop");
        });

        // 按鈕分派（confirm／cancel／close——委派喺視窗根層）
        j_overlay.addEventListener("click", function (click_event) {
            const j_clicked = click_event.target.closest("[data-alert_role]");

            // 非標記元素 → 不做
            if (j_clicked === null) {
                return;
            }

            const alert_role = j_clicked.getAttribute("data-alert_role");

            if (alert_role === "confirm") {
                close_modal("confirm");
                return;
            }

            if (alert_role === "cancel") {
                close_modal("cancel");
                return;
            }

            if (alert_role === "close") {
                close_modal("x");
                return;
            }
        });

        // 自動關閉（倒數列 transition 由 CSS 執行）
        if (alert_options.auto_close_ms > 0) {
            const j_timer_bar = j_overlay.querySelector(".sc_alert_timer_bar");

            // 有倒數列 → 啟動寬度 transition
            if (j_timer_bar !== null) {
                j_timer_bar.style.transitionDuration = alert_options.auto_close_ms + "ms";
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        j_timer_bar.setAttribute("data-state", "run");
                    });
                });
            }

            auto_close_timer_id = setTimeout(function () {
                close_modal("auto");
            }, alert_options.auto_close_ms);
        }

        // 預設聚焦確認鈕（鍵盤即用）
        const j_confirm_button = j_overlay.querySelector("[data-alert_role='confirm']");
        if (j_confirm_button !== null) {
            j_confirm_button.focus();
        }

        this.active_modal = { close: close_modal };

        // on_open 回呼（不阻斷顯示流程）
        if (alert_options.on_open !== null) {
            try {
                alert_options.on_open();
            } catch (open_callback_error) {
                console.error("sc_alert on_open failed", open_callback_error.message);
            }
        }
    }

    // 訊息內容 HTML（html:true → 原樣；code 區塊判定；換行轉 <br>）
    build_message_html(alert_options) {
        const message_text = String(alert_options.message);

        // html 模式 → 原樣輸出（呼叫端自負 XSS 責任）
        if (alert_options.html === true) {
            return message_text;
        }

        // 空訊息 → 空字串
        if (message_text === "") {
            return "";
        }

        // 等寬區塊：明確指定優先，未指定才依內容關鍵字
        let use_code_block = false;
        if (alert_options.code === true) {
            use_code_block = true;
        }

        // 未明確指定 → 依內容判定
        if (is_empty_value(alert_options.code)) {
            use_code_block = looks_like_code(message_text);
        }

        // 等寬區塊（traceback／fatal 類）
        if (use_code_block === true) {
            return "<pre class='sc_alert_code'>" + escape_html(message_text) + "</pre>";
        }

        return escape_html(message_text).replace(/\n/g, "<br>");
    }

    // 清空佇列並關閉目前 modal（關閉原因 'clear'；佇列中未顯示嘅每則照樣 resolve——不留 pending Promise）
    clear() {
        // 逐則剔出並 resolve
        while (this.message_queue.length > 0) {
            const queued_item = this.message_queue.shift();
            queued_item.resolve("clear");
        }

        // 有開啟中 modal → 即刻關
        if (this.active_modal !== null) {
            this.active_modal.close("clear");
        }
    }

    /* ==================== Toast ==================== */

    // 顯示 toast（右上角堆疊——回傳 handle：{ el, dismiss }）
    toast(options) {
        const thisObject = this;
        let raw_options = options;

        // 傳字串 → 視為訊息
        if (typeof raw_options === "string") {
            raw_options = { message: raw_options };
        }

        // 非物件 → 空物件
        if (is_plain_object(raw_options) === false) {
            raw_options = {};
        }

        // 類型：未指定 → 依 message 關鍵字猜
        let toast_type_value = raw_options.type;

        // 未指定 type → 猜
        if (is_empty_value(toast_type_value)) {
            toast_type_value = guess_type(raw_options.message);
        }

        const toast_type = normalize_type(toast_type_value);

        const j_stack = this.ensure_toast_stack();

        // 顯示時長（毫秒；未給 → 4000；0 → 不自動關）
        let toast_duration_ms = 4000;
        if (is_empty_value(raw_options.duration) === false) {
            toast_duration_ms = Math.round(Number(raw_options.duration) || 0);
        }

        // 標題（有值才出）
        let toast_title_html = "";
        if (is_empty_value(raw_options.title) === false) {
            toast_title_html = "<div class='sc_toast_title'>" + escape_html(raw_options.title) + "</div>";
        }

        // 訊息（未給 → 空字串）
        let toast_message = raw_options.message;

        // 未給 message → 空字串
        if (is_empty_value(toast_message)) {
            toast_message = "";
        }

        // 倒數進度條（duration > 0 才出）
        let toast_bar_html = "";
        if (toast_duration_ms > 0) {
            toast_bar_html = "<span class='sc_toast_bar' style='animation-duration: " + toast_duration_ms + "ms'></span>";
        }

        // toast 元素組裝
        const j_toast = document.createElement("div");
        j_toast.className = "sc_toast";
        j_toast.setAttribute("data-type", toast_type);
        j_toast.setAttribute("role", "status");
        j_toast.innerHTML = "<div class='sc_toast_icon'>" + ALERT_ICONS[toast_type] + "</div>" +
            "<div class='sc_toast_text'>" + toast_title_html +
            "<div class='sc_toast_message'>" + escape_html(toast_message) + "</div>" +
            "</div>" +
            "<button type='button' class='sc_toast_close' aria-label='關閉'>✕</button>" +
            toast_bar_html;

        j_stack.appendChild(j_toast);

        let toast_gone = false;
        let toast_timer_id = null;
        let toast_remaining_ms = toast_duration_ms;
        let toast_started_at = 0;

        const j_toast_bar = j_toast.querySelector(".sc_toast_bar");
        const toast_handle = { el: j_toast, dismiss: dismiss_toast };
        this.live_toasts.push(toast_handle);

        // 關閉（單一出口——每則 toast 只關一次）
        function dismiss_toast() {
            // 已關過 → 不重複
            if (toast_gone === true) {
                return;
            }
            toast_gone = true;

            // 有計時器 → 清掉
            if (toast_timer_id !== null) {
                clearTimeout(toast_timer_id);
                toast_timer_id = null;
            }

            // 由存活清單剔走
            const toast_index = thisObject.live_toasts.indexOf(toast_handle);
            if (toast_index > -1) {
                thisObject.live_toasts.splice(toast_index, 1);
            }

            j_toast.setAttribute("data-state", "out");

            // 退場動畫完才移出 DOM
            setTimeout(function () {
                // 仲喺 DOM 先移除
                if (j_toast.parentNode !== null) {
                    j_toast.parentNode.removeChild(j_toast);
                }
            }, 210);
        }

        // 啟動／重啟倒數（毫秒；<=0 → 立即關）
        function arm_toast_timer(arm_ms) {
            // 無時長 → 立即關
            if (arm_ms <= 0) {
                dismiss_toast();
                return;
            }

            toast_started_at = Date.now();
            toast_remaining_ms = arm_ms;

            // 舊計時器 → 先清
            if (toast_timer_id !== null) {
                clearTimeout(toast_timer_id);
            }

            toast_timer_id = setTimeout(dismiss_toast, arm_ms);
        }

        // 自動關（duration > 0 才啟動）
        if (toast_duration_ms > 0) {
            arm_toast_timer(toast_duration_ms);
        }

        // 移入 → 暫停倒數（記住剩餘時間）
        j_toast.addEventListener("mouseenter", function () {
            // 有計時器 → 停錶並記剩餘
            if (toast_timer_id !== null) {
                clearTimeout(toast_timer_id);
                toast_timer_id = null;
                toast_remaining_ms = Math.max(toast_remaining_ms - (Date.now() - toast_started_at), 0);
            }

            // 進度條同步暫停
            if (j_toast_bar !== null) {
                j_toast_bar.style.animationPlayState = "paused";
            }
        });

        // 移出 → 恢復倒數（剩餘時間續跑）
        j_toast.addEventListener("mouseleave", function () {
            // 未關且有時長 → 未有計時器才重啟
            if (toast_gone === false) {
                // 有時長先重啟
                if (toast_duration_ms > 0) {
                    // 未跑緊先重啟
                    if (toast_timer_id === null) {
                        arm_toast_timer(toast_remaining_ms);
                    }
                }
            }

            // 進度條同步恢復
            if (j_toast_bar !== null) {
                j_toast_bar.style.animationPlayState = "running";
            }
        });

        // 撳 toast：✕ → 關；有 on_click → 回呼；冇 on_click → 直接關
        j_toast.addEventListener("click", function (click_event) {
            const j_close_clicked = click_event.target.closest(".sc_toast_close");

            // 撳 ✕ → 關
            if (j_close_clicked !== null) {
                dismiss_toast();
                return;
            }

            // 未提供 on_click → 撳邊度都關
            if (typeof raw_options.on_click !== "function") {
                dismiss_toast();
                return;
            }

            raw_options.on_click(toast_handle);
        });

        return toast_handle;
    }

    // 取得（或建立）toast 堆疊容器
    ensure_toast_stack() {
        let j_stack = document.querySelector(".sc_toast_stack");

        // 已存在 → 直接用
        if (j_stack !== null) {
            return j_stack;
        }

        j_stack = document.createElement("div");
        j_stack.className = "sc_toast_stack";
        document.body.appendChild(j_stack);

        return j_stack;
    }

    // 關閉全部 toast（先複製清單再走訪——避免走訪中被改）
    dismiss_toasts() {
        const toast_snapshot = this.live_toasts.slice();

        toast_snapshot.forEach(function (toast_handle_item) {
            toast_handle_item.dismiss();
        });
    }

    /* ==================== 後端資料 ==================== */

    // 直食後端 JSON（type／level／status ＋ message／text／body ＋ detail；其餘選項原樣帶過）
    notify(backend_data) {
        // 非物件 → 當普通內容處理
        if (is_plain_object(backend_data) === false) {
            return this.show(backend_data);
        }

        const notify_options = {};

        // 類型欄位（type → level → status 依序取第一個有值者）
        if (is_empty_value(backend_data.type) === false) {
            notify_options.type = backend_data.type;
        } else if (is_empty_value(backend_data.level) === false) {
            notify_options.type = backend_data.level;
        } else if (is_empty_value(backend_data.status) === false) {
            notify_options.type = backend_data.status;
        }

        // 標題欄位
        if (is_empty_value(backend_data.title) === false) {
            notify_options.title = backend_data.title;
        }

        // 訊息欄位（message → text → body 依序取第一個有值者）
        if (is_empty_value(backend_data.message) === false) {
            notify_options.message = backend_data.message;
        } else if (is_empty_value(backend_data.text) === false) {
            notify_options.message = backend_data.text;
        } else if (is_empty_value(backend_data.body) === false) {
            notify_options.message = backend_data.body;
        }

        // detail → 疊喺訊息後（第二行）
        if (is_empty_value(backend_data.detail) === false) {
            let base_message = notify_options.message;

            // 未有訊息 → detail 當本體
            if (is_empty_value(base_message)) {
                notify_options.message = backend_data.detail;
            } else {
                notify_options.message = base_message + "\n" + backend_data.detail;
            }
        }

        // 其餘選項原樣帶過（有值才收——避免覆蓋預設）
        const passthrough_keys = ["confirm_text", "cancel_text", "auto_close", "backdrop_close",
            "escape_close", "show_close", "width", "html", "code", "on_open", "on_close"];

        passthrough_keys.forEach(function (option_key) {
            // 未提供 → 跳過
            if (backend_data[option_key] === undefined) {
                return;
            }
            notify_options[option_key] = backend_data[option_key];
        });

        return this.show(notify_options);
    }

    // 類型猜測（對外開放——呼叫端可自行分類）
    guess(text_value) {
        return guess_type(text_value);
    }

    // 型別清單（對外查詢用）
    types() {
        return ALERT_TYPES.slice();
    }
}

// 出口（factory——同 registry 模式；registry().shared().sc_alert() 取單例）
export default (function (instance) {
    return function () {
        return instance;
    };
})(new _sc_alert());
