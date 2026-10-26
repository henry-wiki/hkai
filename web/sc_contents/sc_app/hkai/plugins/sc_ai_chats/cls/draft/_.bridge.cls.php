<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft;

// == sc_ai_chats draft\draft_Class：draft 域本體（bridge 檔——singleton；bridging sc_ai_chats()->draft()） ==
// == 職責：草稿目錄（一對話一目錄——draft/<project_slug>/<chat_sid>/；新訊息未有 chat_sid → "new"） ==
// == 目錄 key＝chat_sid（唔用 uuid——上傳時兩邊都知用邊個目錄）；uuid 只留喺 meta 內部做追蹤 ==
// == 上游：request/draft/*（三個動作）；下游：sc_config 路徑＋檔案系統（本階段未涉 DB） ==

class draft_Class
{
    use action\create;
    use action\fetch;
    use action\discard;
    use action\upload;
    use action\delete_file;
    use action\save_meta;

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

    // 草稿根目錄（sc_config 絕對路徑——路徑單一來源）
    public function get_draft_dir()
    {
        return sc_get_config("hkai.messages_draft_dir");
    }

    // 單一草稿目錄（<根>/<project_slug>/<draft_key>——純組裝，唔檢查存在）
    public function get_draft_item_dir($project_slug, $draft_key)
    {
        $draft_dir  = $this->get_draft_dir() . "/" . $project_slug;
        $draft_dir .= "/" . $draft_key;

        return $draft_dir;
    }

    // 由 chat_sid 取目錄 key（空＝新訊息 → "new"）
    public function get_draft_key($chat_sid)
    {
        $draft_key = trim((string)$chat_sid);

        // 判定：未有 chat_sid（新訊息）→ 固定 "new"
        if ($draft_key === "") {
            return "new";
        }

        return $draft_key;
    }

    // 判定目錄 key 格式（"new" 或英數底線減號——防路徑穿越）
    public function is_valid_draft_key($draft_key)
    {
        // 判定：新訊息固定 key → 合法
        if ($draft_key === "new") {
            return true;
        }

        // 判定：格式唔合 → 假
        if (preg_match("/^[A-Za-z0-9_-]{1,64}$/", $draft_key) !== 1) {
            return false;
        }

        return true;
    }

    // 讀 meta.json（冇／壞 → 空陣列）
    public function read_meta($draft_dir)
    {
        $meta_file = $draft_dir . "/meta.json";

        // 判定：meta 檔唔存在 → 空
        if (!is_file($meta_file)) {
            return [];
        }

        $meta_data = json_decode((string)@file_get_contents($meta_file), true);

        // 判定：解唔到／唔係物件 → 空
        if (!is_array($meta_data)) {
            return [];
        }

        return $meta_data;
    }

    // 寫 meta.json（原子：先寫 .tmp 再 rename——讀者永不見半份）
    public function write_meta($draft_dir, $meta_data)
    {
        $meta_file     = $draft_dir . "/meta.json";
        $meta_tmp_file = $meta_file . ".tmp";

        $meta_json = json_encode($meta_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $written   = @file_put_contents($meta_tmp_file, $meta_json);

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

    // 列出草稿附件絕對路徑（送出匯入用——排序＝檔名）
    public function list_draft_files($project_slug, $draft_key)
    {
        $draft_files     = [];
        $attachments_dir = $this->get_draft_item_dir($project_slug, $draft_key) . "/attachments";

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

    // 標記草稿狀態（submitted／discarded——meta 存在才寫）
    public function mark_status($project_slug, $draft_key, $status)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data = $this->read_meta($draft_dir);

        // 判定：冇 meta → 唔動作
        if (count($meta_data) < 1) {
            return false;
        }

        $meta_data["status"]     = $status;
        $meta_data["updated_at"] = (int)floor(microtime(true) * 1000);

        return $this->write_meta($draft_dir, $meta_data);
    }

    // 確保 meta 存在（未建立過草稿 → 寫最小 meta；已存在＝唔動）
    public function ensure_meta($project_slug, $draft_key, $chat_sid)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data = $this->read_meta($draft_dir);

        // 判定：已有 meta → 直接回
        if (count($meta_data) > 0) {
            return $meta_data;
        }

        $now_milliseconds = (int)floor(microtime(true) * 1000);

        $meta_data = [];
        $meta_data["draft_id"]          = sc_uuid7();
        $meta_data["draft_key"]         = $draft_key;
        $meta_data["project_slug"]      = $project_slug;
        $meta_data["chat_sid"]          = $chat_sid;
        $meta_data["prompt_text"]       = "";
        $meta_data["provider_id"]       = "";
        $meta_data["model_id"]          = "";
        $meta_data["reasoning_effort"]  = "";
        $meta_data["chat_mode"]         = "";
        $meta_data["agent"]             = "";
        $meta_data["status"]            = "active";
        $meta_data["is_new_chat"]       = 1;
        $meta_data["updated_by"]        = "";
        $meta_data["attachments"]       = [];
        $meta_data["created_at"]        = $now_milliseconds;
        $meta_data["updated_at"]        = $now_milliseconds;

        // 目錄未存在（舊對話第一次有草稿）→ 先建立
        sc_mkdir($draft_dir);

        $this->write_meta($draft_dir, $meta_data);

        return $meta_data;
    }

    // 更新 meta（patch 合併＋updated_at；meta 未存在＝先建立）
    public function update_meta($project_slug, $draft_key, array $patch)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data = $this->read_meta($draft_dir);

        // 判定：meta 未存在 → 先建立（chat_sid 由 patch 帶）
        if (count($meta_data) < 1) {
            $chat_sid  = (string)($patch["chat_sid"] ?? "");
            $meta_data = $this->ensure_meta($project_slug, $draft_key, $chat_sid);
        }

        // 逐欄合併（呼叫端只傳要改嘅欄）
        foreach ($patch as $patch_key => $patch_value) {
            $meta_data[$patch_key] = $patch_value;
        }

        $meta_data["updated_at"] = (int)floor(microtime(true) * 1000);

        return $this->write_meta($draft_dir, $meta_data);
    }

    // 附件加入 meta（filename＝伺服器檔名；original_filename＝顯示名；thumb＝縮圖檔名）
    public function add_attachment_meta($project_slug, $draft_key, $filename, $original_filename, $thumb_filename)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data = $this->read_meta($draft_dir);
        $attachment_list = [];

        // 判定：meta 有 attachments → 取舊清單
        if (isset($meta_data["attachments"]) && is_array($meta_data["attachments"])) {
            $attachment_list = $meta_data["attachments"];
        }

        $attachment_item = [];
        $attachment_item["filename"]          = $filename;
        $attachment_item["original_filename"] = $original_filename;
        $attachment_item["thumb"]             = $thumb_filename;

        $attachment_list[] = $attachment_item;

        return $this->update_meta($project_slug, $draft_key, ["attachments" => $attachment_list, "status" => "active"]);
    }

    // 附件移出 meta（filename 比對——刪檔後同步）
    public function remove_attachment_meta($project_slug, $draft_key, $filename)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $draft_key);
        $meta_data = $this->read_meta($draft_dir);
        $kept_list = [];

        // 判定：冇清單 → 唔動作
        if (!isset($meta_data["attachments"]) || !is_array($meta_data["attachments"])) {
            return false;
        }

        // 逐項保留（filename 唔同＝留下）
        foreach ($meta_data["attachments"] as $attachment_item) {
            // 判定：要移除嗰項 → 跳過
            if (($attachment_item["filename"] ?? "") === $filename) {
                continue;
            }

            $kept_list[] = $attachment_item;
        }

        return $this->update_meta($project_slug, $draft_key, ["attachments" => $kept_list]);
    }

    // 寫縮圖（長邊 ≤240px JPEG；成功＝true）
    public function write_thumbnail($binary, $thumb_path)
    {
        $image_resource = @imagecreatefromstring($binary);

        // 判定：解唔到圖 → 假
        if ($image_resource === false) {
            return false;
        }

        $source_width  = imagesx($image_resource);
        $source_height = imagesy($image_resource);
        $max_side_px   = 240;
        $scale_ratio   = 1.0;
        $longest_side  = max($source_width, $source_height);

        // 判定：長邊超過上限 → 計縮放比
        if ($longest_side > $max_side_px) {
            $scale_ratio = $max_side_px / $longest_side;
        }

        $thumb_width  = max(1, (int)round($source_width * $scale_ratio));
        $thumb_height = max(1, (int)round($source_height * $scale_ratio));

        $thumb_resource = imagecreatetruecolor($thumb_width, $thumb_height);

        // 白底（PNG 透明 → JPEG）
        $white_color = imagecolorallocate($thumb_resource, 255, 255, 255);
        imagefilledrectangle($thumb_resource, 0, 0, $thumb_width, $thumb_height, $white_color);
        imagecopyresampled($thumb_resource, $image_resource, 0, 0, 0, 0, $thumb_width, $thumb_height, $source_width, $source_height);

        $thumb_saved = @imagejpeg($thumb_resource, $thumb_path, 80);

        imagedestroy($thumb_resource);
        imagedestroy($image_resource);

        // 判定：存唔到 → 假
        if ($thumb_saved === false) {
            return false;
        }

        return true;
    }

    // 最終存檔（驗大小／真假格式 → 轉 PNG／JPEG → 寫檔＋縮圖＋meta；回資料 array）
    public function store_final_image($project_slug, $draft_key, $original_filename, $binary)
    {
        $max_upload_size_kb = (int)sc_get_config("hkai.draft.max_upload_size");
        $max_file_size_byte = $max_upload_size_kb * 1024;

        // 判定：超過大小上限 → 空
        if (strlen($binary) > $max_file_size_byte) {
            return [];
        }

        $detected_mime_type = sc_ai_chats()->attachment()->detect_image_mime_type($binary);

        // 判定：唔係圖片 → 空
        if ($detected_mime_type === "") {
            return [];
        }

        $stored_mime_type = $detected_mime_type;
        $stored_binary    = $binary;

        // 判定：非 provider 格式 → 轉 PNG／JPEG
        if (!in_array($detected_mime_type, ["image/png", "image/jpeg"])) {
            $converted_image = sc_ai_chats()->attachment()->convert_image_binary_to_provider_format($binary, $detected_mime_type);

            // 判定：轉碼失敗 → 空
            if (count($converted_image) < 1) {
                return [];
            }

            $stored_mime_type = (string)$converted_image["mime_type"];
            $stored_binary    = (string)$converted_image["binary"];
        }

        $extension_map = [];
        $extension_map["image/png"]  = "png";
        $extension_map["image/jpeg"] = "jpg";

        $file_extension   = $extension_map[$stored_mime_type];
        $now_milliseconds = (int)floor(microtime(true) * 1000);
        $random_suffix    = bin2hex(random_bytes(3));
        $stored_filename  = $now_milliseconds . "-" . $random_suffix . "." . $file_extension;
        $thumb_filename   = $now_milliseconds . "-" . $random_suffix . ".thumb.jpg";

        $attachments_dir = $this->get_draft_item_dir($project_slug, $draft_key) . "/attachments";
        sc_mkdir($attachments_dir);

        $absolute_filepath = $attachments_dir . "/" . $stored_filename;
        $thumb_filepath    = $attachments_dir . "/" . $thumb_filename;

        $write_result = @file_put_contents($absolute_filepath, $stored_binary);

        // 判定：寫入失敗 → 空
        if ($write_result === false) {
            return [];
        }

        $thumb_written = $this->write_thumbnail($stored_binary, $thumb_filepath);

        // 判定：縮圖失敗 → thumb 欄留空（前端用 icon）
        if ($thumb_written === false) {
            $thumb_filename = "";
        }

        $this->ensure_meta($project_slug, $draft_key, "");
        $this->add_attachment_meta($project_slug, $draft_key, $stored_filename, $original_filename, $thumb_filename);

        $image_width  = 0;
        $image_height = 0;
        $image_size_info = @getimagesizefromstring($stored_binary);

        // 判定：取到尺寸 → 記錄
        if ($image_size_info !== false) {
            $image_width  = (int)$image_size_info[0];
            $image_height = (int)$image_size_info[1];
        }

        $stored_info = [];
        $stored_info["filename"]          = $stored_filename;
        $stored_info["original_filename"] = $original_filename;
        $stored_info["thumb"]             = $thumb_filename;
        $stored_info["mime_type"]         = $stored_mime_type;
        $stored_info["file_size"]         = strlen($stored_binary);
        $stored_info["image_width"]       = $image_width;
        $stored_info["image_height"]      = $image_height;

        return $stored_info;
    }

    // 讀縮圖成 data URL（回空字串＝冇縮圖）
    public function read_thumb_data_url($project_slug, $draft_key, $thumb_filename)
    {
        // 判定：冇縮圖檔名 → 空
        if ($thumb_filename === "") {
            return "";
        }

        $thumb_path = $this->get_draft_item_dir($project_slug, $draft_key) . "/attachments/" . $thumb_filename;

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

    // 清空草稿附件（送出匯入後——meta 清單歸零＋刪殘留縮圖；主檔已搬走）
    public function clear_draft_attachments($project_slug, $draft_key)
    {
        $attachments_dir = $this->get_draft_item_dir($project_slug, $draft_key) . "/attachments";

        // 判定：目錄存在 → 掃縮圖殘檔
        if (is_dir($attachments_dir)) {
            $dir_entries = scandir($attachments_dir);

            // 判定：掃到 → 逐項刪 .thumb.jpg
            if ($dir_entries !== false) {
                foreach ($dir_entries as $dir_entry) {
                    // 判定：唔係縮圖 → 跳過
                    if (substr($dir_entry, -10) !== ".thumb.jpg") {
                        continue;
                    }

                    @unlink($attachments_dir . "/" . $dir_entry);
                }
            }
        }

        return $this->update_meta($project_slug, $draft_key, ["attachments" => []]);
    }

    // 新對話落地後認領草稿（new → 真 chat_sid——守住「一對話一目錄」）
    public function claim_new_draft($project_slug, $chat_sid)
    {
        $new_dir    = $this->get_draft_item_dir($project_slug, "new");
        $target_dir = $this->get_draft_item_dir($project_slug, $chat_sid);

        // 判定：冇 new 草稿 → 唔動作
        if (!is_dir($new_dir)) {
            return false;
        }

        // 判定：目標目錄已存在 → 唔覆蓋（留待 sweep／人手處理）
        if (is_dir($target_dir)) {
            return false;
        }

        $renamed = @rename($new_dir, $target_dir);

        // 判定：搬唔到 → 假
        if ($renamed === false) {
            return false;
        }

        $meta_data = $this->read_meta($target_dir);
        $meta_data["draft_key"]  = $chat_sid;
        $meta_data["chat_sid"]   = $chat_sid;
        $meta_data["updated_at"] = (int)floor(microtime(true) * 1000);

        $this->write_meta($target_dir, $meta_data);

        return true;
    }
}
