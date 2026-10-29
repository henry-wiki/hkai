<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive;

// == sc_ai_chats archive\archive_Class（bridge 檔）：archive 域本體（singleton——bridging sc_ai_chats()->archive()） ==
// == 動作（cls/archive/ 各 trait——一檔一動作）：終態收尾（flush＋歸檔搬箱）／歸檔搬檔／時間回填／清理過期歸檔 ==
// == 路徑層：get_archived_poll_dir／get_archived_dir_by_slug——目錄名一律嚟自 poll()->get_poll_dir_name()（單一來源） ==

class archive_Class
{
    use action\delete_archive_files;
    use action\archive_msg_files;
    use action\backfill_message_timing;
    use action\backfill_response_timing;
    use action\finalize_poll_job;
    use action\sweep_old_archived_messages;

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

    /**
     * file 子域（獨立物件——cls/archive/file/_.bridge.cls.php）
     *
     * @return \sc\app\hkai\plugins\sc_ai_chats\archive\file\file_Class
     */
    public function file()
    {
        return file\file_Class::getInstance();
    }

    // 歸檔訊息目錄（archive/<project_slug>/<chat_sid>/<prompt_id>——chat_id 版；命名交 poll 域）
    public function get_archived_poll_dir($chat_id, $message_id)
    {
        $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);

        return $this->get_archived_dir_by_slug($project_slug, $message_id);
    }

    // 歸檔訊息目錄（slug 版——路徑組裝：命名交 poll()->get_poll_dir_name()）
    public function get_archived_dir_by_slug($project_slug, $message_id)
    {
        $message_dir_name = sc_ai_chats()->poll()->get_poll_dir_name($project_slug, $message_id);

        return sc_get_config("hkai.messages_archive_dir") . "/" . $message_dir_name;
    }
}
