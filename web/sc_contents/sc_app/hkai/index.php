<?php
// == hkai/index.php：路由入口（controller 動作——執行時 $this = controller instance） ==
// == 職責：取 route → 交 page builder 組裝整頁（唯一出口；資料載入全在 builder／前端 AJAX，此層零業務） ==

// 頁面入口：確保本機伺服器已啟動（人手停旗標／已活／port 都交 ensure_server 判斷；失敗只寫 log）
hkai()->process()->server()->ensure_server();

// 頁面初始化：建 viewer 檔＋逐 target 快照（同一份 targets 寫入 viewer）
sc_stream()->snapshot()->init_snapshot();

// 執行語境 controller（bridging 收 route array——run() 內 require 語境使 $this = 執行語境 instance）
$route      = $this->get_route();
$controller = sc_controller($route);

// 整頁輸出（page builder——唯一出口）
return hkai()->web_page()->builder()->render_page_index($controller);
