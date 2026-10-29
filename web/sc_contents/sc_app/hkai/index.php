<?php
// == hkai/index.php：路由入口（controller 動作——執行時 $this = controller instance） ==
// == 職責：取 route → 交 page builder 組裝整頁（唯一出口；資料載入全在 builder／前端 AJAX，此層零業務） ==

// 頁面初始化：建立本頁 viewer 參照檔（data/stream/viewers/{uuidv7}.json；初值空——由 health_stream 開線時對齊）
$viewer_id = sc_ai_health()->stream()->create_viewer_snapshot();

// 執行語境 controller（bridging 收 route array——run() 內 require 語境使 $this = 執行語境 instance）
$route      = $this->get_route();
$controller = sc_controller($route);

// 整頁輸出（page builder——唯一出口）
return hkai()->web_page()->builder()->render_page_index($controller, $viewer_id);
