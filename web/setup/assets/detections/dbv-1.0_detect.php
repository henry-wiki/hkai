<?php

// == detections/dbv-1.0_detect.php：dbv-1.0（0.1.0）搬遷偵測表
// == 用途：搬遷模式只需知舊庫位置與版本；其他資料一律不搬
// == 規則：每個 DB 版本一個偵測檔（dbv-1.2_detect.php…）；版本冇對應檔＝用最低版本

return [
    // 舊版資料庫（相對舊安裝根目錄）
    "database_file" => "data/localdb/hkai_chat_data.sqlite",
];
