<?php

// == sc_app/dbviewer/__init__.php：dbviewer app 設定（載入語境時執行一次——由 controller load_app_context 呼叫） ==
// == 職責：寫死本 app 讀取的 sqlite 庫檔路徑，注入 core class（結構性設定集中於入口——class 零寫死） ==

// 注入設定（檢視目標＝寫死的 hkai 本地庫絕對路徑——唯一讀取來源）
dbviewer__core()->initial_configs([
    "sqlite_file" => sc_get_config("storage.database_file"),
]);
