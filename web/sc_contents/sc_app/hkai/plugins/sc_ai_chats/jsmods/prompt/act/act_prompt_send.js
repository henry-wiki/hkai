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

        // 撳送出即入 loading（flush／提交期間都有反饋）
        ui_context.j_send_button.attr("data-state", "loading");

        const prompt_send_button_state_instance = await prompt_instance.act().prompt_send_button_state();
        prompt_send_button_state_instance.refresh();

        // 最後一次草稿保存（清走未到期節流＋即寫一次——後端只憑 meta 取內容）
        const prompt_draft_save_instance = await prompt_instance.act().prompt_draft_save();
        await prompt_draft_save_instance.flush_prompt_draft();

        request_data.attachment_files = prompt_attach_instance.get_files();

        const thisObject = this;

        return prompt_send_instance.prompt_send(request_data, {
            before_send: function () {
                thisObject.prompt_before_send(ui_context, request_data);
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

    // 發送前程序：按鈕 loading（輸入保留——收下後才清，避免空白草稿搶先覆蓋）
    async prompt_before_send(ui_context, request_data) {
        const j_send_button = ui_context.j_send_button;

        j_send_button.attr("data-state", "loading");

        // 撳發送即刷新主按鈕（loading＝進行中 → Stop 即出；唯一決策者判）
        // 橋接駁（同域 act——新方式）
        const prompt_instance = await registry().sc_ai_chats().prompt();
        const prompt_send_button_state_instance = await prompt_instance.act().prompt_send_button_state();

        prompt_send_button_state_instance.refresh();
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
        }

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

        await message_stream_instance.open(response_data.project_slug, response_data.chat_sid, response_data.prompt_id, 0);

        // 派發 prompt_sent hook（after 相位——已收下；現時零 listener）
        registry().sc_ai_chats().execute_hook_after("prompt_sent", response_data);
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

        // 恢復 textarea 內容（沒送出去——不清空）
        j_dock_textarea.val(content);

        // 按鈕對賬（恢復內容之後——重算先睇到真狀態；同步 send／stop）
        jQuery("#sc_input").trigger("input");

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
