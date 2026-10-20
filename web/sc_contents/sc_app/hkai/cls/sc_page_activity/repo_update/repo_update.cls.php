<?php

namespace sc\app\hkai\cls\sc_page_activity\repo_update;

// == repo_update_Class：sc_page_activity 資料層（寫入）（singleton——經 hkai__sc_page_activity_repo_update() 取得） ==
// == 職責：activity 表寫入操作（insert_activity——INSERT；update_activity——依活動鍵〔activity_name＋scope〕UPDATE） ==

class repo_update_Class
{
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
    {}
    
    // ==== activity ====
    
    // 新增活動列（$activity_datapairs 由記錄層組好——本層只執行寫入）
    public function insert_activity($activity_datapairs)
    {
        $db = sc_db()->connection("sqlite");
        
        list($insert_sql, $insert_bindings) = $db->insert_sql("activity", $activity_datapairs);
        $db->insert($insert_sql, $insert_bindings);
    }
    
    // 更新活動列（依活動鍵定位——activity_name＋scope〔project_id／chat_id 動態入 WHERE〕；$activity_datapairs 由記錄層組好）
    public function update_activity($activity_datapairs, $activity_name, $activity_scope = [])
    {
        $db = sc_db()->connection("sqlite");
        
        // 動態 WHERE（activity_name 必有；scope 帶邊欄先加邊欄）
        $where_sql      = "activity_name = ?";
        $where_bindings = [$activity_name];
        
        // scope 帶 project_id → 加條件
        if (isset($activity_scope["project_id"])) {
            $where_sql        .= " AND project_id = ?";
            $where_bindings[]  = $activity_scope["project_id"];
        }
        
        // scope 帶 chat_id → 加條件
        if (isset($activity_scope["chat_id"])) {
            $where_sql        .= " AND chat_id = ?";
            $where_bindings[]  = $activity_scope["chat_id"];
        }
        
        list($update_sql, $update_bindings) = $db->update_sql("activity", $activity_datapairs, $where_sql, $where_bindings);
        $db->statement($update_sql, $update_bindings);
    }
}
