<?php
// == sc_config/assets/app.php：應用常數（對應 Laravel config/app.php——return array） ==
// == 讀取：sc_get_config("app.env_file") ==

// 環境檔（repo root 嘅 .env——相對推導；本檔＝web/sc_config/assets）
$env_file = str_replace("\\", "/", dirname(__DIR__, 3)) . "/.env";

return array(
    "env_file" => $env_file,
);
