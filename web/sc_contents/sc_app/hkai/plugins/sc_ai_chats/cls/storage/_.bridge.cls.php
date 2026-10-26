<?php

namespace sc\app\hkai\plugins\sc_ai_chats\storage;

// == sc_ai_chats storage\storage_Class：storage 域本體（bridge 檔——singleton；呼叫形 sc_ai_chats()->storage()） ==
// == 只負責 storage 目錄（注入／取得）；資料存取唔喺此（chats 讀寫歸 chat 域、messages 讀寫歸 message 域） ==
// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 後備載入） ==

class storage_Class
{
    private static $instance = null;
    
    private $storageDir = null;
    
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {}
    
    // 注入 storage 目錄（由 plugin __init__.php 以 controller->getStorageDir() 取得後設定）
    public function set_storage_dir($storageDir)
    {
        $this->storageDir = rtrim($storageDir, "/");
    }
    
    // 取得 storage 目錄（未注入時回 null）
    public function get_storage_dir()
    {
        return $this->storageDir;
    }

    // 取得 messages 目錄（live 訊息桶——sc_config 絕對路徑；高頻使用——單一路徑來源）
    public function get_messages_dir()
    {
        return sc_get_config("hkai.messages_polling_dir");
    }

}
