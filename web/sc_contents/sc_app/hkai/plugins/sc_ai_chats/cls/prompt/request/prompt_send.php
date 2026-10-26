<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\request;

// == sc_ai_chats prompt prompt_send：prompt 域動作（trait——掛入 prompt_Class；bridging sc_ai_chats()->prompt()） ==
// == 職責：prompt_send——送出訊息端點（對話解析＋三關驗證＋訊息寫入＋入等待列＋派工） ==
// == 2026-09-24 自 cls/message/request.php 搬入（端點歸 prompt 域） ==

trait prompt_send
{
    
    // POST prompt_send：送出一則 user 訊息到指定 chat（無 chat_sid = 新對話模式）
    public function prompt_send()
    {
        $input_data         = sc_request()->json();
        
        $content            = trim($input_data["content"]);
        
        $provider_id        = @trim($input_data["provider_id"]  );
        $model_id           = @trim($input_data["model_id"]     );
        $chat_mode          = @trim($input_data["chat_mode"]     );
        $reasoning_effort   = isset($input_data["reasoning_effort"]) ? $input_data["reasoning_effort"] : null;
        
        $project_slug       = isset($input_data["project_slug"]) ? $input_data["project_slug"] : "";
        $chat_sid           = isset($input_data["chat_sid"]) ? $input_data["chat_sid"] : "";
        $attachment_ids     = isset($input_data["attachment_ids"]) ? $input_data["attachment_ids"] : [];

        // 判定：附件 id 唔係陣列 → 當冇附件
        if (!is_array($attachment_ids)) {
            $attachment_ids = [];
        }
        
        // ===== 專案列（建立新對話需要 project_id——先取；查唔到一律 fallback Home）=====
        $existing_project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到（空 slug／無效 slug）→ fallback 查固定 Home 專案
        if ($existing_project_row === null) {
            $existing_project_row = sc_ai_projects()->project()->get_project_row(sc_get_config("hkai.default_project_slug"));
        }

        // 判定：連 Home 都查唔到（資料壞）→ 嚴重錯（唔靜默）
        if ($existing_project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        // 專案欄位（一律由已解析嘅 row 取——下游唔會再見 null）
        $project_slug = (string)$existing_project_row["project_slug"];
        $project_id   = (int)$existing_project_row["project_id"];
        $project_path = (string)$existing_project_row["project_path"];

        // 附件列（只取本專案＋未綁定——無效 id 自動剔走）
        $attachment_rows = sc_ai_chats()->attachment()->get_rows_by_ids($project_id, $attachment_ids);

        // ===== 草稿附件（拉入即上傳——送出時才匯入正式附件目錄並寫 DB）=====
        $draft_key = sc_ai_chats()->draft()->get_draft_key($chat_sid);

        // 判定：未帶舊附件 id（新流程）→ 匯入草稿附件
        if (count($attachment_ids) < 1) {
            $draft_files   = sc_ai_chats()->draft()->list_draft_files($project_slug, $draft_key);
            $imported_count = 0;

            // 逐個草稿檔匯入（失敗＝略過）
            foreach ($draft_files as $draft_file) {
                $imported_row = sc_ai_chats()->attachment()->import_draft_file($existing_project_row, $draft_file["path"], $draft_file["filename"]);

                // 判定：匯入成功 → 加入待綁定清單
                if ($imported_row !== null) {
                    $attachment_rows[] = $imported_row;
                    $attachment_ids[]  = (int)$imported_row["attachment_id"];
                    $imported_count    = $imported_count + 1;
                }
            }

            // 判定：有檔案匯入 → 標記交收＋清空清單（冇匯入＝上傳未完成——保留草稿等重試）
            if ($imported_count > 0) {
                sc_ai_chats()->draft()->mark_status($project_slug, $draft_key, "submitted");
                sc_ai_chats()->draft()->clear_draft_attachments($project_slug, $draft_key);
            }
        }

        $valid_attachment_ids = [];

        // 逐個有效附件收集 id（綁定用）
        foreach ($attachment_rows as $attachment_row_item) {
            $valid_attachment_ids[] = (int)$attachment_row_item["attachment_id"];
        }

        // 判定：內容空白＋冇有效附件 → 冇問題可送
        if ($content === "" && count($attachment_rows) < 1) {
            return sc_response()->json(["ok" => false, "error" => "請填寫訊息內容或加入圖片"], 400);
        }

        // ===== 發問前預檢：清 repo 幽靈（git 名單有、磁碟冇——唔清會令 aider repo-map 白行＋逐個警告）=====
        // 非致命：bridge 未行＝request 回失敗結構（300ms connect timeout）——衛生動作，唔阻發送
        // 判定：有專案路徑 → 清 repo 幽靈
        if ($project_path !== "") {
            sc_ai_diagnostics()->git()->run_clean_repo_ghosts($project_path);
        }
        
        // ===== 對話列（既有先取；新對話要等三關驗證通過才建立）=====
        $existing_chat_row = null;

        // 判定：有 chat_sid → 取回既有對話列（下面 queue 檢查要用）
        if ($chat_sid !== "") {
            $existing_chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        }

        // ===== 第一關：前置條件（FATAL——不通過就停，唔建對話、不記錄）=====
        $prereq_result = sc_ai_chats()->message()->validate_message_prerequisites($input_data);
        if ($prereq_result["ok"] === false) {
            return sc_response()->json($prereq_result);
        }

        // ===== 第二關：伺服器就緒（FATAL——不通過就停，唔建對話、不記錄）=====
        $server_status = hkai()->process()->server()->get_server_status();

        // 判定：bridge 唔活 → 回統一錯誤結構（E_SERVER_DOWN）
        if ($server_status["is_alive"] !== true) {
            $server_down_result = [];
            $server_down_result["ok"]      = false;
            $server_down_result["code"]    = "E_SERVER_DOWN";
            $server_down_result["title"]   = hkai()->i18n()->get("error.E_SERVER_DOWN.title");
            $server_down_result["message"] = "無法連接本機伺服器，訊息未送出。";
            $server_down_result["hint"]    = "請先啟動伺服器（Settings → Server →「啟用」）再重新發送。";

            return sc_response()->json($server_down_result);
        }

        // ===== 第三關：可處理性（不通過就停——唔建對話、不記錄）=====
        $proc_result = sc_ai_chats()->message()->validate_message_processable($input_data);
        if ($proc_result["ok"] === false) {
            return sc_response()->json($proc_result);
        }

        // ===== 排隊允許檢查（queue 檔最多 3 條；滿即拒收，唔記錄）=====
        $queue_lines = [];

        // 判定：既有對話 → 讀該對話 queue 檔（等待列）
        if ($existing_chat_row !== null) {
            $queue_lines = sc_ai_chats()->queue()->get_queue_lines($existing_chat_row["chat_id"]);
        }

        // 判定：等待列（queue 檔）已滿（≥3）→ 拒收
        if (count($queue_lines) >= 3) {
            return sc_response()->json([
                "ok"      => false,
                "code"    => "E_QUEUE_LIMIT",
                "title"   => hkai()->i18n()->get("queue.limit_title"),
                "message" => hkai()->i18n()->get("queue.limit_message"),
            ]);
        }

        // ===== 新對話（chat_sid==='' 100% 確定）——驗證全過才建立 =====
        $chat_created_this_request = false;

        // 判定：未指定對話／指定咗但 DB 未有（新 prompt 預配 sid）→ 建立新對話列
        if ($existing_chat_row === null) {
            $chat_data                     = [];
            $chat_data["chat_sid"]         = ($chat_sid !== "") ? $chat_sid : sc_generate_uuid();
            // 標題：有文字用文字；純圖片用第一個附件原檔名
            $chat_title_text = $content;

            // 判定：冇文字＋有附件 → 用附件檔名
            if ($chat_title_text === "" && count($attachment_rows) > 0) {
                $chat_title_text = (string)$attachment_rows[0]["original_filename"];
            }

            $chat_data["chat_title"]       = sc_ai_chats()->chat()->build_chat_title($chat_title_text);
            $chat_data["provider_id"]      = $provider_id;
            $chat_data["model_id"]         = $model_id;
            $chat_data["reasoning_effort"] = $reasoning_effort !== null ? $reasoning_effort : "";
            $chat_data["chat_mode"]        = $chat_mode;

            $existing_chat_row = sc_ai_chats()->chat()->insert_db_chat($project_id, $chat_data);
            $chat_created_this_request = true;
        }

        // 對話列（前方已解析——新對話已建立／既有已取回）
        if ($existing_chat_row === null) {
            return sc_response()->json(["ok" => false, "error" => "failed to create chat"], 500);
        }
        
        $chat_sid = $existing_chat_row["chat_sid"];
        $chat_id  = $existing_chat_row["chat_id"];

        // 新對話落地：草稿存在 → 標記轉「非新」（DB 已有 chat row；冇草稿唔建空 meta）
        $final_draft_key = sc_ai_chats()->draft()->get_draft_key($chat_sid);
        $final_draft_dir = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $final_draft_key);

        // 判定：草稿目錄存在 → 更新標記
        if (is_dir($final_draft_dir)) {
            sc_ai_chats()->draft()->update_meta($project_slug, $final_draft_key, ["is_new_chat" => 0]);
        }

        // 新對話落地：草稿目錄由 "new" 搬去真 chat_sid（一對話一目錄）
        if ($chat_created_this_request === true) {
            sc_ai_chats()->draft()->claim_new_draft($project_slug, $chat_sid);
        }
        
        // 環境初始化（生成時機＝發問——與訊息入庫同刻；檢視／建立都唔再觸發）
        // 缺檔先寫（已有＝唔動）；偵測失敗＝零寫入（唔阻發送）
        sc_ai_projects()->tab_environment()->write_project_environment_file($existing_project_row, false);

        // chat mode 正規化（DB 與 conf 同一值——單一事實來源）
        $conf_chat_mode = sc_ai_chats()->chat_option()->get_chat_mode($chat_mode);
        
        // 本條係唔係對話第一則（進場前 count＝0——新對話或空對話；count 由 repo 喺 insert 時重算）
        $is_first_message = ((int)$existing_chat_row["chat_message_count"] === 0);
        
        // 組 conf_data（入 queue 行——shift 時先建檔：conf/status/log/prompt）
        $conf_model_name = $provider_id . "/" . $model_id;
        $conf_reasoning_effort = $reasoning_effort !== null ? $reasoning_effort : "";

        // 上下文長度（model store context_length——agent 決定何時壓縮；第三關已把關——直接取用）
        $conf_model_item = sc_ai_providers()->repo()->get_model_item($provider_id, $model_id);
        $conf_context_length = $conf_model_item["context_length"];
        
        $conf_data = [];
        $conf_data["project_path"]      = $project_path;
        $conf_data["project_id"]        = $project_id;
        $conf_data["project_slug"]      = $project_slug;
        $conf_data["chat_id"]           = $chat_id;
        $conf_data["chat_sid"]          = $chat_sid;
        $conf_data["model_name"]        = $conf_model_name;
        $conf_data["chat_mode"]         = $conf_chat_mode;
        $conf_data["reasoning_effort"]  = $conf_reasoning_effort;
        $conf_data["context_length"]    = $conf_context_length;
        
        // 附件（原檔絕對路徑＋MIME——交 daemon 以 image block 傳模型；唔做文字抽取）
        $attachment_files = [];

        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();

        // 逐個附件砌路徑
        foreach ($attachment_rows as $attachment_row) {
            $attachment_file = [];
            $attachment_file["path"]      = $storage_dir . "/" . $attachment_row["filepath"];
            $attachment_file["mime_type"] = $attachment_row["mime_type"];
            $attachment_files[] = $attachment_file;
        }

        $conf_data["attachments"] = $attachment_files;

        // 入等待列（queue 檔——append 一行：queue_uid + conf_data + user_prompt；順序＝送達順序；shift 即時出列）
        $prompt_message = [];
        $prompt_message["conf_data"]      = $conf_data;
        $prompt_message["user_prompt"]    = $content;
        $prompt_message["attachment_ids"] = $valid_attachment_ids;
        
        $queue_uid = sc_ai_chats()->queue()->append_queue_line($chat_id, $prompt_message);
        
        // 送出（行動點）對賬：殘留終態即場收尾（唔清走會令「忙」嘅判定永真）
        sc_ai_chats()->poll()->finalize_poll_jobs($chat_id);

        // 立即 shift：對話空閒即隊頭進場（出列＋建 DB 列＋建檔＋通知 bridge）；忙＝留隊
        $shift_result = sc_ai_chats()->queue()->shift_queue_message($chat_id);

        // 新訊息氣泡：只有「本條＝進場嗰條」先回——佢先係真正進入對話；仍排隊＝空（佢只出現喺等待列）
        $new_message    = null;
        $new_message_id = 0;

        // 判定：本條已進場（shift 回傳 uid＝本條 uid）→ 出氣泡模板
        if (
            $shift_result !== null
            && $shift_result["queue_uid"] === $queue_uid
        ) {
            $message_row    = $shift_result["message_row"];
            $new_message    = sc_ai_chats()->message()->view()->get_chat_message_slot($message_row, [], true);
            $new_message_id = (int)$message_row["message_id"];

            // 圖片抽取（非阻塞——status 已由 materialize 寫成 preparing）
            $extract_files    = [];
            $attachment_index = 0;

            // 逐個附件砌抽取清單（只收圖片——文件暫時跳過）
            foreach ($attachment_rows as $attachment_row_item) {
                $attachment_index++;
                $attachment_mime = (string)$attachment_row_item["mime_type"];

                // 判定：非圖片 → 跳過
                if (strpos($attachment_mime, "image/") !== 0) {
                    continue;
                }

                $extract_file = [];
                $extract_file["idx"]  = $attachment_index;
                $extract_file["path"] = $storage_dir . "/" . $attachment_row_item["filepath"];
                $extract_file["mime"] = $attachment_mime;
                $extract_files[] = $extract_file;
            }

            // 判定：有圖片 → 交 bridge 拉起 image_extractor（PHP 唔等結果）
            if (count($extract_files) > 0) {
                $extract_payload = [];
                $extract_payload["message_id"]   = $new_message_id;
                $extract_payload["chat_id"]      = $chat_id;
                $extract_payload["chat_sid"]     = $chat_sid;
                $extract_payload["project_slug"] = $project_slug;
                $extract_payload["files"]        = $extract_files;

                $extract_result = hkai()->process()->bridge()->request("/image_extract/start/", $extract_payload, 10);

                // 判定：派發失敗 → 記錄＋標 failed（避免卡 preparing）
                if (($extract_result["ok"] ?? false) !== true) {
                    hkai()->runtime()->logger()->error("image_extract start failed: message_id=" . $new_message_id . " error=" . (string)($extract_result["error"] ?? "unknown"));
                    sc_ai_chats()->poll()->append_msg_status_by_event($chat_id, $new_message_id, "FAILED");
                }
            }
        }
        
        // 完整隊列 UI（共用 builder——傳 chat_id 即掃＋砌整排；前端只 bind）
        $queue_list = sc_ai_chats()->prompt()->render_queue_list($chat_id);
        
        
        $response_json = [];
        // 前端採用新對話用嘅事實：chat_created（本次請求新建）／is_first_message（本條係第一則）／chat_url（後端砌好）
        $response_json["ok"]               = true;
        $response_json["project_slug"]     = $project_slug;
        $response_json["chat_sid"]         = $chat_sid;
        // ------------------------------------------------------------------------------------
        $response_json["chat_id"]          = $chat_id;
        $response_json["message_id"]       = $new_message_id;
        // ----------------------------------------------------------------------------------------------
        $response_json["chat_created"]     = $chat_created_this_request;
        $response_json["is_first_message"] = $is_first_message;
        // 送信只喺普通語境（歸檔 composer 空白——到唔到呢度）→ 專案狀態固定 normal；URL 形交 build_chat_url 統一砌
        $response_json["chat_url"]         = sc_ai_chats()->chat()->build_chat_url($project_slug, "normal", $chat_sid);
        
        $response_json["html"]["new_message"] = $new_message;
        $response_json["html"]["queue_list"]  = $queue_list;

        return sc_response()->json($response_json);
    }
    
}