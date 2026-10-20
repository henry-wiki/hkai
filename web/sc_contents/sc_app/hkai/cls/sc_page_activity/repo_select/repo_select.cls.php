<?php

namespace sc\app\hkai\cls\sc_page_activity\repo_select;

// == repo_select_Class：sc_page_activity 資料層（讀取）（singleton——經 hkai__sc_page_activity_repo_select() 取得） ==
// == 職責：activity 表查詢（get_activity_row——指定鍵單列；get_latest_activity_row——最近一筆前往列） ==

class repo_select_Class
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
    
    // 讀取單一活動列（activity_name 精確查＋scope 動態條件——查無回 null）
    public function get_activity_row($activity_name, $activity_scope = [])
    {
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
        
        $query = "SELECT * FROM activity WHERE " . $where_sql . " LIMIT 1";
        
        return sc_db()->selectOne($query, $where_bindings);
    }
    
    // 讀取最近一筆前往列（純前往列 activity_name 空——頁面記憶列唔算；無記錄回 null）
    public function get_latest_activity_row()
    {
        $query = "SELECT * FROM activity WHERE activity_name = '' ORDER BY activity_id DESC LIMIT 1";
        
        return sc_db()->selectOne($query);
    }
}
