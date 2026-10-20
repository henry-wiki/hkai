<?php
// == sc_routes/channels.php：廣播頻道授權註冊表（對應 Laravel routes/channels.php——Broadcast::channel） ==
// == 用途：即時推送頻道的訂閱授權——「誰可以聽哪個頻道」（SSE / WebSocket / 即時通知） ==
// == 對應專案現況：目前即時推送走 SSE（sc_api events 的 message_update / status_change） ==
// ==               暫無頻道授權機制——本檔為未來擴展預留，機制實作前請勿取消註解 ==
// == 載入：由 bootstrapper load_routes require（HTTP 引導階段，與 api/web 同批） ==
// == 未來用法（機制實作後）：sc_channel("projects.{project_id}", function ($projectId) { return true; }); ==
