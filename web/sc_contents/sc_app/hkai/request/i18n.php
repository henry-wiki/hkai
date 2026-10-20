<?php
// == sc_app/hkai/request/i18n.php：GET i18n 字典 handler（app 層 action——供前端 JS 取字典） ==
// == 回應：200 + JSON = 目前語系碼 + 字典（flat dot-key——與 PHP 共用同一份 JSON 檔） ==

return sc_response()->json([
    "lang_code" => hkai__i18n()->get_lang_code(),
    "lang_data" => hkai__i18n()->get_lang_data(),
]);
