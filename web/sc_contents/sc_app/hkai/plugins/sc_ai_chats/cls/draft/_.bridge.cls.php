<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft;

// == sc_ai_chats draft\draft_Class：draft 域本體（bridge 檔——singleton；bridging sc_ai_chats()->draft()） ==
// == 職責：草稿目錄（一對話一目錄——draft/<project_slug>/<chat_sid>/） ==
// == 目錄 key＝chat_sid（唔用 uuid——上傳時兩邊都知用邊個目錄）；uuid 只留喺 meta 內部做追蹤 ==
// == 上游：request/draft/*（三個動作）；下游：sc_config 路徑＋檔案系統（本階段未涉 DB） ==

class draft_Class
{
    use meta\get_draft_meta;
    use meta\set_draft_meta;
    use meta\refresh_draft_meta_for_attachments;
    use action\draft_create;
    use action\fetch;
    use action\discard;
    use action\save_meta;
    use action\finalize_draft;
    use guard\get_prompt_required_fields;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {
    }

    // 單一草稿目錄（<根>/<project_slug>/<chat_sid>——根由 sc_config 取，唔檢查存在）
    public function get_draft_item_dir($project_slug, $chat_sid)
    {
        $draft_dir  = sc_get_config("hkai.messages_draft_dir") . "/" . $project_slug;
        $draft_dir .= "/" . $chat_sid;

        return $draft_dir;
    }






    // 列出草稿附件絕對路徑（送出匯入用——排序＝檔名）
    public function list_draft_files($project_slug, $chat_sid)
    {
        $draft_files     = [];
        $attachments_dir = $this->get_draft_item_dir($project_slug, $chat_sid) . "/attachments";

        // 判定：目錄唔存在 → 空
        if (!is_dir($attachments_dir)) {
            return $draft_files;
        }

        $dir_entries = scandir($attachments_dir);

        // 判定：掃唔到 → 空
        if ($dir_entries === false) {
            return $draft_files;
        }

        // 逐項收集（跳過 . 與 ..）
        foreach ($dir_entries as $dir_entry) {
            // 判定：跳過目錄項
            if ($dir_entry === "." || $dir_entry === "..") {
                continue;
            }

            $file_path = $attachments_dir . "/" . $dir_entry;

            // 判定：唔係普通檔 → 跳過
            if (!is_file($file_path)) {
                continue;
            }

            // 判定：縮圖（.thumb.jpg）→ 跳過（只匯入主檔）
            if (substr($dir_entry, -10) === ".thumb.jpg") {
                continue;
            }

            $draft_file = [];
            $draft_file["path"]     = $file_path;
            $draft_file["filename"] = $dir_entry;

            $draft_files[] = $draft_file;
        }

        return $draft_files;
    }


    // 讀縮圖成 data URL（回空字串＝冇縮圖）
    public function read_thumb_data_url($project_slug, $chat_sid, $thumb_filename)
    {
        // 判定：冇縮圖檔名 → 空
        if ($thumb_filename === "") {
            return "";
        }

        $thumb_path = $this->get_draft_item_dir($project_slug, $chat_sid) . "/attachments/" . $thumb_filename;

        // 判定：縮圖檔唔存在 → 空
        if (!is_file($thumb_path)) {
            return "";
        }

        $thumb_binary = @file_get_contents($thumb_path);

        // 判定：讀唔到 → 空
        if ($thumb_binary === false) {
            return "";
        }

        return "data:image/jpeg;base64," . base64_encode($thumb_binary);
    }
}
