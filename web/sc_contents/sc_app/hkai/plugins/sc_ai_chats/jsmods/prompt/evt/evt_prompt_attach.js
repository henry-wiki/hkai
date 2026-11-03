// jsmods/prompt/evt/evt_prompt_attach.js —— 附件選擇／貼上／拖放綁定（factory 單例——一檔一事）
// 所有東西在此檔：① 綁定註冊（init——selector 委派）② 動作（attach_files／attach_remove／貼上／拖放）
// 檔名＝DevTools Event Listeners 面板顯示嘅字（一檔一事——一眼知位置）
// 範圍：只做 UI chips（選檔／貼上／拖放／移除）；上傳與送模型屬下一部分

import { sc_console } from "../../../../../jsmods/sc_console/sc_console.js";

// fallback 設定（實際值由 .sc_composer__attachments data-* 覆寫——後端 sc_config hkai.draft）
const DEFAULT_MAX_IMAGES                = 13;
const DEFAULT_MAX_SOURCE_SIDE_PX        = 12800;
const DEFAULT_RESIZE_TARGET_SIDE_PX     = 640;
const RESIZE_JPEG_QUALITY               = 0.92;

class _evt_prompt_attach {
    constructor() {
        // 已選附件（{uid, file, origin, name, size, preview_url, thumb_data_url, draft_filename, state}）
        this.attachments = [];
        this.next_uid = 1;

        // 上傳設定（load_settings 由容器 data-* 讀；未讀到用 fallback）
        this.settings = {};
        this.settings.max_images            = DEFAULT_MAX_IMAGES;
        this.settings.max_source_side_px    = DEFAULT_MAX_SOURCE_SIDE_PX;
        this.settings.resize_target_side_px = DEFAULT_RESIZE_TARGET_SIDE_PX;
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

        this.load_settings();
    }

    // 讀上傳設定（容器 data-*——後端 sc_config 單一來源）
    load_settings() {
        const j_container = jQuery(".sc_composer__attachments");

        // 判定：頁面冇容器 → 用 fallback
        if (j_container.length < 1) {
            return;
        }

        const max_images = parseInt(j_container.attr("data-max_images"), 10);
        const max_source_side_px = parseInt(j_container.attr("data-max_source_side_px"), 10);
        const resize_target_side_px = parseInt(j_container.attr("data-resize_target_side_px"), 10);

        // 判定：有值 → 覆寫
        if (!isNaN(max_images) && max_images > 0) {
            this.settings.max_images = max_images;
        }

        // 判定：有值 → 覆寫
        if (!isNaN(max_source_side_px) && max_source_side_px > 0) {
            this.settings.max_source_side_px = max_source_side_px;
        }

        // 判定：有值 → 覆寫
        if (!isNaN(resize_target_side_px) && resize_target_side_px > 0) {
            this.settings.resize_target_side_px = resize_target_side_px;
        }
    }

    // 還原草稿附件（重載後由 draft_fetch 帶入——state＝done）
    restore_from_draft(attachment_list) {
        // 判定：冇清單 → 當空（空清單會收起附件區）
        attachment_list = attachment_list || [];

        this.attachments = [];
        this.next_uid = 1;

        // 逐項還原（伺服器檔名＋原檔名＋縮圖）
        for (let index = 0; index < attachment_list.length; index++) {
            const attachment_item = attachment_list[index];
            const restored = {};
            restored.uid = this.next_uid;
            restored.file = null;
            restored.origin = null;
            restored.name = attachment_item.original_filename || attachment_item.filename;
            restored.size = parseInt(attachment_item.file_size, 10) || 0;
            restored.preview_url = "";
            restored.thumb_data_url = attachment_item.thumb_data_url || "";
            restored.draft_filename = attachment_item.filename;
            restored.state = "done";

            this.next_uid = this.next_uid + 1;
            this.attachments.push(restored);
        }

        this.render_attachments();
    }

    // 取已選檔案（送問前上傳用——回傳 File 陣列副本）
    get_files() {
        const file_list = [];

        // 逐項抽檔案
        for (let index = 0; index < this.attachments.length; index++) {
            file_list.push(this.attachments[index].file);
        }

        return file_list;
    }

    // 有冇仍在上傳中嘅附件（送出閘用——上傳中送會漏圖）
    has_uploading() {
        // 逐項檢查狀態
        for (let index = 0; index < this.attachments.length; index++) {
            // 判定：uploading → 有
            if (this.attachments[index].state === "uploading") {
                return true;
            }
        }

        return false;
    }

    // 清空已選附件（送出成功後——釋放縮圖＋收容器）
    clear_attachments() {
        // 逐項釋放縮圖網址
        for (let index = 0; index < this.attachments.length; index++) {
            this.release_preview_url(this.attachments[index]);
        }

        this.attachments = [];
        this.render_attachments();
    }

    // 開檔案選擇器（附件掣）
    attach_files(event) {
        event.preventDefault();

        const j_input = jQuery(".sc_composer__file_input");

        // 判定：頁面冇 file input → 唔動作
        if (j_input.length < 1) {
            return;
        }

        j_input.trigger("click");
    }

    // 選檔後加入清單（多選）
    attach_files_change(event) {
        const input_element = event.currentTarget;

        // 先複製成獨立陣列（FileList 係即時參考——清空 input 會變空）
        const selected_files = [];

        // 逐個檔抄入陣列
        for (let index = 0; index < input_element.files.length; index++) {
            selected_files.push(input_element.files[index]);
        }

        // 清空 input（同一個檔可以再選；必須喺複製之後）
        input_element.value = "";

        this.add_files(selected_files);
    }

    // 貼上（剪貼板圖片——Ctrl+V）
    attach_files_paste(event) {
        const clipboard_data = event.originalEvent.clipboardData;

        // 判定：冇剪貼板資料 → 唔動作
        if (!clipboard_data) {
            return;
        }

        const pasted_files = [];
        const clipboard_items = clipboard_data.items;

        // 逐項抽檔案（只有圖片）
        for (let index = 0; index < clipboard_items.length; index++) {
            const clipboard_item = clipboard_items[index];

            // 判定：唔係檔案 → 跳過
            if (clipboard_item.kind !== "file") {
                continue;
            }

            // 判定：唔係圖片 → 跳過
            if (clipboard_item.type.indexOf("image/") !== 0) {
                continue;
            }

            const pasted_file = clipboard_item.getAsFile();

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

        const j_editor = jQuery(event.currentTarget);
        j_editor.removeClass("sc_composer__editor--drag_over");

        const drag_data = event.originalEvent.dataTransfer;

        // 判定：冇拖放資料 → 唔動作
        if (!drag_data) {
            return;
        }

        this.add_files(drag_data.files);
    }

    // 背景上傳一張（完成／失敗都更新 chip 狀態）
    async start_upload(attachment, project_slug, chat_sid) {
        const upload_result = await this.upload_file(attachment, project_slug, chat_sid);

        // 判定：失敗 → 標記 failed（後端零寫入）
        if (upload_result === null) {
            attachment.state = "failed";
            this.render_attachments();

            return;
        }

        attachment.state = "done";
        attachment.draft_filename = upload_result.filename;
        attachment.thumb_data_url = upload_result.thumb_data_url || "";
        this.render_attachments();

        // 首次有附件落地 → URL 未帶 sid 就靜默換（重載可憑 sid 取回草稿）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        await prompt_instance.sync_chat_url(project_slug);
    }

    // 單次 multipart 上傳（XHR——原生二進位＋upload progress；跨裝置同一條路）
    upload_file(attachment, project_slug, chat_sid) {
        return new Promise(function (resolve) {
            const form_data = new FormData();
            form_data.append("project_slug", project_slug);
            form_data.append("chat_sid", chat_sid);
            form_data.append("file", attachment.file, attachment.name);

            const csrf_token = jQuery("meta[name='csrf-token']").attr("content") || "";

            const xhr = new XMLHttpRequest();
            xhr.open("POST", "/request/hkai/upload_draft_attachment/");
            xhr.setRequestHeader("X-CSRF-Token", csrf_token);

            sc_console("prompt", "attach").log("start | name=" + attachment.name + " | size=" + attachment.file.size + "B | chat_sid=" + chat_sid);

            // 狀態：上傳開始
            xhr.upload.onloadstart = function () {
                sc_console("prompt", "attach").log("0.00% | uploaded=0B | total=" + attachment.file.size + "B");
            };

            // 進度（真百分比——記入 attachment；畫面交 chip CSS）
            xhr.upload.onprogress = function (event) {
                // 判定：長度未知 → 唔記
                if (event.lengthComputable === false) {
                    sc_console("prompt", "attach").log("progress (length unknown) | loaded=" + event.loaded + "B");

                    return;
                }

                attachment.progress = event.loaded / event.total;

                const progress_percent = (attachment.progress * 100).toFixed(2);

                sc_console("prompt", "attach").log(progress_percent + "% | uploaded=" + event.loaded + "B | total=" + event.total + "B");
            };

            // 狀態：位元組已送完（等伺服器回應）
            xhr.upload.onload = function () {
                sc_console("prompt", "attach").log("100.00% | uploaded all bytes | waiting server response");
            };

            xhr.onload = function () {
                let result = null;

                // 判定：回應唔係 JSON → 當失敗
                try {
                    result = JSON.parse(xhr.responseText);
                }
                catch (parse_error) {
                    result = null;
                }

                sc_console("prompt", "attach").log("response | http=" + xhr.status + " | ok=" + (result !== null && result.ok === true) + " | body=" + xhr.responseText);

                // 判定：HTTP 非 200／冇結果／ok 唔係 true → 失敗
                if (xhr.status !== 200 || result === null || result.ok !== true) {
                    resolve(null);

                    return;
                }

                resolve(result);
            };

            xhr.onerror = function () {
                sc_console("prompt", "attach").log("error | network failure | name=" + attachment.name);

                resolve(null);
            };

            xhr.ontimeout = function () {
                sc_console("prompt", "attach").log("timeout | name=" + attachment.name);

                resolve(null);
            };

            xhr.send(form_data);
        });
    }

    // 圖片尺寸處理（≤2048 原樣；2049–4096 縮到 2048；>4096 警告＋拒收）
    resize_image_file(file_item) {
        const thisObject = this;

        return new Promise(function (resolve) {
            // 判定：非圖片 → 原樣
            if (file_item.type.indexOf("image/") !== 0) {
                resolve(file_item);
                return;
            }

            createImageBitmap(file_item).then(function (bitmap) {
                const max_side_px = Math.max(bitmap.width, bitmap.height);

                // 判定：超過硬上限 → 警告＋拒收
                if (max_side_px > thisObject.settings.max_source_side_px) {
                    bitmap.close();

                    const shared = registry().shared();
                    shared.sc_alert().warning(
                        "圖片過大",
                        "長邊超過 " + thisObject.settings.max_source_side_px + " px，已略過：" + file_item.name
                    );

                    resolve(null);
                    return;
                }

                // 判定：未超過目標尺寸 → 原樣
                if (max_side_px <= thisObject.settings.resize_target_side_px) {
                    bitmap.close();
                    resolve(file_item);
                    return;
                }

                const scale_ratio = thisObject.settings.resize_target_side_px / max_side_px;
                const target_width = Math.round(bitmap.width * scale_ratio);
                const target_height = Math.round(bitmap.height * scale_ratio);

                const canvas = document.createElement("canvas");
                canvas.width = target_width;
                canvas.height = target_height;

                const canvas_context = canvas.getContext("2d");
                canvas_context.drawImage(bitmap, 0, 0, target_width, target_height);
                bitmap.close();

                let output_mime = "image/jpeg";

                // 判定：PNG（可能有透明）→ 保持 PNG
                if (file_item.type === "image/png") {
                    output_mime = "image/png";
                }

                canvas.toBlob(function (blob) {
                    // 判定：轉換失敗 → 原樣（後端仍會處理）
                    if (!blob) {
                        resolve(file_item);
                        return;
                    }

                    const resized_file = new File([blob], file_item.name, {
                        type: output_mime,
                        lastModified: file_item.lastModified
                    });

                    resolve(resized_file);
                }, output_mime, RESIZE_JPEG_QUALITY);
            }).catch(function () {
                // 判定：解碼失敗 → 原樣（後端驗真實格式）
                resolve(file_item);
            });
        });
    }

    // 加入檔案（選檔／貼上／拖放共用——同名同大小同時間＝重複跳過）
    async add_files(file_list) {
        // 每次加入前重讀設定（composer 係後載入——init 時容器未存在，唔可以只讀一次）
        this.load_settings();

        let added_count = 0;

        // 草稿位置（SSOT：projects／chats 域；home 用 composer 專案）
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        let project_slug = project_instance.get_project_slug();

        const chats = registry().sc_ai_chats();
        const prompt_instance = await chats.prompt();
        const chat_sid = await prompt_instance.get_active_chat_sid();

        // 判定：URL 冇專案（home）→ 用 composer 專案
        if (project_slug === "") {
            project_slug = prompt_instance.get_composer_project_slug();
        }

        // 逐個檔加入暫存清單
        for (let index = 0; index < file_list.length; index++) {
            const file_item = file_list[index];

            // 判定：唔係圖片 → 跳過（附件只支援圖片）
            if (file_item.type.indexOf("image/") !== 0) {
                continue;
            }

            // 判定：已存在同一份檔 → 跳過
            if (this.has_attachment(file_item)) {
                continue;
            }

            // 判定：已達張數上限 → 警告並停
            if (this.attachments.length >= this.settings.max_images) {
                const shared = registry().shared();
                shared.sc_alert().warning("附件數量上限", "每條訊息最多 " + this.settings.max_images + " 張圖片");
                break;
            }

            // 尺寸處理（超上限＝null；縮圖後＝新 File）
            const resized_file = await this.resize_image_file(file_item);

            // 判定：尺寸超限已拒收 → 跳過
            if (resized_file === null) {
                continue;
            }

            const attachment = {};
            attachment.uid = this.next_uid;
            attachment.file = resized_file;
            attachment.origin = {};
            attachment.origin.name = file_item.name;
            attachment.origin.size = file_item.size;
            attachment.origin.lastModified = file_item.lastModified;
            attachment.name = file_item.name;
            attachment.size = resized_file.size;
            attachment.preview_url = URL.createObjectURL(resized_file);
            attachment.thumb_data_url = "";
            attachment.draft_filename = "";
            attachment.state = "uploading";
            this.next_uid = this.next_uid + 1;

            this.attachments.push(attachment);
            added_count = added_count + 1;

            // 即時上傳（唔等——chip 已顯示 loading）
            this.start_upload(attachment, project_slug, chat_sid);
        }

        // 判定：有新增 → 重畫
        if (added_count > 0) {
            this.render_attachments();
        }
    }

    // 判定是否已有同一份檔（原檔名＋大小＋修改時間）
    has_attachment(file_item) {
        for (let index = 0; index < this.attachments.length; index++) {
            const attachment_item = this.attachments[index];

            // 判定：還原附件（冇原檔）→ 用顯示名比對
            if (attachment_item.origin === null) {
                // 判定：同名 → 當重複
                if (attachment_item.name === file_item.name) {
                    return true;
                }

                continue;
            }

            const origin_item = attachment_item.origin;

            // 判定：檔名唔同 → 未重複
            if (origin_item.name !== file_item.name) {
                continue;
            }

            // 判定：大小唔同 → 未重複
            if (origin_item.size !== file_item.size) {
                continue;
            }

            // 判定：修改時間唔同 → 未重複
            if (origin_item.lastModified !== file_item.lastModified) {
                continue;
            }

            return true;
        }

        return false;
    }

    // 移除一項（chip 上 ×——同步刪後端）
    async attach_remove(event) {
        event.preventDefault();

        const uid = parseInt(jQuery(event.currentTarget).attr("data-attach_uid"), 10);
        let removed_attachment = null;
        const kept_attachments = [];

        // 逐項保留（uid 唔同＝留下）
        for (let index = 0; index < this.attachments.length; index++) {
            const attachment_item = this.attachments[index];

            // 判定：要移除呢項 → 記住＋釋放縮圖
            if (attachment_item.uid === uid) {
                removed_attachment = attachment_item;
                this.release_preview_url(attachment_item);
                continue;
            }

            kept_attachments.push(attachment_item);
        }

        this.attachments = kept_attachments;
        this.render_attachments();

        // 判定：未上傳過 → 唔使通知後端
        if (removed_attachment === null || removed_attachment.draft_filename === "") {
            return;
        }

        await this.delete_draft_file(removed_attachment.draft_filename);
    }

    // 通知後端刪草稿檔（同時 meta 移除）
    async delete_draft_file(draft_filename) {
        const projects = registry().sc_ai_projects();
        const project_instance = await projects.project();
        let project_slug = project_instance.get_project_slug();

        // 判定：URL 冇專案（home）→ 用 composer 專案
        if (project_slug === "") {
            const prompt_instance_home = await registry().sc_ai_chats().prompt();
            project_slug = prompt_instance_home.get_composer_project_slug();
        }

        const prompt_instance = await registry().sc_ai_chats().prompt();
        const delete_instance = await prompt_instance.req().request_delete_draft_attachment();

        const delete_payload = {};
        delete_payload.project_slug = project_slug;
        delete_payload.chat_sid = await prompt_instance.get_active_chat_sid();
        delete_payload.filename = draft_filename;

        let delete_result = null;

        await new Promise(function (resolve) {
            delete_instance.request_delete_draft_attachment(delete_payload, {
                success: function (result) {
                    delete_result = result;
                    resolve();
                },
                error: function (result) {
                    delete_result = result;
                    resolve();
                }
            });
        });

        // 判定：失敗 → 警告（檔案可能仍存在）
        if (delete_result === null || delete_result.ok !== true) {
            const shared = registry().shared();
            shared.sc_alert().warning("刪除失敗", "草稿檔案未能刪除，請重試");
            return;
        }

        // 成功 → 走同一條草稿還原（附件／文字都以後端為準；空清單＝附件區收起）
        const prompt_instance_restore = await registry().sc_ai_chats().prompt();
        const draft_restore_instance = await prompt_instance_restore.evt().prompt_draft_restore();

        await draft_restore_instance.prompt_draft_restore(true);
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
        const j_container = jQuery(".sc_composer__attachments");

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
        for (let index = 0; index < this.attachments.length; index++) {
            const attachment_item = this.attachments[index];
            j_container.append(this.build_attachment_chip(attachment_item));
        }
    }

    // 砌一個 chip（div——縮圖／loading／失敗感嘆號／檔名／大小／移除）
    build_attachment_chip(attachment_item) {
        const j_chip = jQuery("<div class='sc_composer__attachment'></div>");
        j_chip.attr("data-attach_uid", attachment_item.uid);
        j_chip.attr("data-state", attachment_item.state);

        let thumb_src = attachment_item.preview_url;

        // 判定：冇本地縮圖 → 用後端縮圖
        if (thumb_src === "") {
            thumb_src = attachment_item.thumb_data_url;
        }

        // 判定：有縮圖 → 圖片；否則 icon
        if (thumb_src !== "") {
            const j_thumb = jQuery("<img class='sc_composer__attachment_thumb'>");
            j_thumb.attr("src", thumb_src);
            j_thumb.attr("alt", attachment_item.name);
            j_chip.append(j_thumb);
        } else {
            j_chip.append(jQuery("<i class='fa-solid fa-paperclip'></i>"));
        }

        // 上傳中：CSS 轉圈
        if (attachment_item.state === "uploading") {
            j_chip.append(jQuery("<span class='sc_composer__attachment_spinner'></span>"));
        }

        // 失敗：紅色感嘆號（唔彈警告）
        if (attachment_item.state === "failed") {
            const j_failed = jQuery("<span class='sc_composer__attachment_failed' title='upload failed'>!</span>");
            j_chip.append(j_failed);
        }

        const j_name = jQuery("<span class='sc_composer__attachment_name'></span>");
        j_name.text(attachment_item.name);

        const j_size = jQuery("<span class='sc_composer__attachment_size'></span>");
        j_size.text(this.format_file_size(attachment_item.size));

        const j_remove = jQuery("<button class='sc_composer__attachment_remove' type='button' data-action='attach_remove'>×</button>");
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
})(new _evt_prompt_attach());
