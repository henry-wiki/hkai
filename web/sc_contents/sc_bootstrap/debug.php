<?php
// == sc_bootstrap/debug.php：選擇性 debug 收尾註冊（對應 Laravel $app->terminating()） ==
// == 由 sc_bootstrap/app.php require_once 載入（容器建立後——bridging 已載，可取 sc_kernel()） ==
// == 選擇性：保持本檔為空 = 關閉 debug 收尾；需要 debug 時在此註冊 terminating callback ==
// == 執行時機：每請求最後 kernel::terminate() 依序執行（response 送出後） ==

// route log 寫檔（debug 用——_log/route_logs.{Ymd}.log 追加——含 registered/matched/missed）
sc_kernel()->register_terminating(function ($request, $response) {
    sc_router()->write_route_logs();
});

// 範例（未來收尾——需要時解除註解）：
// session 自動寫回
// sc_kernel()->register_terminating(function ($request, $response) {
//     sc_session()->save();
// });
