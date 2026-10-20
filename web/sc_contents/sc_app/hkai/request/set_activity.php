<?php
// == sc_app/hkai/request/set_activity.php：POST 頁面活動寫入 handler（app 層 action） ==
// == 輸入：{activity_name, activity_bundle, activity_scope}；回應：200 + JSON {ok:true}（空 activity_name → 400） ==

$input_data = sc_request()->json();

$activity_name   = isset($input_data["activity_name"]) ? $input_data["activity_name"] : "";
$activity_bundle = isset($input_data["activity_bundle"]) ? $input_data["activity_bundle"] : [];
$activity_scope  = isset($input_data["activity_scope"]) ? $input_data["activity_scope"] : [];

// activity_name 空 → 400（無鍵可寫）
if ($activity_name === "") {
    return sc_response()->json(["ok" => false, "error" => "empty activity_name"], 400);
}

hkai__sc_page_activity()->set_activity($activity_name, $activity_bundle, $activity_scope);

return sc_response()->json(["ok" => true]);
