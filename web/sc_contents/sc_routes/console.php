<?php
// == sc_routes/console.php：CLI 命令註冊表（對應 Laravel routes/console.php——Artisan::command） ==
// == 用途：集中宣告 CLI 命令名稱 → 命令檔路徑（取代手寫掃描 console/commands/） ==
// == 載入：由 sc_console_kernel::run() require（CLI 引導階段，HTTP 不載入） ==
// == 註冊：sc_console_command("命令名", "命令檔路徑") ==
// == 執行：php console/app.php status ==

sc_console_command("status", SC_DIR_CONSOLE . "/commands/status.php");
