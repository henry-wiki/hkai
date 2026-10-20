// jsmods/message/act/message_item_meta.js —— 訊息項目 meta 初始化（act 層——一檔一事）
// 職責：message_items_meta_refresh（一批——逐條交 message_item_meta_refresh）／message_item_meta_refresh（單條——以 wrapper 為基礎：相關重要資訊寫入 message_data〔隱藏容器〕；每項一格元素·data-meta_id 命名；現有＝mermaid——total＝掃描數〔sc_markdown 域 count_mermaid·單一來源〕、completed＝初始化快照 0）／get_message_item_meta_value（單條讀取——第三參數指定值名直讀：data-〔value_name〕；零循環）
// 結構同 act 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _message_item_meta {
    constructor() {}

    // 訊息項目 meta 刷新（一批——清單用：逐條交 message_item_meta_refresh）
    async message_items_meta_refresh(j_message_wrappers) {
        // 逐條刷新（一條處理完先落下一條）
        for (let index = 0; index < j_message_wrappers.length; index++) {
            await this.message_item_meta_refresh(jQuery(j_message_wrappers[index]));
        }
    }

    // 訊息項目 meta 刷新（單條——以 wrapper 為基礎：資訊入 message_data；此輪＝渲染前快照）
    async message_item_meta_refresh(j_message_wrapper) {
        // 數量知識交 sc_markdown 域（單一來源）
        const markdown = await registry().sc_markdown();

        const j_message_data = j_message_wrapper.children(".message_data");

        // mermaid meta（一格 span·data-meta_id 命名）
        let j_mermaid_meta = j_message_data.children("[data-meta_id='mermaid']");

        // 判定：未建立 → 建（get-or-create）
        if (!j_mermaid_meta.length) {
            j_mermaid_meta = jQuery("<span data-meta_id='mermaid' data-mermaid_total='' data-mermaid_completed=''></span>");
            j_message_data.append(j_mermaid_meta);
        }

        // mermaid 總數＝掃描數（code.language-mermaid——mermaid 域知識）
        const mermaid_total = markdown.count_mermaid(j_message_wrapper);

        // 落值：總數＝掃描結果；完成數＝0（初始化＝渲染前快照）
        j_mermaid_meta.attr("data-mermaid_total", mermaid_total);
        j_mermaid_meta.attr("data-mermaid_completed", "0");
    }
}

export const message_item_meta = (function (instance) {
    return function () {

        return instance;
    };
})(new _message_item_meta());
