<?php

// == sc_sd_trace：臨時追溯（%SystemDrive% 資料夾來源——查完即拆，非業務功能）
// == 上游：request 入口／health_stream 監視；下游：寫 _tmp/sd_trace.log（append＋LOCK）
// == 記錄內容：時間／tag／pid／cwd／SYSTEMDRIVE 有無／services 下 %SystemDrive% 是否存在

function sc_sd_trace($trace_tag)
{
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
        . " | folder=" . $folder_state . "\n";

    @file_put_contents($trace_file, $trace_line, FILE_APPEND | LOCK_EX);
}
