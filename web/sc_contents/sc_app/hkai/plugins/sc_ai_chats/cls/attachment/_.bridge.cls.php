<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment;

// == sc_ai_chats attachment\attachment_Class：attachment 域本體（bridge 檔——singleton；呼叫形 sc_ai_chats()->attachment()） ==
// == 職責：圖片附件（上傳落地／綁定訊息／刪除）——原圖以 base64 交模型（唔做文字抽取） ==
// == 2026-10-22 建（附件功能——圖片） ==

class attachment_Class
{
    use action\store_uploaded_image;
    use action\get_rows_by_ids;
    use action\bind_to_message;
    use action\delete_by_message;
    use action\delete_by_project;
    use action\save_extract_result;
    use action\import_draft_file;

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
}
