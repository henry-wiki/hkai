// jsmods/prompt/act/act_prompt_send.js —— 發送訊息流程（act 層——一檔一事）
// 職責：prompt_send（接線 handler——送出＋回呼；驗證歸後台——E_VALIDATION_FATAL 錯誤卡）
// 本機側程序同檔：prompt_before_send（發送前）／prompt_send_success（已收下——含 RECOVERABLE 已記錄→入畫面）／prompt_send_error（未收下——還原輸入＋提示）
// 包含：append_render_message（訊息模板 insert——queue_dispatch 共用）／replace_slots（槽位替換）；主按鈕兩態交 prompt 域 prompt_send_button_state().refresh()（唯一決策者）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _act_prompt_send {
    constructor() {}

    // 發送（驗證唔過 → 阻斷（focus 返輸入框，內容保留）；過 → 交 handler 送）
    async act_prompt_send(request_data, ui_context) {
        // 派發 prompt_sent hook（before 相位——送出前；現時零 listener）
        registry().sc_ai_chats().execute_hook_before("prompt_sent", []);

        // 橋接駁（同域 req 橋——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_instance = await prompt_instance.req().prompt_send();

        // 已選附件（File 陣列——交 req 上傳後才送問）
        const prompt_attach_instance = await prompt_instance.evt().prompt_attach();

        // 判定：有附件仍在上傳中 → 警告＋中止（唔清輸入、唔送）
        if (prompt_attach_instance.has_uploading()) {
            registry().shared().sc_alert().warning(registry().sc_i18n().get("prompt.attach.uploading_title"), registry().sc_i18n().get("prompt.attach.uploading_desc"));

            return;
        }

        // *** : 步驟 1——先讀走文字（只讀一次）；必須早過畫面清空，否則 before_send 清空之後就冇字可送
        // 送出即擷取最終文字（唔再等 draft_save_meta 往返——文字隨 prompt_send 一併送，後端寫入 meta）
        const prompt_text_snapshot = String(ui_context.j_dock_textarea.val() || "");

        request_data.prompt_text       = prompt_text_snapshot;
        request_data.attachment_files = prompt_attach_instance.get_files();

        const thisObject = this;

        // *** : 步驟 2——最後才發 HTTP；所以一切畫面反應交 before_send 回呼，唔可以等 handler 之後先做
        return prompt_send_instance.prompt_send(request_data, {
            // 送出前：畫面即時反應（loading／清空／樂觀 chip＋氣泡／跳底）——統一交 before_send
            before_send: function () {
                thisObject.prompt_before_send(ui_context, request_data, prompt_text_snapshot);
            },
            // 已收下（正常送出／RECOVERABLE 已記錄）——輸入框維持清空；錯誤原因在訊息泡泡內呈現
            success: function (response_data) {
                thisObject.prompt_send_success(ui_context, request_data, response_data);
            },
            // 未收下（FATAL／網路失敗——後端不記錄）——還原輸入框內容（使用者不用重打）＋ 錯誤卡
            error: function (response_data) {
                thisObject.prompt_send_error(ui_context, request_data, response_data);
            }
        });
    }

    // 發送前程序（畫面即時反應一次做齊：loading／清空／樂觀 chip＋氣泡／跳底）
    async prompt_before_send(ui_context, request_data, prompt_text_snapshot) {
        const j_send_button = ui_context.j_send_button;

        // *** : 步驟 1——先落 in-flight 旗標；必須早過下一步 refresh，否則 refresh 讀到舊狀態
        j_send_button.attr("data-state", "loading");

        // *** : 步驟 2——交唯一決策者重算按鈕兩態（Send↔Stop）；唔自己寫判斷
        // 撳發送即刷新主按鈕（loading＝進行中 → Stop 即出；唯一決策者判）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_state_instance = await prompt_instance.act().prompt_send_button_state();

        prompt_send_button_state_instance.refresh();

        // *** : 步驟 3——立即清空輸入；使用者感知延遲就係呢一步，後台工作一律唔准阻塞畫面
        // 立即清空輸入（後台工作唔再阻塞畫面；失敗時交 prompt_send_error 還原）
        ui_context.j_dock_textarea.val("");

        // *** : 步驟 4——判排隊只讀畫面真旗標（data-working）；忙碌才插樂觀 chip（模板取 composer 內隱藏 div）
        // 判定：本對話已有回覆進行中 → 今次會入等待列 → 先插樂觀 chip（回應一到由後端整排替換）
        const is_chat_busy = jQuery(".sc_thread__viewport .message_wrapper[data-working='1']").length > 0;

        if (is_chat_busy) {
            const queue_template_b64 = jQuery("[data-tpl='queue_list_template']").text();
            const queue_template_bytes = Uint8Array.from(window.atob(queue_template_b64), (char_item) => char_item.charCodeAt(0));
            const queue_template_html = new TextDecoder("utf-8").decode(queue_template_bytes);
            const j_optimistic_chip = jQuery(queue_template_html);

            j_optimistic_chip.attr("data-optimistic", "1");
            j_optimistic_chip.find(".queue_text").text(prompt_text_snapshot.trim());

            // 空容器常駐（hidden）→ 解除隱藏才見到 chip
            jQuery(".sc_composer__queue_list").removeAttr("hidden").append(j_optimistic_chip);
        }

        // *** : 步驟 5——樂觀氣泡：同一份後端骨架（隱藏 div 內 base64），打完即見自己句字；data-optimistic 做日後移除／轉紅把手
        // 樂觀插入：取隱藏 div 的 base64 模板（唯一真相）→ 即時顯示自己打嘅字（回應一到換成真氣泡）
        const message_instance = await registry().sc_ai_chats().message();
        const message_template_b64 = jQuery("[data-tpl='message_template']").text();
        const message_template_bytes = Uint8Array.from(window.atob(message_template_b64), (char_item) => char_item.charCodeAt(0));
        const message_template_html = new TextDecoder("utf-8").decode(message_template_bytes);
        const j_optimistic_message = jQuery(message_template_html);

        j_optimistic_message.attr("data-optimistic", "1");
        j_optimistic_message.find("[data-text='user_content']").text(prompt_text_snapshot.trim());

        const j_viewport = jQuery(".sc_thread__viewport");
        const j_scroll_anchor = j_viewport.children("#sc_scroll_anchor");

        // 判定：有錨點 → 插錨點前（同真氣泡同一位置規則）
        if (j_scroll_anchor.length) {
            j_optimistic_message.insertBefore(j_scroll_anchor);
        } else {
            j_viewport.append(j_optimistic_message);
        }

        // *** : 步驟 6——跳底最後做；氣泡已入 DOM 才跳得正確
        const message_scroll_instance = await message_instance.act().message_scroll_to_bottom();

        message_scroll_instance.message_scroll_to_bottom(true);
    }

    // 本機側成功程序：已收下訊息入畫面（html.new_message 氣泡／html.queue_list 整排替換／is_new_chat_row 換 URL）＋send 按鈕復原
    async prompt_send_success(ui_context, request_data, response_data) {
        const j_send_button = ui_context.j_send_button;
        const result_html   = response_data.html || {};

        // 已收下 → 清空輸入（程式改值後經 input 管道對賬——autoGrow 還原高度）
        ui_context.j_dock_textarea.val("");
        ui_context.j_dock_textarea.trigger("input");

        // 附件已送出 → 清空 chips（失敗時保留——錯誤路徑唔清）
        const prompt_instance_attach = await registry().sc_ai_chats().prompt();
        const prompt_attach_instance = await prompt_instance_attach.evt().prompt_attach();
        prompt_attach_instance.clear_attachments();

        // 本次請求新建對話（後端事實 is_new_chat_row）→ 換 URL 入該對話（shallow——只追上畫面唔導航：唔觸發 URL 變化鏈、唔重載；URL 仍係唯一真相、重載後仍停在該對話）
        if (response_data.is_new_chat_row) {
            sc_url.shallow_replace_url(response_data.chat_url);

            const chat_instance_new_row = await registry().sc_ai_chats().chat();

            // 新對話卡只有後端一份渲染源 → 重抓一次清單（唔自己砌卡）
            const chat_list_fetch_instance = await chat_instance_new_row.act().chat_list_fetch();

            await chat_list_fetch_instance.chat_list_fetch();

            // 新卡入場 → 對齊窗側主鍵＋清單高亮（chat_item_switch 係唯一對齊路徑）
            const chat_item_switch_instance = await chat_instance_new_row.act().chat_item_switch();

            await chat_item_switch_instance.chat_item_switch(chat_instance_new_row.get_chat_sid());
        }

        // 真氣泡入場前，先移除樂觀臨時氣泡（避免重複顯示同一句）
        jQuery(".sc_thread__viewport .message_wrapper[data-optimistic='1']").remove();

        // 新訊息模板（後端渲染——insert；與重載時 fetch 同一渲染源——先清空狀態再插，同 message_list_fetch 同一條路）
        if (result_html.new_message) {
            // 隱藏窗口內空狀態（首次發訊前顯示 "Please start your chat"——節點長駐，只切 hidden）
            jQuery(".sc_thread__viewport").children(".sc_empty_state").prop("hidden", true);

            const j_message_wrapper = this.append_render_message(result_html.new_message, response_data);

            // 對話卡即時移去清單最頂（送出＝最近活動）
            const chat_instance_card = await registry().sc_ai_chats().chat();
            const chat_card_move_to_top_instance = await chat_instance_card.act().chat_card_move_to_top();

            chat_card_move_to_top_instance.chat_card_move_to_top(chat_instance_card.get_chat_sid());

            // t3＝新氣泡插入完成

            // 新訊息內容 markdown 化（交 message_editblock〔編輯區塊轉顯示形〕；空段／未到場＝no-op——poll 到場自會渲）
            const message_instance_editblock = await registry().sc_ai_chats().message();
            const message_editblock_instance = await message_instance_editblock.act().message_editblock();

            const j_response_result = j_message_wrapper.find("[data-text='result']");

            // 判定：有答案段 → 渲染
            if (j_response_result.length) {
                await message_editblock_instance.render_element(j_response_result);
            }

            // 送出訊息 → 跳到底部看最新（捲動事件會恢復跟隨）
            // 橋接駁（message 域 act 橋——跨域新方式）
            const message_instance = await registry().sc_ai_chats().message();
            const message_scroll_to_bottom_instance = await message_instance.act().message_scroll_to_bottom();

            message_scroll_to_bottom_instance.message_scroll_to_bottom(true);
        }

        // 等待列整排替換（後端渲染完整 UI——與頁面初始同一 builder；唔逐條插）
        // 判定：後端帶完整隊列 UI → 整排 bind（RECOVERABLE 等未帶 queue_list 嘅回應＝唔動現有 chips）
        if (result_html.queue_list) {
            // 橋接駁（queue 域 act 橋——跨域新方式）
            const queue_instance = await registry().sc_ai_chats().queue();
            const queue_list_instance = await queue_instance.act().queue_list();

            queue_list_instance.refresh_queue_list(result_html.queue_list);
        }

        // 出清 in-flight 標記（loading 完結——唔留「queued」假狀態；狀態一律交唯一決策者判）
        j_send_button.removeAttr("data-state");

        // 刷新主按鈕兩態（依 DOM 真旗標——RECOVERABLE 已記錄失敗＝即回 Send；正常送出＝接落追蹤）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_state_instance = await prompt_instance.act().prompt_send_button_state();

        prompt_send_button_state_instance.refresh();

        // 開串流（獨立動作；base_url 自動取；SSE 事件由 message_stream 處理內容）
        const message_instance_stream = await registry().sc_ai_chats().message();
        const message_stream_instance = await message_instance_stream.act().message_stream();

        await message_stream_instance.open(response_data.project_slug, response_data.chat_sid);

        // 派發 prompt_sent hook（after 相位——已收下；現時零 listener）
        registry().sc_ai_chats().execute_hook_after("prompt_sent", response_data);

        // 判定：送出期間曾撳停止（空窗期）→ 訊息一到手即發取消（B 方案配套）
        if (jQuery(document).data("sc_stop_pending")) {
            jQuery(document).data("sc_stop_pending", false);

            const prompt_stop_act_instance = await prompt_instance.act().prompt_stop();

            await prompt_stop_act_instance.prompt_stop(
                { message_id: response_data.message_id },
                { j_stop_button: jQuery("[data-action='stop']"), j_dock_textarea: ui_context.j_dock_textarea }
            );
        }
    }

    // 未收下（FATAL／網路失敗——後端不記錄）：按鈕對賬＋還原輸入＋提示（queue＝warning popup；其餘＝error modal）
    async prompt_send_error(ui_context, request_data, response_data) {
        // 動態載入（registry——用先載）
        const shared = registry().shared();

        const j_send_button   = ui_context.j_send_button;
        const j_dock_textarea = ui_context.j_dock_textarea;

        const error_result = response_data;
        const content      = request_data.content;

        // 出清 in-flight 標記（loading 完結——失敗唔可以扮「queued」；兩態交唯一決策者判）
        j_send_button.removeAttr("data-state");

        // 失敗 → 保留樂觀氣泡，標記 failed（畫面轉紅）；後端冇記錄 → 重載頁面即消失
        const j_optimistic_message = jQuery(".sc_thread__viewport .message_wrapper[data-optimistic='1']");

        // 失敗 → 移除樂觀排隊 chip（否則會殘留一個假排隊項目）
        jQuery(".sc_composer__queue_list .queue_item[data-optimistic='1']").remove();

        // 恢復 textarea 內容（沒送出去——不清空；用戶可重試）
        j_dock_textarea.val(content);

        // 按鈕對賬（恢復內容之後——重算先睇到真狀態；同步 send／stop）
        jQuery("#sc_input").trigger("input");

        // 標準欄位錯誤：逐欄紅框＋訊息（後端 errors＝{field_key:{message,code}}）
        const error_map = {};

        // 判定：後端有帶 errors → 收成 {field: message}
        if (error_result.errors) {
            const error_field_list = Object.keys(error_result.errors);

            for (let error_index = 0; error_index < error_field_list.length; error_index++) {
                const error_field = error_field_list[error_index];
                const error_entry = error_result.errors[error_field] || {};

                error_map[error_field] = String(error_entry.message || "");
            }
        }

        // 判定：有欄位錯誤 → 套落 composer（冇對應欄位會自然略過）
        if (Object.keys(error_map).length > 0) {
            shared.sc_web_utils().apply_form_errors(jQuery(".sc_composer"), error_map);
        }

        // 失敗氣泡收尾（狀態 failed＝紅色 �� 標記；後端冇記錄——重載即消失）
        if (j_optimistic_message.length > 0) {
            j_optimistic_message.attr("data-status", "failed");
            j_optimistic_message.attr("data-working", "0");

            const j_agent_message = j_optimistic_message.find(".agent_message");

            // 判定：有 agent 槽 → 三段表達（標題／原因／欄位錯誤／提示）；modal 照舊保留
            if (j_agent_message.length > 0) {
                j_agent_message.empty();

                const error_title   = String(error_result.title || "");
                const error_message = String(error_result.message || error_result.error || "");
                const error_hint    = String(error_result.hint || "");

                const j_error_box = jQuery("<div class='optimistic_error'></div>");
                j_error_box.attr("data-error_code", String(error_result.code || ""));

                // 判定：有標題 → 一行粗體
                if (error_title !== "") {
                    j_error_box.append(jQuery("<div class='optimistic_error__title'></div>").text(error_title));
                }

                // 判定：有原因 → 一行細字
                if (error_message !== "") {
                    j_error_box.append(jQuery("<div class='optimistic_error__message'></div>").text(error_message));
                }

                // 判定：有欄位錯誤 → 逐項列點（field_key：訊息）
                const error_field_list = Object.keys(error_result.errors || {});

                if (error_field_list.length > 0) {
                    const j_field_list = jQuery("<ul class='optimistic_error__fields'></ul>");

                    for (let error_index = 0; error_index < error_field_list.length; error_index++) {
                        const error_field = error_field_list[error_index];
                        const error_entry = error_result.errors[error_field] || {};
                        const error_field_message = String(error_entry.message || error_entry || "");

                        j_field_list.append(jQuery("<li></li>").text(error_field + "：" + error_field_message));
                    }

                    j_error_box.append(j_field_list);
                }

                // 判定：有提示 → 一行細字（可做咩）
                if (error_hint !== "") {
                    j_error_box.append(jQuery("<div class='optimistic_error__hint'></div>").text(error_hint));
                }

                j_agent_message.append(j_error_box);
            }
        }

        // 判定：等待上限（後台拒收——warning popup 即可；文案＝後台帶返）
        if (error_result.code === "E_QUEUE_LIMIT") {
            shared.sc_alert().warning(error_result.title, error_result.message);
            return;
        }

        // 其餘（FATAL／網路／未知——後台唔記錄）：error modal（文案＝後台帶返；未帶＝i18n 補位）
        const i18n = registry().sc_i18n();

        let title = error_result.title;
        // 判定：後台未帶 title → i18n 補位（連線層）
        if (!title) {
            title = i18n.get("error.E_NETWORK.title");
        }

        let popup_message = error_result.message;
        // 判定：後台未帶 message → 退取 error 欄
        if (!popup_message) {
            popup_message = error_result.error;
        }
        // 判定：仍然冇 → i18n 補位（連線層）
        if (!popup_message) {
            popup_message = i18n.get("error.E_NETWORK.hint");
        }
        // 判定：有 hint → 補一行
        if (error_result.hint) {
            popup_message = popup_message + "\n\n" + error_result.hint;
        }

        shared.sc_alert().error(title, popup_message);
    }

    // 後端返回 render（統一結構：template ＋ slots）→ insert template 到 scroll_anchor 前，再灌 slots
    append_render_message(render_html, result) {
        const j_message_wrapper = jQuery(render_html.template.message);
        j_message_wrapper.attr("data-message_id", result.message_id);

        // 錨點每次即時取（訊息窗口重載後錨點會換新——不可用建構時快取）
        j_message_wrapper.insertBefore(jQuery(".sc_thread__viewport").children("#sc_scroll_anchor"));

        this.replace_slots(j_message_wrapper, render_html.slots);

        // 內容套用完成（統一狀態軸——raw＝slots 灌咗、markdown 未標；同初始載入第 2 階段同口徑）
        j_message_wrapper.attr("data-render_status", "raw");

        return j_message_wrapper;
    }

    // 槽位替換（[data-slot='key'] 容器 → 換成 slots[key]（自帶容器）——空值或找不到目標就略過，不破壞畫面）
    replace_slots(j_scope, slots) {
        // 判定：無槽位資料 → 不做
        if (!slots) {
            return;
        }

        const slot_keys = Object.keys(slots);

        for (let i = 0; i < slot_keys.length; i++) {
            const slot_key = slot_keys[i];
            const slot_html = slots[slot_key];

            // 判定：空內容 → 略過（保留原容器，下一輪仍能填入）
            if (!slot_html) {
                continue;
            }

            const j_target = j_scope.find("[data-slot='" + slot_key + "']");

            // 判定：找不到目標 → 略過
            if (!j_target.length) {
                continue;
            }

            j_target.replaceWith(slot_html);
        }
    }
}

export const prompt_send = (function (instance) {
    return function () {
        return instance;
    };
})(new _act_prompt_send());
