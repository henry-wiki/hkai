// jsmods/prompt/evt/prompt_attach.js —— 附件選擇／貼上／拖放綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（attach_files／attach_remove／貼上／拖放）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 範圍：只做 UI chips（選檔／貼上／拖放／移除）；上傳與送模型屬下一部分

class _prompt_attach {
    constructor() {
        // 已選附件（{uid, file, preview_url}——暫存記憶體；未上傳）
        this.attachments = [];
        this.next_uid = 1;
    }

    // 綁定註冊（由 prompt evt init 呼叫——一次；selector 委派）
    init() {
        jQuery(document)
            .off("click.sc_ai_chats_prompt_attach")
            .on("click.sc_ai_chats_prompt_attach", "[data-action='attach_files']", this.attach_files.bind(this));

        jQuery(document)
            .off("change.sc_ai_chats_prompt_attach")
            .on("change.sc_ai_chats_prompt_attach", ".sc_composer__file_input", this.attach_files_change.bind(this));

        jQuery(document)
            .off("click.sc_ai_chats_prompt_attach_remove")
            .on("click.sc_ai_chats_prompt_attach_remove", "[data-action='attach_remove']", this.attach_remove.bind(this));

        jQuery(document)
            .off("paste.sc_ai_chats_prompt_attach")
            .on("paste.sc_ai_chats_prompt_attach", "#sc_input", this.attach_files_paste.bind(this));

        jQuery(document)
            .off("dragover.sc_ai_chats_prompt_attach")
            .on("dragover.sc_ai_chats_prompt_attach", ".sc_composer__editor", this.attach_files_dragover.bind(this));

        jQuery(document)
            .off("dragleave.sc_ai_chats_prompt_attach")
            .on("dragleave.sc_ai_chats_prompt_attach", ".sc_composer__editor", this.attach_files_dragleave.bind(this));

        jQuery(document)
            .off("drop.sc_ai_chats_prompt_attach")
            .on("drop.sc_ai_chats_prompt_attach", ".sc_composer__editor", this.attach_files_drop.bind(this));
    }

    // 取已選檔案（送問前上傳用——回傳 File 陣列副本）
    get_files() {
        var file_list = [];

        // 逐項抽檔案
        for (var index = 0; index < this.attachments.length; index++) {
            file_list.push(this.attachments[index].file);
        }

        return file_list;
    }

    // 清空已選附件（送出成功後——釋放縮圖＋收容器）
    clear_attachments() {
        // 逐項釋放縮圖網址
        for (var index = 0; index < this.attachments.length; index++) {
            this.release_preview_url(this.attachments[index]);
        }

        this.attachments = [];
        this.render_attachments();
    }

    // 開檔案選擇器（附件掣）
    attach_files(event) {
        event.preventDefault();

        var j_input = jQuery(".sc_composer__file_input");

        // 判定：頁面冇 file input → 唔動作
        if (j_input.length < 1) {
            return;
        }

        j_input.trigger("click");
    }

    // 選檔後加入清單（多選）
    attach_files_change(event) {
        var input_element = event.currentTarget;

        this.add_files(input_element.files);

        // 清空 input（同一個檔可以再選）
        input_element.value = "";
    }

    // 貼上（剪貼板圖片——Ctrl+V）
    attach_files_paste(event) {
        var clipboard_data = event.originalEvent.clipboardData;

        // 判定：冇剪貼板資料 → 唔動作
        if (!clipboard_data) {
            return;
        }

        var pasted_files = [];
        var clipboard_items = clipboard_data.items;

        // 逐項抽檔案（只有圖片）
        for (var index = 0; index < clipboard_items.length; index++) {
            var clipboard_item = clipboard_items[index];

            // 判定：唔係檔案 → 跳過
            if (clipboard_item.kind !== "file") {
                continue;
            }

            // 判定：唔係圖片 → 跳過
            if (clipboard_item.type.indexOf("image/") !== 0) {
                continue;
            }

            var pasted_file = clipboard_item.getAsFile();

            // 判定：抽唔到檔 → 跳過
            if (!pasted_file) {
                continue;
            }

            pasted_files.push(pasted_file);
        }

        // 判定：冇圖片 → 保持原生貼上（文字）
        if (pasted_files.length < 1) {
            return;
        }

        event.preventDefault();
        this.add_files(pasted_files);
    }

    // 拖放：進入（阻止瀏覽器開檔＋高亮）
    attach_files_dragover(event) {
        event.preventDefault();
        event.stopPropagation();
        jQuery(event.currentTarget).addClass("sc_composer__editor--drag_over");
    }

    // 拖放：離開（取消高亮）
    attach_files_dragleave(event) {
        event.preventDefault();
        event.stopPropagation();
        jQuery(event.currentTarget).removeClass("sc_composer__editor--drag_over");
    }

    // 拖放：放下（加 chips）
    attach_files_drop(event) {
        event.preventDefault();
        event.stopPropagation();

        var j_editor = jQuery(event.currentTarget);
        j_editor.removeClass("sc_composer__editor--drag_over");

        var drag_data = event.originalEvent.dataTransfer;

        // 判定：冇拖放資料 → 唔動作
        if (!drag_data) {
            return;
        }

        this.add_files(drag_data.files);
    }

    // 加入檔案（選檔／貼上／拖放共用——同名同大小同時間＝重複跳過）
    add_files(file_list) {
        var added_count = 0;

        // 逐個檔加入暫存清單
        for (var index = 0; index < file_list.length; index++) {
            var file_item = file_list[index];

            // 判定：唔係圖片 → 跳過（附件只支援圖片）
            if (file_item.type.indexOf("image/") !== 0) {
                continue;
            }

            // 判定：已存在同一份檔 → 跳過
            if (this.has_attachment(file_item)) {
                continue;
            }

            var attachment = {};
            attachment.uid = this.next_uid;
            attachment.file = file_item;
            attachment.preview_url = "";
            this.next_uid = this.next_uid + 1;

            // 判定：圖片 → 產生縮圖網址
            if (file_item.type.indexOf("image/") === 0) {
                attachment.preview_url = URL.createObjectURL(file_item);
            }

            this.attachments.push(attachment);
            added_count = added_count + 1;
        }

        // 判定：有新增 → 重畫
        if (added_count > 0) {
            this.render_attachments();
        }
    }

    // 判定是否已有同一份檔（檔名＋大小＋修改時間）
    has_attachment(file_item) {
        // 逐項比對
        for (var index = 0; index < this.attachments.length; index++) {
            var attachment_item = this.attachments[index];
            var existing_file = attachment_item.file;

            // 判定：檔名唔同 → 未重複
            if (existing_file.name !== file_item.name) {
                continue;
            }

            // 判定：大小唔同 → 未重複
            if (existing_file.size !== file_item.size) {
                continue;
            }

            // 判定：修改時間唔同 → 未重複
            if (existing_file.lastModified !== file_item.lastModified) {
                continue;
            }

            return true;
        }

        return false;
    }

    // 移除一項（chip 上 ×）
    attach_remove(event) {
        event.preventDefault();

        var uid = parseInt(jQuery(event.currentTarget).attr("data-attach_uid"), 10);
        var kept_attachments = [];

        // 逐項保留（uid 唔同＝留下）
        for (var index = 0; index < this.attachments.length; index++) {
            var attachment_item = this.attachments[index];

            // 判定：要移除呢項 → 釋放縮圖網址
            if (attachment_item.uid === uid) {
                this.release_preview_url(attachment_item);
                continue;
            }

            kept_attachments.push(attachment_item);
        }

        this.attachments = kept_attachments;
        this.render_attachments();
    }

    // 釋放縮圖網址（避免記憶體漏）
    release_preview_url(attachment_item) {
        // 判定：冇縮圖 → 唔動作
        if (attachment_item.preview_url === "") {
            return;
        }

        URL.revokeObjectURL(attachment_item.preview_url);
    }

    // 重畫 chips（容器有檔才顯示）
    render_attachments() {
        var j_container = jQuery(".sc_composer__attachments");

        // 判定：頁面冇容器 → 唔動作
        if (j_container.length < 1) {
            return;
        }

        j_container.empty();

        // 判定：清單空 → 隱藏容器
        if (this.attachments.length < 1) {
            j_container.attr("hidden", "hidden");
            return;
        }

        j_container.removeAttr("hidden");

        // 逐項加 chip
        for (var index = 0; index < this.attachments.length; index++) {
            var attachment_item = this.attachments[index];
            j_container.append(this.build_attachment_chip(attachment_item));
        }
    }

    // 砌一個 chip（檔名／大小／移除掣——text() 防 HTML 注入）
    build_attachment_chip(attachment_item) {
        var j_chip = jQuery("<span class='sc_composer__attachment'></span>");
        j_chip.attr("data-attach_uid", attachment_item.uid);

        // 判定：有縮圖 → 用圖片；否則用 icon
        if (attachment_item.preview_url !== "") {
            var j_thumb = jQuery("<img class='sc_composer__attachment_thumb'>");
            j_thumb.attr("src", attachment_item.preview_url);
            j_thumb.attr("alt", attachment_item.file.name);
            j_chip.append(j_thumb);
        } else {
            j_chip.append(jQuery("<i class='fa-solid fa-paperclip'></i>"));
        }

        var j_name = jQuery("<span class='sc_composer__attachment_name'></span>");
        j_name.text(attachment_item.file.name);

        var j_size = jQuery("<span class='sc_composer__attachment_size'></span>");
        j_size.text(this.format_file_size(attachment_item.file.size));

        var j_remove = jQuery("<button class='sc_composer__attachment_remove' type='button' data-action='attach_remove'>×</button>");
        j_remove.attr("data-attach_uid", attachment_item.uid);

        j_chip.append(j_name);
        j_chip.append(j_size);
        j_chip.append(j_remove);

        return j_chip;
    }

    // 檔案大小顯示（B／KB／MB）
    format_file_size(size_bytes) {
        // 判定：小於 1 KB → B
        if (size_bytes < 1024) {
            return size_bytes + " B";
        }

        // 判定：小於 1 MB → KB
        if (size_bytes < 1024 * 1024) {
            return (size_bytes / 1024).toFixed(1) + " KB";
        }

        return (size_bytes / (1024 * 1024)).toFixed(1) + " MB";
    }
}

export const prompt_attach = (function (instance) {
    return function () {

        return instance;
    };
})(new _prompt_attach());
