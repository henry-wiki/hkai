<?php
// == sc_app/hkai/request/get_activity.php：POST 頁面活動讀取 handler（app 層 action） ==
// == 輸入：{activity_name, activity_scope}；回應：200 + JSON {ok:true, activity_bundle}（查無＝null；空 activity_name → 400） ==

$input_data = sc_request()->json();

$activity_name  = isset($input_data["activity_name"]) ? $input_data["activity_name"] : "";
$activity_scope = isset($input_data["activity_scope"]) ? $input_data["activity_scope"] : [];

// activity_name 空 → 400（無鍵可查）
if ($activity_name === "") {
    return sc_response()->json(["ok" => false, "error" => "empty activity_name"], 400);
}

$activity_bundle = hkai__sc_page_activity()->get_activity($activity_name, $activity_scope);

return sc_response()->json([
    "ok"              => true,
    "activity_bundle" => $activity_bundle,
]);
