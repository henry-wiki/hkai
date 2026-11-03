<?php
// == hkai/index.php：路由入口（controller 動作——執行時 $this = controller instance） ==
// == 職責：取 route → 交 page builder 組裝整頁（唯一出口；資料載入全在 builder／前端 AJAX，此層零業務） ==

// TEMP TRACE（追溯 %SystemDrive%——查完移除）：頁面開始／結束各一筆
$sd_trace_write = function ($trace_tag) {
    $services_dir = sc_normalize_path(sc_get_config("services"));
    $trace_file   = dirname($services_dir) . "/_tmp/sd_trace.log";
    $trace_folder = $services_dir . "/%SystemDrive%";
    $system_drive = getenv("SYSTEMDRIVE");
    $folder_state = "no";

    // 判定：資料夾已存在 → 記 YES
    if (is_dir($trace_folder) === true) {
        $folder_state = "YES";
    }

    $trace_line = date("Y-m-d H:i:s") . " | " . $trace_tag
        . " | pid=" . getmypid()
        . " | cwd=" . getcwd()
        . " | SYSTEMDRIVE=" . ($system_drive === false ? "<missing>" : $system_drive)
        . " | folder=" . $folder_state . "
";

    @file_put_contents($trace_file, $trace_line, FILE_APPEND | LOCK_EX);
};

$sd_trace_write("php_page_begin:" . $this->get_request_path());
register_shutdown_function(function () use ($sd_trace_write) {
    $sd_trace_write("php_page_end");
});

// 頁面入口：確保本機伺服器已啟動（人手停旗標／已活／port 都交 ensure_server 判斷；失敗只寫 log）
hkai()->process()->server()->ensure_server();

// 頁面初始化：建 viewer 檔＋逐 target 快照（同一份 targets 寫入 viewer）
sc_stream()->snapshot()->init_snapshot();

// 執行語境 controller（bridging 收 route array——run() 內 require 語境使 $this = 執行語境 instance）
$route      = $this->get_route();
$controller = sc_controller($route);

// 整頁輸出（page builder——唯一出口）
return hkai()->web_page()->builder()->render_page_index($controller);
