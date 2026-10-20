// jsmods/message/act/message_editblock.js —— 訊息內容編輯區塊前處理（act 層——一檔一事）
// 職責：render_element（訊息內容渲染——原文交 editblock_to_display 轉顯示形，再交 sc_markdown 通用原語；原文保留 sc_raw_text 俾 copy／zoom）
// 用途：aider 編輯區塊（<<<<<<< SEARCH／=======／>>>>>>> REPLACE＋路徑行＋信封 ```）→「檔名卡＋代碼框」；孤兒信封吸收；平常 markdown 文字原樣直過
// 對照：sc_markdown 只管「拋入嘅嘢點處理、回咩」——零訊息知識；aider 格式知識一律住本檔
// 唔想要嗰日：刪本檔＋4 個呼叫點改返 markdown.render_element 即可——零殘留
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

// 副檔名 → 代碼框語言（hljs 名——唔認得＝唔標）
const LANGUAGE_BY_EXTENSION = {
    html: "html", htm: "html", css: "css", js: "javascript", mjs: "javascript", cjs: "javascript",
    json: "json", php: "php", py: "python", md: "markdown", ts: "typescript", txt: "plaintext",
    sql: "sql", xml: "xml", yml: "yaml", yaml: "yaml", sh: "bash", ini: "ini"
};

// 檔名行判定（無前導空白、無空格、含斜線、尾帶副檔名——唔似普通句子）
function is_path_line(check_line) {
    // 判定：空行 → 唔係
    if (check_line === "") {
        return false;
    }
    // 判定：有前導空白（縮排＝代碼）→ 唔係
    if (check_line !== check_line.trim()) {
        return false;
    }
    // 判定：含空格（唔似檔案路徑）→ 唔係
    if (check_line.indexOf(" ") !== -1) {
        return false;
    }
    // 判定：唔含斜線 → 唔係
    if (check_line.indexOf("/") === -1) {
        return false;
    }
    // 判定：尾唔似副檔名 → 唔係
    if (!(/\.[A-Za-z0-9]+$/.test(check_line))) {
        return false;
    }

    return true;
}

// 代碼框語言（由路徑副檔名——唔認得＝空＝唔標）
function pick_language_name(path_text) {
    const dot_index = path_text.lastIndexOf(".");

    // 判定：冇副檔名 → 唔標
    if (dot_index === -1) {
        return "";
    }

    const extension = path_text.substring(dot_index + 1).toLowerCase();
    const language_name = LANGUAGE_BY_EXTENSION[extension];

    // 判定：唔認得 → 唔標
    if (language_name === undefined) {
        return "";
    }

    return language_name;
}

// 代碼框圍欄（內容含 ``` 就升做 ````——免提早收口）
function pick_fence_token(body_lines) {
    const fence_token = "```";

    // 逐行查（任何一行含 ``` → 升圍欄）
    for (let line_index = 0; line_index < body_lines.length; line_index++) {
        // 判定：含 ``` → 升做四連
        if (body_lines[line_index].indexOf("```") !== -1) {
            return "````";
        }
    }

    return fence_token;
}

// 檔名收割（由已輸出尾行執——空行跳過；``` 信封／```路徑 吸收；路徑行取走）
function take_path_from_display(display_lines) {
    let back_index = display_lines.length - 1;

    // 跳過尾隨空行（唔當檔名）
    while (back_index >= 0 && display_lines[back_index].trim() === "") {
        back_index = back_index - 1;
    }

    // 判定：冇內容 → 無檔名
    if (back_index < 0) {
        return "";
    }

    const candidate_line = display_lines[back_index];
    const trim_candidate = candidate_line.trim();

    // 判定：獨立信封 ``` → 吸收佢，再望上一行
    if (trim_candidate === "```") {
        display_lines.splice(back_index, 1);
        return take_path_from_display(display_lines);
    }
    // 判定：```路徑 同體形 → 取路徑、成行移走
    if (trim_candidate.indexOf("```") === 0 && trim_candidate.length > 3) {
        display_lines.splice(back_index, 1);
        return trim_candidate.substring(3).trim();
    }
    // 判定：路徑行 → 視為檔名、移走
    if (is_path_line(candidate_line)) {
        display_lines.splice(back_index, 1);
        return candidate_line;
    }

    return "";
}

// 組顯示（檔名卡＋原文＋新文；空段唔出）
function append_editblock_display(display_lines, path_text, search_body_lines, replace_body_lines) {
    const fence_token = pick_fence_token(search_body_lines.concat(replace_body_lines));
    const language_name = pick_language_name(path_text);

    // 檔名卡（行內代碼 chip——冇檔名唔出）
    if (path_text !== "") {
        display_lines.push("📄 `" + path_text + "`");
    }

    // 原文段（SEARCH——空＝唔出）
    if (search_body_lines.length > 0) {
        display_lines.push("");
        display_lines.push("**原文**");
        display_lines.push(fence_token + language_name);

        for (let line_index = 0; line_index < search_body_lines.length; line_index++) {
            display_lines.push(search_body_lines[line_index]);
        }

        display_lines.push(fence_token);
    }

    // 新文段（REPLACE——空＝「（無）」）
    display_lines.push("");
    display_lines.push("**新文**");

    // 判定：有新內容 → 出代碼框
    if (replace_body_lines.length > 0) {
        display_lines.push(fence_token + language_name);

        for (let line_index = 0; line_index < replace_body_lines.length; line_index++) {
            display_lines.push(replace_body_lines[line_index]);
        }

        display_lines.push(fence_token);
    } else {
        display_lines.push("（無）");
    }
}

// 收 SEARCH/REPLACE 區塊（由 <<<<<<< SEARCH 行開始——成功＝入 display_lines 並回下一行索引；失敗＝null＝原樣留返）
function collect_search_replace_block(source_lines, search_start_index, display_lines) {
    // 收原文段（直到 =======）
    let scan_index = search_start_index + 1;
    const search_body_lines = [];

    while (scan_index < source_lines.length && source_lines[scan_index].trim() !== "=======") {
        search_body_lines.push(source_lines[scan_index]);
        scan_index = scan_index + 1;
    }

    // 判定：搵唔到分隔（未收尾）→ 失敗
    if (scan_index >= source_lines.length) {
        return null;
    }

    const separator_index = scan_index;

    // 收新文段（直到 >>>>>>> REPLACE）
    scan_index = separator_index + 1;
    const replace_body_lines = [];

    while (scan_index < source_lines.length && source_lines[scan_index].trim() !== ">>>>>>> REPLACE") {
        replace_body_lines.push(source_lines[scan_index]);
        scan_index = scan_index + 1;
    }

    // 判定：搵唔到收尾（未收尾）→ 失敗
    if (scan_index >= source_lines.length) {
        return null;
    }

    const replace_end_index = scan_index;

    // 檔名收割（回望已輸出尾行——信封／路徑行）
    const path_text = take_path_from_display(display_lines);

    // 檔名卡＋原文／新文
    append_editblock_display(display_lines, path_text, search_body_lines, replace_body_lines);

    // 食埋收口信封（緊隨 >>>>>>> REPLACE 之後一粒 ```）
    let next_index = replace_end_index + 1;

    // 判定：下一行＝獨立 ``` → 吸收佢
    if (next_index < source_lines.length && source_lines[next_index].trim() === "```") {
        next_index = next_index + 1;
    }

    return next_index;
}

// 整檔替換起點判定（本行＝路徑行、下一行＝獨立 ```、再下一行唔係 <<<<<<< SEARCH）
function is_whole_file_start(source_lines, line_index) {
    // 判定：本行唔似路徑 → 唔係
    if (!is_path_line(source_lines[line_index])) {
        return false;
    }
    // 判定：冇下一行 → 唔係
    if (line_index + 1 >= source_lines.length) {
        return false;
    }
    // 判定：下一行唔係獨立 ``` → 唔係
    if (source_lines[line_index + 1].trim() !== "```") {
        return false;
    }
    // 判定：再下一行＝<<<<<<< SEARCH（fenced SEARCH/REPLACE——交另一支處理）→ 唔係
    if (line_index + 2 < source_lines.length && source_lines[line_index + 2].trim() === "<<<<<<< SEARCH") {
        return false;
    }

    return true;
}

// 收整檔替換區塊（路徑行＋```內容```——成功＝入 display_lines 並回下一行索引；失敗＝null）
function collect_whole_file_block(source_lines, path_index, display_lines) {
    // 跳過 ``` 開（path_index＋1 嗰行）
    let scan_index = path_index + 2;
    const body_lines = [];
    let has_closing_fence = false;

    while (scan_index < source_lines.length) {
        // 判定：搵到收口 ``` → 完成
        if (source_lines[scan_index].trim() === "```") {
            has_closing_fence = true;
            break;
        }

        body_lines.push(source_lines[scan_index]);
        scan_index = scan_index + 1;
    }

    // 判定：冇收口（未收尾）→ 失敗
    if (!has_closing_fence) {
        return null;
    }

    const path_text = source_lines[path_index];
    const fence_token = pick_fence_token(body_lines);
    const language_name = pick_language_name(path_text);

    // 檔名卡＋內容（整檔替換＝一個內容框）
    display_lines.push("📄 `" + path_text + "`");
    display_lines.push("");
    display_lines.push(fence_token + language_name);

    for (let line_index = 0; line_index < body_lines.length; line_index++) {
        display_lines.push(body_lines[line_index]);
    }

    display_lines.push(fence_token);

    return scan_index + 1;
}

// 顯示轉換（原文 → 顯示形——唯一改文字嘅地方；唔含區塊＝原樣回）
function editblock_to_display(raw_text) {
    const source_lines = raw_text.split("\n");
    const display_lines = [];
    let line_index = 0;

    while (line_index < source_lines.length) {
        const trim_line = source_lines[line_index].trim();

        // 判定：SEARCH/REPLACE 起點 → 執區塊
        if (trim_line === "<<<<<<< SEARCH") {
            const next_index = collect_search_replace_block(source_lines, line_index, display_lines);

            // 判定：成功 → 跳去下一行
            if (next_index !== null) {
                line_index = next_index;
                continue;
            }
        }

        // 判定：整檔替換起點 → 執區塊
        if (is_whole_file_start(source_lines, line_index)) {
            const next_index = collect_whole_file_block(source_lines, line_index, display_lines);

            // 判定：成功 → 跳去下一行
            if (next_index !== null) {
                line_index = next_index;
                continue;
            }
        }

        // 平常行（或未收尾區塊）——原樣過
        display_lines.push(source_lines[line_index]);
        line_index = line_index + 1;
    }

    return display_lines.join("\n");
}

class _message_editblock {
    constructor() {}

    // 渲染一條內容元素（原文 → 顯示形 → 交 sc_markdown 通用原語；原文保留 sc_raw_text 俾 copy／zoom）
    async render_element(j_element) {
        const content_element = j_element[0];

        // 原文（同 sc_markdown 慣例——已有 sc_raw_text 優先）
        const raw_text = content_element.sc_raw_text || j_element.text();

        // 判定：空內容 → 不動作（避免清空既有結構）
        if (raw_text === "") {
            return;
        }

        // 原文保留（copy／zoom 讀嘅係原文——唯一真相）
        if (!content_element.sc_raw_text) {
            content_element.sc_raw_text = raw_text;
        }

        const display_text = editblock_to_display(raw_text);

        // 判定：無區塊（顯示形＝原文）→ 直交通用原語（平常訊息零變化）
        if (display_text === raw_text) {
            const markdown_plain = await registry().sc_markdown();
            markdown_plain.render_element(j_element);
            return;
        }

        // 轉換後文字交通用原語（臨時容器渲染——成品 HTML 搬返目標元素）
        const markdown = await registry().sc_markdown();
        const j_scratch = jQuery("<div></div>").text(display_text);
        markdown.render_element(j_scratch);

        j_element.empty();
        j_element.addClass("sc_markdown_body");
        j_element.append(j_scratch.contents());
    }
}

export const message_editblock = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_editblock());
