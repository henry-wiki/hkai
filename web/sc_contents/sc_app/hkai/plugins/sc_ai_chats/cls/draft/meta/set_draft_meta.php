<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\meta;

// == sc_ai_chats draft set_draft_meta：持鎖寫 meta（取現有／模板 → 覆蓋新值 → 原子寫）＋私有寫檔與模板（trait——掛入 draft_Class） ==
// == 上游：上傳／存 meta／刪檔／狀態更新 ==

trait set_draft_meta
{

    // 寫草稿 meta（持鎖：取現有／模板 → 覆蓋新值 → 原子寫；回 bool）
    public function set_draft_meta($project_slug, $chat_sid, $meta_data)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $chat_sid);

        sc_mkdir($draft_dir);

        $lock_handle = @fopen($draft_dir . "/meta.json.lock", "c");

        // 判定：開唔到鎖檔 → 無鎖照做（退化：行為同舊版）
        if ($lock_handle !== false) {
            @flock($lock_handle, LOCK_EX);
        }

        $previous_meta = $this->get_draft_meta($project_slug, $chat_sid);

        $meta_base = $previous_meta;

        // 判定：未建立過草稿 → 用模板做起點
        if (count($meta_base) < 1) {
            $meta_base = $this->_get_draft_meta_template($project_slug, $chat_sid);
        }

        // 判定：草稿未有專案身份（首次寫入／舊草稿／路徑未填）→ 由 project_slug 補齊（一次性查詢）
        if (
            (int)($meta_base["project_id"] ?? 0) < 1
            || (string)($meta_base["project_path"] ?? "") === ""
        ) {
            $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

            // 判定：專案存在 → 蓋上 id／path
            if ($project_row !== null) {
                $meta_base["project_id"]   = (int)$project_row["project_id"];
                $meta_base["project_path"] = (string)$project_row["project_path"];
            }
        }

        $meta_base["updated_at"] = (int)floor(microtime(true) * 1000);

        // 新值逐欄覆蓋
        foreach ($meta_data as $meta_key => $meta_value) {
            $meta_base[$meta_key] = $meta_value;
        }

        // 標題由草稿內容決定（每次寫入更新——上帶後唔再計）
        $meta_base = $this->_refresh_chat_title($meta_base);

        // 判定：除 updated_at 外內容完全一樣 → 唔寫檔（mtime 不變）
        if (count($previous_meta) > 0) {
            $previous_compare = $previous_meta;
            $current_compare  = $meta_base;

            unset($previous_compare["updated_at"]);
            unset($current_compare["updated_at"]);

            $previous_json = json_encode($previous_compare, JSON_UNESCAPED_UNICODE);
            $current_json  = json_encode($current_compare, JSON_UNESCAPED_UNICODE);

            // 判定：一樣 → 解鎖收檔、回成功（零寫入）
            if ($previous_json === $current_json) {
                // 判定：有鎖 → 解鎖收檔
                if ($lock_handle !== false) {
                    @flock($lock_handle, LOCK_UN);
                    @fclose($lock_handle);
                }

                return true;
            }
        }

        $saved = $this->_write_meta_file($draft_dir, $meta_base);

        // 判定：有鎖 → 解鎖收檔
        if ($lock_handle !== false) {
            @flock($lock_handle, LOCK_UN);
            @fclose($lock_handle);
        }

        return $saved;
    }

    // 寫 meta.json（原子：先寫 .tmp 再 rename——讀者永不見半份；回 bool）
    private function _write_meta_file($draft_dir, $meta_data)
    {
        $meta_file     = $draft_dir . "/meta.json";
        $meta_tmp_file = $meta_file . ".tmp";
        $meta_json     = json_encode($meta_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $written       = @file_put_contents($meta_tmp_file, $meta_json);

        // 判定：寫入失敗 → 假
        if ($written === false) {
            return false;
        }
        $renamed = @rename($meta_tmp_file, $meta_file);

        // 判定：改名失敗 → 清 tmp 並回假
        if ($renamed === false) {
            @unlink($meta_tmp_file);

            return false;
        }


        return true;
    }

    // meta 模板（新草稿起點——空值一律 null＝未提交）
    private function _get_draft_meta_template($project_slug, $chat_sid)
    {
        $now_milliseconds = (int)floor(microtime(true) * 1000);

        $meta_template = [];
        $meta_template["prompt_id"]         = sc_uuid7();
        $meta_template["project_slug"]      = $project_slug;
        $meta_template["project_id"]        = 0;
        $meta_template["project_path"]      = null;
        $meta_template["chat_id"]           = 0;
        $meta_template["chat_sid"]          = $chat_sid;
        $meta_template["prompt_text"]       = null;
        $meta_template["chat_title"]        = null;
        $meta_template["provider_id"]       = null;
        $meta_template["model_id"]          = null;
        $meta_template["reasoning_effort"]  = null;
        $meta_template["chat_mode"]         = null;
        $meta_template["agent"]             = null;
        $meta_template["daemon_engine"]     = hkai()->agent()->get_daemon_engine();
        $meta_template["status"]            = "draft";
        $meta_template["is_new_chat"]       = 1;
        $meta_template["message_id"]        = 0;
        $meta_template["updated_by"]        = null;
        $meta_template["viewer_id"]         = "";
        $meta_template["attachments"]       = [];
        $meta_template["created_at"]        = $now_milliseconds;
        $meta_template["updated_at"]        = $now_milliseconds;

        return $meta_template;
    }

    // 草稿標題（prompt_text 優先；冇文字＝第一個附件檔名）
    private function _refresh_chat_title($meta_data)
    {
        $prompt_text = (string)($meta_data["prompt_text"] ?? "");

        // 判定：有文字 → 用文字
        if ($prompt_text !== "") {
            $meta_data["chat_title"] = sc_ai_chats()->resolve_chat_title($prompt_text);
            return $meta_data;
        }

        $attachments = (array)($meta_data["attachments"] ?? []);

        // 判定：冇文字但有附件 → 用第一個附件原檔名
        if (count($attachments) > 0) {
            $first_attachment = $attachments[0];
            $attachment_label = (string)($first_attachment["original_filename"] ?? "");

            // 判定：冇原檔名 → 用儲存檔名
            if ($attachment_label === "") {
                $attachment_label = (string)($first_attachment["filename"] ?? "");
            }

            $meta_data["chat_title"] = sc_ai_chats()->resolve_chat_title($attachment_label);
            return $meta_data;
        }

        $meta_data["chat_title"] = null;

        return $meta_data;
    }
}
