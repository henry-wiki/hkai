<?php
// == hkai/index.php：路由入口（controller 動作——執行時 $this = controller instance） ==
// == 職責：取 route → 交 page builder 組裝整頁（唯一出口；資料載入全在 builder／前端 AJAX，此層零業務） ==

// 執行語境 controller（bridging 收 route array——run() 內 require 語境使 $this = 執行語境 instance）
$route      = $this->get_route();
$controller = sc_controller($route);

// 整頁輸出（page builder——唯一出口）
return hkai__sc_page_builder_view()->render_page_index($controller);
