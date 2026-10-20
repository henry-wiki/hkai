<?php

namespace sc\app\hkai\cls\sc_page_activity;

// == sc_page_activity_Class：hkai app 層頁面活動動作（singleton——經 bridging hkai__sc_page_activity() 取得） ==
// == 職責：頁面活動動作——set_activity・get_activity（頁面記憶・JSON）／get_latest_activity_row（最近前往） ==
// == 讀寫 SQL 交資料層——repo_update（hkai__sc_page_activity_repo_update()）／repo_select（hkai__sc_page_activity_repo_select()） ==

class sc_page_activity_Class
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
    
    // 寫入頁面活動（鍵＝activity_name＋scope〔project_id／chat_id——0 照計〕；scope 收 id 直接值或語境字串〔chat_sid／project_slug——自動解析〕——既有列更新、無則新增；bundle 以 JSON 存入 activity_content）
    public function set_activity($activity_name, $activity_bundle, $activity_scope = [])
    {
        $activity_content = json_encode($activity_bundle);
        
        // scope 取值（id 直接值先落底——未帶項目給 0）
        $project_id = isset($activity_scope["project_id"]) ? (int)$activity_scope["project_id"] : 0;
        $chat_id    = isset($activity_scope["chat_id"]) ? (int)$activity_scope["chat_id"] : 0;
        
        // 判定：chat_id 仍未有（未帶直接值）→ 藉 chat_sid 揾 chat row（一次拎齊 project_id／chat_id——short way）
        if ($chat_id === 0) {
            $chat_sid = isset($activity_scope["chat_sid"]) ? $activity_scope["chat_sid"] : "";
            $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
            
            // 判定：揾到 chat row → 以該對話嘅正式歸屬覆寫
            if ($chat_row) {
                $project_id = (int)$chat_row["project_id"];
                $chat_id    = (int)$chat_row["chat_id"];
            }
        }
        
        // 判定：project_id 仍未有（chat row 揾唔到）→ 藉 project_slug 查表解（get_project_row——空值／唔中回 null）
        if ($project_id === 0) {
            $project_slug = isset($activity_scope["project_slug"]) ? $activity_scope["project_slug"] : "";
            $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);
            
            // 判定：解析到 project row → 寫入 project_id
            if ($project_row) {
                $project_id = (int)$project_row["project_id"];
            }
        }
        
        // 活動列鍵 scope（project_id／chat_id——0 照計；查找＋更新同一鍵）
        $activity_row_scope = ["project_id" => $project_id, "chat_id" => $chat_id];
        $activity_row       = repo_select()->get_activity_row($activity_name, $activity_row_scope);
        
        // 既有列 → 更新（鍵＝activity_name＋scope〔project_id／chat_id〕——同鍵只維持一列）
        if ($activity_row !== null) {
            $activity_datapairs = [];
            
            $activity_datapairs["project_id"]       = $project_id;
            $activity_datapairs["chat_id"]          = $chat_id;
            $activity_datapairs["activity_content"] = $activity_content;
            $activity_datapairs["activity_at"]      = date("Y-m-d H:i:s");
            
            repo_update()->update_activity($activity_datapairs, $activity_name, $activity_row_scope);
            
            return;
        }
        
        // 無列 → 新增（activity_at 記寫入時間）
        $activity_datapairs = [];
        
        $activity_datapairs["project_id"]       = $project_id;
        $activity_datapairs["chat_id"]          = $chat_id;
        $activity_datapairs["activity_name"]    = $activity_name;
        $activity_datapairs["activity_content"] = $activity_content;
        $activity_datapairs["activity_at"]      = date("Y-m-d H:i:s");
        
        repo_update()->insert_activity($activity_datapairs);
    }
    
    // 讀取頁面活動（activity_name 精確查＋scope 對頁篩選（project_id／chat_id 動態入 WHERE）——查無回 null；有 → JSON 解回 bundle）
    public function get_activity($activity_name, $activity_scope = [])
    {
        $activity_row = repo_select()->get_activity_row($activity_name, $activity_scope);
        
        // 查無 → null
        if ($activity_row === null) {
            return null;
        }
        
        $activity_content = $activity_row["activity_content"];
        
        return json_decode($activity_content, true);
    }
    
    // 讀取最近一筆前往列（純前往列——頁面記憶列唔算；無記錄回 null）
    public function get_latest_activity_row()
    {
        return repo_select()->get_latest_activity_row();
    }
}
