// jsmods/setup/request_headers.js —— setup 請求頭（一支共用——所有 setup 請求共用同一份頭）
// 職責：setup_request_headers()——回請求頭（內容類型＋瀏覽器時區；每個請求隨帶返後端，採用與否＝後端鏈決定）

export function setup_request_headers() {
    const browser_timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    let timezone_header_value = browser_timezone;

    // 判定：攞唔到瀏覽器時區（undefined）→ 帶空（後端鏈自然跳過）
    if (timezone_header_value === undefined) {
        timezone_header_value = "";
    }

    return {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Browser-Timezone": timezone_header_value
    };
}
