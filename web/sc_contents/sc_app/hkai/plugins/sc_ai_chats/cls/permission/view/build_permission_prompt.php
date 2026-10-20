<?php

namespace sc\app\hkai\plugins\sc_ai_chats\permission\view;

// == sc_ai_chats permission view build_permission_prompt：permission 域顯示動作（trait——掛入 permission_Class；檔＝動作） ==
// == 讀 msg_permission.json（pending 請求）＋核對 msg_permission_decision.json → 砌提示卡 HTML（後端砌 UI——前端只 insert） ==

trait build_permission_prompt
{

    // 權限提示 payload（無 pending／已決定＝null；有＝{request_id, deadline_ts, html}）
    public function build_permission_prompt($chat_id, $message_id)
    {
        // pending 權限請求（Python ACP 側寫——無檔＝今輪未出過）
        $permission_request_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "permission");

        // 判定：無請求檔 → 無提示
        if (!is_file($permission_request_file)) {
            return null;
        }

        $permission_request = json_decode(file_get_contents($permission_request_file), true);

        // 判定：請求檔唔完整 → 無提示
        if (!is_array($permission_request)) {
            return null;
        }

        // 從請求檔取值（同名變數）
        $request_id  = (string)($permission_request["request_id"] ?? "");
        $deadline_ts = (int)($permission_request["deadline_ts"] ?? 0);

        // 判定：request_id 缺 → 無提示
        if ($request_id === "") {
            return null;
        }

        // 決定檔（已決定＝提示收——request_id 對唔上＝上一請求殘留，唔算已決）
        $permission_decision_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "permission_decision");

        // 判定：已有決定（同一請求）→ 無提示
        if ($this->is_permission_request_decided($permission_decision_file, $request_id)) {
            return null;
        }

        $permission_payload = [];
        $permission_payload["request_id"]  = $request_id;
        $permission_payload["deadline_ts"] = $deadline_ts;
        $permission_payload["html"]        = $this->render_permission_prompt($request_id, $deadline_ts, $permission_request);

        return $permission_payload;
    }

    // 決定檔核對（同 request_id 先算已決——殘留／壞檔＝未決）
    private function is_permission_request_decided($permission_decision_file, $request_id)
    {
        // 判定：無決定檔 → 未決
        if (!is_file($permission_decision_file)) {
            return false;
        }

        $permission_decision = json_decode(file_get_contents($permission_decision_file), true);

        // 判定：決定檔唔完整 → 未決
        if (!is_array($permission_decision)) {
            return false;
        }

        return (string)($permission_decision["request_id"] ?? "") === $request_id;
    }

    // 提示卡 HTML（標題＋倒數＋工具名＋參數＋允許／拒絕（右手邊）＋套用到全部 checkbox——後端一次砌好）
    private function render_permission_prompt($request_id, $deadline_ts, $permission_request)
    {
        // 從請求檔取值（同名變數）
        $tool_title = (string)($permission_request["tool_title"] ?? "");
        $tool_input = $permission_request["tool_input"] ?? "";

        // 工具參數顯示文字（陣列 → pretty JSON；純字串直接用）
        $tool_input_text = $tool_input;
        if (is_array($tool_input)) {
            $tool_input_text = json_encode($tool_input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $tool_input_text = (string)$tool_input_text;

        // 倒數顯示（伺服器當下剩餘秒——前端 tick 接手更新）
        $remain_sec = max(0, $deadline_ts - time());

        // 顯示資料整包一次過轉義（框架 helper——禁止逐欄呼叫）
        $display_data = sc_html_encode([
            "request_id"       => $request_id,
            "tool_title"       => $tool_title,
            "tool_input_text"  => $tool_input_text,
            "title_label"      => hkai__i18n()->get("permission.title"),
            "allow_label"      => hkai__i18n()->get("permission.allow"),
            "deny_label"       => hkai__i18n()->get("permission.deny"),
            "apply_all_label"  => hkai__i18n()->get("permission.apply_all"),
            "auto_deny_suffix" => hkai__i18n()->get("permission.auto_deny_suffix"),
        ]);

        // 從轉義後結構取值（同名變數）
        $request_id       = $display_data["request_id"];
        $tool_title       = $display_data["tool_title"];
        $tool_input_text  = $display_data["tool_input_text"];
        $title_label      = $display_data["title_label"];
        $allow_label      = $display_data["allow_label"];
        $deny_label       = $display_data["deny_label"];
        $apply_all_label  = $display_data["apply_all_label"];
        $auto_deny_suffix = $display_data["auto_deny_suffix"];

        // HTML 逐行收集（一行一 tag；父子分行縮排；一次 implode 輸出）
        $permission_prompt_lines   = [];
        $permission_prompt_lines[] = "<div class='permission_prompt' data-request_id='{$request_id}' data-deadline_ts='{$deadline_ts}'>";
        $permission_prompt_lines[] = "    <div class='permission_prompt__head'>";
        $permission_prompt_lines[] = "        <span class='permission_prompt__title'>{$title_label}</span>";
        $permission_prompt_lines[] = "        <span class='permission_prompt__countdown' data-suffix='{$auto_deny_suffix}'>{$remain_sec}s{$auto_deny_suffix}</span>";
        $permission_prompt_lines[] = "    </div>";
        $permission_prompt_lines[] = "    <div class='permission_prompt__tool'>{$tool_title}</div>";
        $permission_prompt_lines[] = "    <pre class='permission_prompt__args'>{$tool_input_text}</pre>";
        $permission_prompt_lines[] = "    <div class='permission_prompt__actions'>";
        $permission_prompt_lines[] = "        <button type='button' class='permission_prompt__btn permission_prompt__btn--allow' data-action='permission_decide' data-decision='allow' data-request_id='{$request_id}'>{$allow_label}</button>";
        $permission_prompt_lines[] = "        <button type='button' class='permission_prompt__btn permission_prompt__btn--deny' data-action='permission_decide' data-decision='deny' data-request_id='{$request_id}'>{$deny_label}</button>";
        $permission_prompt_lines[] = "    </div>";
        $permission_prompt_lines[] = "    <label class='permission_prompt__apply_all'>";
        $permission_prompt_lines[] = "        <input type='checkbox' class='permission_prompt__apply_all_box'>";
        $permission_prompt_lines[] = "        <span class='permission_prompt__apply_all_text'>{$apply_all_label}</span>";
        $permission_prompt_lines[] = "    </label>";
        $permission_prompt_lines[] = "</div>";

        return implode("\r\n", $permission_prompt_lines);
    }
}
