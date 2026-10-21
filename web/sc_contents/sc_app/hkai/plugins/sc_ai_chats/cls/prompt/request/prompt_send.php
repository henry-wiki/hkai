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
        
        // ===== 專案列（建立新對話需要 project_id——先取；查唔到一律 fallback Home）=====
        $existing_project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 判定：查唔到（空 slug／無效 slug）→ fallback 查固定 Home 專案
        if ($existing_project_row === null) {
            $existing_project_row = sc_ai_projects()->project()->get_project_row("home");
        }

        // 判定：連 Home 都查唔到（資料壞）→ 嚴重錯（唔靜默）
        if ($existing_project_row === null) {
            return sc_response()->json(["ok" => false, "error" => "home project not found"], 500);
        }

        // 專案欄位（一律由已解析嘅 row 取——下游唔會再見 null）
        $project_slug = (string)$existing_project_row["project_slug"];
        $project_id   = (int)$existing_project_row["project_id"];
        $project_path = (string)$existing_project_row["project_path"];

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
        $server_result = hkai__python_bridge_check()->validate_server_running();
        if ($server_result["ok"] === false) {
            return sc_response()->json($server_result);
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
                "title"   => hkai__i18n()->get("queue.limit_title"),
                "message" => hkai__i18n()->get("queue.limit_message"),
            ]);
        }

        // ===== 新對話（chat_sid==='' 100% 確定）——驗證全過才建立 =====
        $chat_created_this_request = false;

        // 判定：未指定對話 → 建立新對話列
        if ($chat_sid === "") {
            $chat_data                     = [];
            $chat_data["chat_sid"]         = sc_generate_uuid();
            $chat_data["chat_title"]       = sc_ai_chats()->chat()->build_chat_title($content);
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
        
        // 環境初始化（生成時機＝發問——與訊息入庫同刻；檢視／建立都唔再觸發）
        // 缺檔先寫（已有＝唔動）；偵測失敗＝零寫入（唔阻發送）
        sc_ai_projects()->tab_environment()->write_project_environment_file($existing_project_row, false);

        // 系統指示防線（發問前保證 root 兩檔可得：system_prompt／system_env；缺＝由 manifest 默認補；皆缺＝錯誤）
        $instructions_result = sc_ai_settings()->instructions()->ensure_system_instructions();

        // 判定：系統指示檔攞唔到（連默認都無）→ 回滾新對話＋返回錯誤（訊息不送出）
        if ($instructions_result === false) {
            sc_ai_chats()->prompt()->rollback_created_chat($existing_chat_row, $chat_created_this_request);
            return sc_response()->json(["ok" => false, "error" => "system instructions unavailable"], 500);
        }
        
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
        $conf_data["model_name"]        = $conf_model_name;
        $conf_data["chat_mode"]         = $conf_chat_mode;
        $conf_data["reasoning_effort"]  = $conf_reasoning_effort;
        $conf_data["context_length"]    = $conf_context_length;
        
        // 單請求逾時（全域 config——manifest aider_daemon.request_timeout_sec；缺 key → 180 秒兜底）
        $aider_daemon_config             = hkai__core()->get_hkai_configs("aider_daemon");
        $request_timeout_sec            = $aider_daemon_config["request_timeout_sec"] ?? 180;
        $conf_data["request_timeout"]   = $request_timeout_sec;
        
        // 入等待列（queue 檔——append 一行：queue_uid + conf_data + user_prompt；順序＝送達順序；shift 即時出列）
        $prompt_message = [];
        $prompt_message["conf_data"]   = $conf_data;
        $prompt_message["user_prompt"] = $content;
        
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