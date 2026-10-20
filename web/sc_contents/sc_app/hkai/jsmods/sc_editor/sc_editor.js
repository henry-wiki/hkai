// jsmods/sc_editor/sc_editor.js —— Markdown 編輯器（CodeMirror 封裝——多語境共用）
// 用途：registry().sc_editor() → 服務單例；create_editor(selection_scope, event_namespace) → 每個編輯語境一個控制器
// 職責：控制器＝掛載／卸載／工具列綁定（編輯切換＋格式掣八粒）／閱讀⇄編輯切換／讀現值（全交 CodeMirror；本檔唔碰業務）
// 語境：Instructions 設定頁（[data-modal='settings_modal']）＋專案規則／環境頁（[data-modal='project_modal']）——範圍同 namespace 由呼叫端帶入
// 結構：控制器 class + 服務單例（eager closure factory——同 sc_loading 家族形）

class _sc_editor {
    constructor(selection_scope, event_namespace) {
        // 本語境（selector 範圍＝工具列／切換掣搜尋界；事件 namespace 前綴＝DevTools 一眼辨識）
        this.selection_scope = selection_scope;
        this.event_namespace = event_namespace;
        this.code_mirror_instance = null;
    }

    // 掛載 CodeMirror 編輯器（scope 內搵 mount 節點——無＝略過；初始值由同名 value 節點帶入）
    mount_editor(j_scope) {
        const j_mount = j_scope.find(".instructions-editor__mount");

        // 判定：頁內無編輯器節點 → 略過（其它分頁零影響）
        if (!j_mount.length) {
            return;
        }

        const j_value = j_scope.find(".instructions-editor__value");
        const instructions_content = j_value.text();

        // CodeMirror 選項（純文字編輯器——markdown 上色唔改原文；Tab 縮排；Enter 續列表 1. 2. 3.；初始值由頁內 value 節點帶入）
        const editor_options = {
            mode           : { name: "markdown", highlightFormatting: true },
            theme          : "material-darker",
            value          : instructions_content,
            lineNumbers    : false,
            lineWrapping   : true,
            indentUnit     : 4,
            indentWithTabs : false,
            tabSize        : 4,
            viewportMargin : Infinity,
            extraKeys      : {
                "Tab"       : "insertSoftTab",
                "Shift-Tab" : "indentLess",
                "Enter"     : "newlineAndIndentContinueMarkdownList",
            },
        };

        // 判定：閱讀起始（mount 帶 data-readonly='1'）→ readOnly＝'nocursor'（唔單止唔畀打——連光標都冇，零誤導）
        if (j_mount.attr("data-readonly") === "1") {
            editor_options.readOnly = "nocursor";
        }

        this.code_mirror_instance = CodeMirror(j_mount[0], editor_options);

        // 工具列綁定（每次掛載重綁——.off 先清、零重複）
        this.bind_toolbar();
    }

    // 卸載編輯器（切頁前先移除——唔留殘留實例；無實例＝no-op）
    teardown_editor() {
        // 判定：無實例 → 直回
        if (!this.code_mirror_instance) {
            return;
        }

        jQuery(this.code_mirror_instance.getWrapperElement()).remove();
        this.code_mirror_instance = null;
    }

    // 讀現值（CodeMirror 實例——getValue 即 markdown 原文）
    read_editor_content() {
        // 判定：編輯器未就緒 → 空字串
        if (!this.code_mirror_instance) {
            return "";
        }

        return this.code_mirror_instance.getValue();
    }

    // 工具列綁定（每條獨立 namespace——DevTools 可追蹤；.off 先清再綁、零重複）
    bind_toolbar() {
        const selection_scope = this.selection_scope;
        const event_namespace = this.event_namespace;

        jQuery(document)
            .off("click." + event_namespace + "_toggle_edit_mode")
            .on("click." + event_namespace + "_toggle_edit_mode", selection_scope + " [data-btn='toggle_edit_mode']", this.toggle_edit_mode.bind(this));

        jQuery(document)
            .off("click." + event_namespace + "_insert_heading_1")
            .on("click." + event_namespace + "_insert_heading_1", selection_scope + " [data-btn='insert_heading_1']", this.insert_heading.bind(this, 1));

        jQuery(document)
            .off("click." + event_namespace + "_insert_heading_2")
            .on("click." + event_namespace + "_insert_heading_2", selection_scope + " [data-btn='insert_heading_2']", this.insert_heading.bind(this, 2));

        jQuery(document)
            .off("click." + event_namespace + "_insert_heading_3")
            .on("click." + event_namespace + "_insert_heading_3", selection_scope + " [data-btn='insert_heading_3']", this.insert_heading.bind(this, 3));

        jQuery(document)
            .off("click." + event_namespace + "_insert_ordered_list")
            .on("click." + event_namespace + "_insert_ordered_list", selection_scope + " [data-btn='insert_ordered_list']", this.insert_ordered_list.bind(this));

        jQuery(document)
            .off("click." + event_namespace + "_insert_bullet_list")
            .on("click." + event_namespace + "_insert_bullet_list", selection_scope + " [data-btn='insert_bullet_list']", this.insert_bullet_list.bind(this));

        jQuery(document)
            .off("click." + event_namespace + "_insert_bold")
            .on("click." + event_namespace + "_insert_bold", selection_scope + " [data-btn='insert_bold']", this.insert_bold.bind(this));

        jQuery(document)
            .off("click." + event_namespace + "_insert_italic")
            .on("click." + event_namespace + "_insert_italic", selection_scope + " [data-btn='insert_italic']", this.insert_italic.bind(this));

        jQuery(document)
            .off("click." + event_namespace + "_insert_code_block")
            .on("click." + event_namespace + "_insert_code_block", selection_scope + " [data-btn='insert_code_block']", this.insert_code_block.bind(this));
    }

    // 工具列：編輯模式切換（閱讀 ⇄ 編輯——閱讀＝唯讀＋格式掣收起；編輯＝可改＋格式掣展開）
    toggle_edit_mode() {
        const j_editor = jQuery(this.selection_scope + " .instructions-editor");

        // 判定：編輯器未就緒 → 唔做
        if (!this.code_mirror_instance) {
            return;
        }

        const is_edit_mode = j_editor.attr("data-edit_mode") === "1";

        // 二選一：編輯模式中 → 轉返閱讀（唯讀＋無光標）
        if (is_edit_mode) {
            j_editor.attr("data-edit_mode", "0");
            this.code_mirror_instance.setOption("readOnly", "nocursor");
        } else {
            // 否則是閱讀模式 → 入編輯（可改）
            j_editor.attr("data-edit_mode", "1");
            this.code_mirror_instance.setOption("readOnly", false);
        }
    }

    // 工具列：標題 toggle（唔係本級＝逐行設為本級；全部已係本級＝逐行拆走；空行跳過）
    insert_heading(heading_level) {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selection  = code_mirror.listSelections()[0];
        const start_line = Math.min(selection.anchor.line, selection.head.line);
        const end_line   = Math.max(selection.anchor.line, selection.head.line);
        const heading_marker = "#".repeat(heading_level) + " ";

        // 第一趟：查明非空行係唔係全部都係本級標題
        let has_line_of_other_level = false;

        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過唔計
            if (line_text.trim() === "") {
                continue;
            }

            const heading_match = line_text.match(/^(#{1,6}) /);
            let current_heading_level = 0;

            // 判定：呢行有標題標記 → 記低級數
            if (heading_match !== null) {
                current_heading_level = heading_match[1].length;
            }

            // 判定：唔係本級（普通行又或者其他級）→ 記低
            if (current_heading_level !== heading_level) {
                has_line_of_other_level = true;
            }
        }

        // 第二趟：逐行處理——全部已係本級 → 拆走；否則 → 逐行設為本級
        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過
            if (line_text.trim() === "") {
                continue;
            }

            const heading_match = line_text.match(/^(#{1,6}) /);

            // 判定：全部已係本級 → 拆走嗰行標記
            if (!has_line_of_other_level) {
                code_mirror.replaceRange("", { line: line_index, ch: 0 }, { line: line_index, ch: heading_match[0].length });
                continue;
            }

            // 判定：已有其他級標題 → 換成本級標記
            if (heading_match !== null) {
                code_mirror.replaceRange(heading_marker, { line: line_index, ch: 0 }, { line: line_index, ch: heading_match[0].length });
                continue;
            }

            // 普通行 → 加上本級標記
            code_mirror.replaceRange(heading_marker, { line: line_index, ch: 0 });
        }
    }

    // 工具列：有序列表 toggle（未加＝逐行前綴「1. 2. 3.」；全部已有＝逐行拆走；空行跳過唔食號）
    insert_ordered_list() {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selection  = code_mirror.listSelections()[0];
        const start_line = Math.min(selection.anchor.line, selection.head.line);
        const end_line   = Math.max(selection.anchor.line, selection.head.line);

        // 第一趟：查明非空行係唔係全部都有「數字. 」
        let has_unmarked_line = false;

        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過唔計
            if (line_text.trim() === "") {
                continue;
            }

            // 判定：呢行未有「數字. 」→ 記低（唔係全部有）
            if (!/^\d+\. /.test(line_text)) {
                has_unmarked_line = true;
            }
        }

        // 第二趟：逐行處理——全部已有 → 拆走；否則 → 補上序號
        let item_number = 1;

        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過
            if (line_text.trim() === "") {
                continue;
            }

            // 判定：全部已有 → 拆走嗰行嘅「數字. 」
            if (!has_unmarked_line) {
                const marker_text = line_text.match(/^\d+\. /)[0];
                code_mirror.replaceRange("", { line: line_index, ch: 0 }, { line: line_index, ch: marker_text.length });
                continue;
            }

            // 判定：呢行未有「數字. 」→ 補上序號（已有嘅唔郁——防疊）
            if (!/^\d+\. /.test(line_text)) {
                code_mirror.replaceRange(item_number + ". ", { line: line_index, ch: 0 });
            }

            item_number += 1;
        }
    }

    // 工具列：無序列表 toggle（未加＝逐行前綴「- 」；全部已有＝逐行拆走；空行跳過）
    insert_bullet_list() {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selection  = code_mirror.listSelections()[0];
        const start_line = Math.min(selection.anchor.line, selection.head.line);
        const end_line   = Math.max(selection.anchor.line, selection.head.line);

        // 第一趟：查明非空行係唔係全部都有「- 」
        let has_unmarked_line = false;

        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過唔計
            if (line_text.trim() === "") {
                continue;
            }

            // 判定：呢行未有「- 」→ 記低（唔係全部有）
            if (line_text.slice(0, 2) !== "- ") {
                has_unmarked_line = true;
            }
        }

        // 第二趟：逐行處理——全部已有 → 拆走；否則 → 補上前綴
        for (let line_index = start_line; line_index <= end_line; line_index++) {
            const line_text = code_mirror.getLine(line_index);

            // 判定：空行 → 跳過
            if (line_text.trim() === "") {
                continue;
            }

            // 判定：全部已有 → 拆走嗰行嘅「- 」
            if (!has_unmarked_line) {
                code_mirror.replaceRange("", { line: line_index, ch: 0 }, { line: line_index, ch: 2 });
                continue;
            }

            // 判定：呢行未有「- 」→ 補上（已有嘅唔郁——防疊）
            if (line_text.slice(0, 2) !== "- ") {
                code_mirror.replaceRange("- ", { line: line_index, ch: 0 });
            }
        }
    }

    // 工具列：粗體 toggle（唔係粗體＝包「**」；已係粗體＝拆走星號；無選字＝插一對星號、游標移入中間）
    insert_bold() {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selected_text = code_mirror.getSelection();

        // 判定：無選字 → 插一對星號、游標移入兩個星號中間
        if (selected_text === "") {
            const cursor_position = code_mirror.getCursor();
            code_mirror.replaceSelection("****");
            code_mirror.setCursor({ line: cursor_position.line, ch: cursor_position.ch + 2 });
            return;
        }

        const selection_start = code_mirror.getCursor("from");
        const selection_end   = code_mirror.getCursor("to");
        const text_head = selected_text.slice(0, 2);
        const text_tail = selected_text.slice(selected_text.length - 2);

        // 判定：選字頭兩位係「**」→ 再查尾
        if (text_head === "**") {
            // 判定：尾兩位都係「**」→ 選字連星號：拆走頭尾星號
            if (text_tail === "**") {
                const inner_text = selected_text.slice(2, selected_text.length - 2);
                const selection_start_index = code_mirror.indexFromPos(selection_start);

                code_mirror.replaceRange(inner_text, selection_start, selection_end);
                code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index), code_mirror.posFromIndex(selection_start_index + inner_text.length));
                return;
            }
        }

        let text_before_selection = "";
        let text_after_selection  = "";

        // 判定：選字起點前面有兩個位 → 讀前兩位
        if (selection_start.ch >= 2) {
            text_before_selection = code_mirror.getRange({ line: selection_start.line, ch: selection_start.ch - 2 }, selection_start);
        }

        const end_line_text = code_mirror.getLine(selection_end.line);

        // 判定：選字終點後面有兩個位 → 讀後兩位
        if (selection_end.ch + 2 <= end_line_text.length) {
            text_after_selection = code_mirror.getRange(selection_end, { line: selection_end.line, ch: selection_end.ch + 2 });
        }

        // 判定：前兩位係「**」→ 再查後
        if (text_before_selection === "**") {
            // 判定：後兩位都係「**」→ 選字被星號包住：連前後星號一齊拆、選字保留
            if (text_after_selection === "**") {
                const selection_start_index = code_mirror.indexFromPos(selection_start);
                const selection_end_index   = code_mirror.indexFromPos(selection_end);

                code_mirror.replaceRange("", selection_end, { line: selection_end.line, ch: selection_end.ch + 2 });
                code_mirror.replaceRange("", { line: selection_start.line, ch: selection_start.ch - 2 }, selection_start);
                code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index - 2), code_mirror.posFromIndex(selection_end_index - 2));
                return;
            }
        }

        // 唔係粗體 → 包「**」、選字保留喺中間（撳第二次＝行返上面嘅拆星號路徑）
        const selection_start_index = code_mirror.indexFromPos(selection_start);

        code_mirror.replaceSelection("**" + selected_text + "**");
        code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index + 2), code_mirror.posFromIndex(selection_start_index + 2 + selected_text.length));
    }

    // 工具列：斜體 toggle（唔係斜體＝包「*」；已係斜體＝拆走單星；無選字＝插一對單星、游標移入中間）
    insert_italic() {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selected_text = code_mirror.getSelection();

        // 判定：無選字 → 插一對單星、游標移入兩個單星中間
        if (selected_text === "") {
            const cursor_position = code_mirror.getCursor();
            code_mirror.replaceSelection("**");
            code_mirror.setCursor({ line: cursor_position.line, ch: cursor_position.ch + 1 });
            return;
        }

        const selection_start = code_mirror.getCursor("from");
        const selection_end   = code_mirror.getCursor("to");

        // 判定一：選字連住單星（頭尾都係「*」而唔係「**」）→ 拆走頭尾單星
        let is_wrapped_inside_selection = true;

        // 判定：頭一位唔係「*」→ 唔算
        if (selected_text.slice(0, 1) !== "*") {
            is_wrapped_inside_selection = false;
        }

        // 判定：尾一位唔係「*」→ 唔算
        if (selected_text.slice(selected_text.length - 1) !== "*") {
            is_wrapped_inside_selection = false;
        }

        // 判定：頭兩位係「**」→ 唔算（係粗體嘅一部分）
        if (selected_text.slice(0, 2) === "**") {
            is_wrapped_inside_selection = false;
        }

        // 判定：尾兩位係「**」→ 唔算（係粗體嘅一部分）
        if (selected_text.slice(selected_text.length - 2) === "**") {
            is_wrapped_inside_selection = false;
        }

        // 判定：選字被單星包住 → 拆走頭尾單星、選字保留
        if (is_wrapped_inside_selection) {
            const inner_text = selected_text.slice(1, selected_text.length - 1);
            const selection_start_index = code_mirror.indexFromPos(selection_start);

            code_mirror.replaceRange(inner_text, selection_start, selection_end);
            code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index), code_mirror.posFromIndex(selection_start_index + inner_text.length));
            return;
        }

        // 判定二：選字被前後單星夾住（前一位同後一位都係「*」而唔係「**」）→ 連前後單星一齊拆
        let text_before_1 = "";
        let text_before_2 = "";

        // 判定：選字起點前面有一位 → 讀前一位
        if (selection_start.ch >= 1) {
            text_before_1 = code_mirror.getRange({ line: selection_start.line, ch: selection_start.ch - 1 }, selection_start);
        }

        // 判定：選字起點前面有兩位 → 讀前兩位
        if (selection_start.ch >= 2) {
            text_before_2 = code_mirror.getRange({ line: selection_start.line, ch: selection_start.ch - 2 }, selection_start);
        }

        const end_line_text = code_mirror.getLine(selection_end.line);
        let text_after_1 = "";
        let text_after_2 = "";

        // 判定：選字終點後面有一位 → 讀後一位
        if (selection_end.ch + 1 <= end_line_text.length) {
            text_after_1 = code_mirror.getRange(selection_end, { line: selection_end.line, ch: selection_end.ch + 1 });
        }

        // 判定：選字終點後面有兩位 → 讀後兩位
        if (selection_end.ch + 2 <= end_line_text.length) {
            text_after_2 = code_mirror.getRange(selection_end, { line: selection_end.line, ch: selection_end.ch + 2 });
        }

        let is_wrapped_by_single_star = true;

        // 判定：前面唔係「*」→ 唔算
        if (text_before_1 !== "*") {
            is_wrapped_by_single_star = false;
        }

        // 判定：前面係「**」→ 唔算（係粗體嘅一部分）
        if (text_before_2 === "**") {
            is_wrapped_by_single_star = false;
        }

        // 判定：後面唔係「*」→ 唔算
        if (text_after_1 !== "*") {
            is_wrapped_by_single_star = false;
        }

        // 判定：後面係「**」→ 唔算（係粗體嘅一部分）
        if (text_after_2 === "**") {
            is_wrapped_by_single_star = false;
        }

        // 判定：前後都係單星 → 拆走單星、選字保留
        if (is_wrapped_by_single_star) {
            const selection_start_index = code_mirror.indexFromPos(selection_start);
            const selection_end_index   = code_mirror.indexFromPos(selection_end);

            code_mirror.replaceRange("", selection_end, { line: selection_end.line, ch: selection_end.ch + 1 });
            code_mirror.replaceRange("", { line: selection_start.line, ch: selection_start.ch - 1 }, selection_start);
            code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index - 1), code_mirror.posFromIndex(selection_end_index - 1));
            return;
        }

        // 唔係斜體 → 包「*」、選字保留喺中間（撳第二次＝行返上面嘅拆單星路徑）
        const selection_start_index = code_mirror.indexFromPos(selection_start);

        code_mirror.replaceSelection("*" + selected_text + "*");
        code_mirror.setSelection(code_mirror.posFromIndex(selection_start_index + 1), code_mirror.posFromIndex(selection_start_index + 1 + selected_text.length));
    }

    // 工具列：Code block toggle（未圍欄＝選取行上下各加一行「```」；已圍欄＝拆走上下圍欄）
    insert_code_block() {
        const code_mirror = this.code_mirror_instance;

        // 判定：編輯器未就緒 → 唔做
        if (!code_mirror) {
            return;
        }

        const selection  = code_mirror.listSelections()[0];
        const start_line = Math.min(selection.anchor.line, selection.head.line);
        const end_line   = Math.max(selection.anchor.line, selection.head.line);

        const line_above = code_mirror.getLine(start_line - 1);
        const line_below = code_mirror.getLine(end_line + 1);

        let line_above_text = "";

        // 判定：上面有行 → 讀入（去空白）
        if (line_above !== undefined) {
            line_above_text = line_above.trim();
        }

        let line_below_text = "";

        // 判定：下面有行 → 讀入（去空白）
        if (line_below !== undefined) {
            line_below_text = line_below.trim();
        }

        let is_already_fenced = true;

        // 判定：上面唔係圍欄 → 唔算已圍欄
        if (line_above_text.slice(0, 3) !== "```") {
            is_already_fenced = false;
        }

        // 判定：下面唔係圍欄 → 唔算已圍欄
        if (line_below_text.slice(0, 3) !== "```") {
            is_already_fenced = false;
        }

        // 判定：已圍欄 → 拆走上下圍欄、完成
        if (is_already_fenced) {
            const closer_length = code_mirror.getLine(end_line + 1).length;

            code_mirror.replaceRange("", { line: end_line, ch: code_mirror.getLine(end_line).length }, { line: end_line + 1, ch: closer_length });
            code_mirror.replaceRange("", { line: start_line - 1, ch: 0 }, { line: start_line, ch: 0 });

            // 選取範圍釘返落內容行（上面圍欄拆走後上移一行；唔釘＝下次偵測飄）
            code_mirror.setSelection({ line: start_line - 1, ch: 0 }, { line: end_line - 1, ch: code_mirror.getLine(end_line - 1).length });
            return;
        }

        // 唔係圍欄 → 上下各加一行圍欄（先加下面——免影響上面行號）
        code_mirror.replaceRange("\n```", { line: end_line, ch: code_mirror.getLine(end_line).length });
        code_mirror.replaceRange("```\n", { line: start_line, ch: 0 });

        // 選取範圍釘返落內容行（圍欄插喺上下——唔釘＝下次偵測飄去圍欄行）
        code_mirror.setSelection({ line: start_line + 1, ch: 0 }, { line: end_line + 1, ch: code_mirror.getLine(end_line + 1).length });
    }
}

// 服務（每語境 create 一個控制器——控制器自持實例；服務本身無狀態）
class _sc_editor_service {
    create_editor(selection_scope, event_namespace) {
        return new _sc_editor(selection_scope, event_namespace);
    }
}

export const sc_editor = (function (instance) {
    return function () {

        return instance;
    };
})(new _sc_editor_service());
