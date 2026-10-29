<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue;

// == sc_ai_chats queue\queue_Class（bridge 檔）：queue 域本體（singleton——bridging sc_ai_chats()->queue()） ==
// == 動作（cls/queue/ 各 trait——一檔一動作）：get_queue_chat_dir／is_queue_idle／enqueue_prompt_from_draft／get_queue_items／remove_queue_item／delete_queue_chat／dequeue_prompt_message_to_poll／dispatch_polling_job／move_queue_item_to_polling ==
// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 後備載入） ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
//
// == 輸送帶（訊息由草稿到開跑——一條線；打包完成後箱內零改寫）：
// ==   prompt_send 工序 6（finalize_draft）
// ==     → finalize_draft()                      定稿：上帶前最後一次寫入（chat_id＋status=pending）
// ==   工序 7（enqueue_prompt_from_draft）
// ==     → enqueue_prompt_from_draft()          上帶：純 rename 入 queue/<slug>/<chat_sid>/<prompt_id>/
// ==   工序 8（dequeue_prompt_message_to_poll）
// ==     → dequeue_prompt_message_to_poll()     推帶唯一入口：①內部驗證 is_queue_idle()（對賬＋有冇任務）
// ==                                           ②前移一格：抽 seq 最細＝隊頭 → move_queue_item_to_polling()
// ==   工序 9（render_prompt_send_result）
// ==     → render_queue_list()                  氣泡＋整排隊列 UI
// ==   最後開關（dispatch_polling_job）
// ==     → move_queue_item_to_polling()        出帶：整個目錄搬去 polling/<slug>/<chat_sid>/<prompt_id>/（只蓋 message_id）
// ==     → notify_server_new_prompt()           交 daemon（有附件＝等整理完成才派；停用＝// 註釋嗰句呼叫）
class queue_Class
{
    use file\get_queue_chat_dir;
    use guard\is_queue_idle;
    use file\enqueue_prompt_from_draft;
    use file\get_queue_items;
    use file\remove_queue_item;
    use file\delete_queue_chat;
    use action\dequeue_prompt_message_to_poll;
    use action\dispatch_polling_job;
    use action\dispatch_polling_item;
    use action\dispatch_image_extract_item;
    use action\dispatch_agent_item;
    use action\notify_image_extract;
    use action\notify_prompt_agent;
    use action\resume_polling_jobs;
    use action\move_queue_item_to_polling;

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
