<?PHP
namespace sc\framework\http\response;

// =========================================================================================================================
// == sc_response：統一回應層（full follow Laravel Response——Symfony Response 基底 + Illuminate 擴充） ==
// == 兩套 API 並存： ==
// ==   ① 舊式直接輸出：ok()/fail()（plugins 現役——output_json 直接輸出——ob 捕捉相容） ==
// ==   ② 新式 Response 物件：status/header/body/json/redirect/download/cookie/cache + send()（統一輸出） ==
// == 未來 controller 遷移方向：return sc_response()->json($data)（取代 ok/fail 的輸出） ==
// =========================================================================================================================

class sc_response_Class
{
	
	private static $instance = null;
	
	// Response 物件（對應 Laravel Response）：status/headers/body——send() 統一輸出
	private $statusCode = null;      // null = 不覆蓋（controller 已設定的 http_response_code 保留）
	private $headers = array();
	private $bodyContent = "";
	
	public static function getInstance()	
	{
        if (!@isset(self::$instance)) {	$class = __CLASS__;self::$instance = new $class();	}
		return self::$instance;
	}
	
	public function __construct()	{	}
	
	// === 設定狀態碼 ===
	// 對應 Laravel：setStatusCode（Symfony Response 基底）
	// 作用：指定回應狀態碼（如 404/500）——設定了才覆蓋 controller 已設定的 http_response_code
	// 用法：sc_response()->status(404)
	public function status($statusCode)
	{
		$this->statusCode = $statusCode;
		
		return $this;
	}
	
	// === 設定 header ===
	// 對應 Laravel：header()（Symfony setHeader）
	// 作用：加入一個回應 header（Content-Type/Location/Set-Cookie 等）
	// 用法：sc_response()->header("Content-Type", "application/json")
	public function header($name, $value)
	{
		$this->headers[$name] = $value;
		
		return $this;
	}
	
	// === 設定 body ===
	// 對應 Laravel：setContent（Symfony 基底）
	// 作用：指定回應內容（HTML/JSON 字串）——kernel handle 的 ob 捕捉結果由此填入
	// 用法：sc_response()->body($html)
	public function body($content)
	{
		$this->bodyContent = $content;
		
		return $this;
	}
	
	// === 取得狀態碼 ===
	// 對應 Laravel：getStatusCode（Symfony 基底）
	// 作用：讀回目前狀態碼（未設定時視為 200）——測試/判斷用
	// 用法：$response->getStatusCode()
	public function getStatusCode()
	{
		return $this->statusCode !== null ? $this->statusCode : 200;
	}
	
	// === 取得 body 內容 ===
	// 對應 Laravel：getContent（Symfony 基底）
	// 作用：讀回回應內容（body）——測試檢查用
	// 用法：$response->getContent()
	public function getContent()
	{
		return $this->bodyContent;
	}
	
	// === 統一輸出 ===
	// 對應 Laravel：Response::send()（Symfony 基底）
	// 作用：把 status/headers/body 送到瀏覽器（含 FPM 提前釋放——效能）
	// 流程：① status（設定了才覆蓋）→ ② headers 逐一輸出 → ③ body → ④ fastcgi_finish_request → ⑤ return $this
	public function send()
	{
		// status：Response 層設定了才覆蓋（否則保留 controller 已設定的——如 404/405）
		if ($this->statusCode !== null) {
			http_response_code($this->statusCode);
		}
		
		// headers 逐一輸出
		foreach ($this->headers as $headerName => $headerValue) {
			header($headerName . ": " . $headerValue);
		}
		
		// body 輸出
		echo $this->bodyContent;
		
		// PHP-FPM：回應已送出後立即結束請求（connection 釋放——terminate 階段不再佔 FPM worker）
		if (function_exists("fastcgi_finish_request")) {
			fastcgi_finish_request();
		}
		
		// 回傳自身（鏈式相容——對應 Symfony Response::send 回傳 $this）
		return $this;
	}
	
	// === JSON 回應 ===
	// 對應 Laravel：response()->json($data, $status)
	// 作用：回傳 JSON 回應（自動設 Content-Type: application/json + json_encode body + 可選 status）
	// 用法：return sc_response()->json($data);（API controller 標準回傳——未來取代 ok/fail）
	public function json($data, $status = null)
	{
		$this->header("Content-Type", "application/json");
		$this->body(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
		
		// 可選狀態碼（如 201/422）
		if ($status !== null) {
			$this->status($status);
		}
		
		return $this;
	}
	
	// === 重導向 ===
	// 對應 Laravel：redirect($url, $status)
	// 作用：Location header 轉跳（預設 302 暫時轉跳）——登入後跳回/表單成功後跳轉
	// 用法：return sc_response()->redirect("/home");
	public function redirect($url, $status = 302)
	{
		$this->header("Location", $url);
		$this->status($status);
		$this->body("");
		
		return $this;
	}
	
	// === 檔案下載 ===
	// 對應 Laravel：response()->download($filePath, $filename)
	// 作用：輸出檔案給瀏覽器下載（自動 Content-Type 依副檔名 + Content-Disposition attachment）
	// 用法：return sc_response()->download($filePath);（下載報表/匯出檔）
	public function download($filePath, $filename = null)
	{
		// 未指定檔名 → 用檔案本身名稱
		if (!@$filename) {
			$filename = basename($filePath);
		}
		
		$mimeType = @mime_content_type($filePath);
		
		// 無法辨識類型 → 通用二進位
		if (!$mimeType) {
			$mimeType = "application/octet-stream";
		}
		
		$this->header("Content-Type", $mimeType);
		$this->header("Content-Disposition", "attachment; filename=\"" . $filename . "\"");
		$this->body(@file_get_contents($filePath));
		
		return $this;
	}
	
	// === 附加 cookie ===
	// 對應 Laravel：withCookie($name, $value, $minutes)
	// 作用：加入 Set-Cookie header（minutes → expires；0 = 對話期 cookie）
	// 用法：return sc_response()->withCookie("lang", "tc", 60 * 24);（記住語言選擇 1 天）
	public function withCookie($name, $value, $minutes = 0)
	{
		// minutes > 0 → 算過期時間；0 = 對話期（瀏覽器關閉失效）
		$expires = $minutes > 0 ? time() + ($minutes * 60) : 0;
		
		$this->header("Set-Cookie", $name . "=" . urlencode($value) . "; expires=" . gmdate("D, d M Y H:i:s", $expires) . " GMT; path=/");
		
		return $this;
	}
	
	// === 設定 HTTP 快取 ===
	// 對應 Laravel：setCache($options)
	// 作用：設定快取相關 headers（Cache-Control/ETag/Last-Modified/Expires）——靜態資源/API 快取
	// options：public/private/immutable/max_age/s_maxage/etag/last_modified/expires
	// 用法：return sc_response()->setCache(["max_age" => 3600, "public" => true]);
	public function setCache($options = array())
	{
		$cacheControl = array();
		
		// Cache-Control 各旗標（對應 Laravel options）
		if (!empty($options["public"])) {
			$cacheControl[] = "public";
		}
		if (!empty($options["private"])) {
			$cacheControl[] = "private";
		}
		if (!empty($options["immutable"])) {
			$cacheControl[] = "immutable";
		}
		if (!empty($options["max_age"])) {
			$cacheControl[] = "max-age=" . $options["max_age"];
		}
		if (!empty($options["s_maxage"])) {
			$cacheControl[] = "s-maxage=" . $options["s_maxage"];
		}
		
		// 有 Cache-Control 旗標 → 送出
		if ($cacheControl) {
			$this->header("Cache-Control", implode(", ", $cacheControl));
		}
		
		// ETag（內容版本標記——例：hash 內容）
		if (!empty($options["etag"])) {
			$this->header("ETag", "\"" . $options["etag"] . "\"");
		}
		
		// Last-Modified（最後修改時間——timestamp → HTTP date）
		if (!empty($options["last_modified"])) {
			$this->header("Last-Modified", gmdate("D, d M Y H:i:s", $options["last_modified"]) . " GMT");
		}
		
		// Expires（過期時間——timestamp → HTTP date）
		if (!empty($options["expires"])) {
			$this->header("Expires", gmdate("D, d M Y H:i:s", $options["expires"]) . " GMT");
		}
		
		return $this;
	}
	
	// === 設定 ETag ===
	// 對應 Laravel：setEtag（快捷——setCache 的 etag 單一用法）
	// 作用：內容版本標記（快取驗證用）——瀏覽器可條件請求（If-None-Match）
	// 用法：return sc_response()->setEtag(md5($content));
	public function setEtag($etag)
	{
		return $this->setCache(array("etag" => $etag));
	}
	
	// === 設定 Last-Modified ===
	// 對應 Laravel：setLastModified（快捷——setCache 的 last_modified 單一用法）
	// 作用：最後修改時間（快取驗證用——瀏覽器可條件請求 If-Modified-Since）
	// 用法：return sc_response()->setLastModified(filemtime($filePath));
	public function setLastModified($timestamp)
	{
		return $this->setCache(array("last_modified" => $timestamp));
	}
	
	// === 設定 Expires ===
	// 對應 Laravel：setExpires（快捷——setCache 的 expires 單一用法）
	// 作用：明確過期時間（快取用——過期前直接取快取）
	// 用法：return sc_response()->setExpires(time() + 3600);
	public function setExpires($timestamp)
	{
		return $this->setCache(array("expires" => $timestamp));
	}
	
	// === 狀態檢查：資訊回應（1xx） ===
	// 對應 Symfony：isInformational——例：100 Continue
	public function isInformational()
	{
		$code = $this->getStatusCode();
		return $code >= 100 && $code < 200;
	}
	
	// === 狀態檢查：成功（2xx） ===
	// 對應 Symfony：isSuccessful——例：200 OK
	public function isSuccessful()
	{
		$code = $this->getStatusCode();
		return $code >= 200 && $code < 300;
	}
	
	// === 狀態檢查：重導向（3xx） ===
	// 對應 Symfony：isRedirection——例：302 Found
	public function isRedirection()
	{
		$code = $this->getStatusCode();
		return $code >= 300 && $code < 400;
	}
	
	// === 狀態檢查：客戶端錯誤（4xx） ===
	// 對應 Symfony：isClientError——例：404 Not Found
	public function isClientError()
	{
		$code = $this->getStatusCode();
		return $code >= 400 && $code < 500;
	}
	
	// === 狀態檢查：伺服器錯誤（5xx） ===
	// 對應 Symfony：isServerError——例：500 Internal Server Error
	public function isServerError()
	{
		$code = $this->getStatusCode();
		return $code >= 500 && $code < 600;
	}
	
	// === 狀態檢查：是否 404 ===
	// 對應 Symfony：isNotFound——例：測試時確認頁面不存在
	public function isNotFound()
	{
		return $this->getStatusCode() === 404;
	}
	
	// === 狀態檢查：是否 200 ===
	// 對應 Symfony：isOk——例：測試時確認回應成功
	public function isOk()
	{
		return $this->getStatusCode() === 200;
	}
	
	// == 舊式直接輸出（plugins 現役——output_json 直接輸出——ob 捕捉相容） ==
	// == 對應 Laravel：無（Laravel 用 json()——未來遷移 return sc_response()->json($data)） ==
	
	// === 回應失敗：輸出錯誤 code + 訊息（error > 0） ===
	// 用法：sc_response()->fail(988, "invalid request")（plugins request handler 現役）
	public function fail($error_code, $message, $extras=array())
	{
		$output_results = array();
		$output_results["error"] 			= $error_code;
		$output_results["error_msg"] 		= $message;
		$output_results["message"] 			= $message;
		$output_results["data"] 			= null;
		$output_results["alert_title"] 		= sc_translate("notice");
		$output_results["alert_content"] 	= $message;
		
		// 合併額外參數（可覆蓋 data 等欄位）
		if (is_array($extras) && !empty($extras)) {
			$output_results = array_replace($output_results, $extras);
		}
		
		output_json($output_results,true);
	}
	
	// === 回應成功：輸出訊息（error = 0） ===
	// 用法：sc_response()->ok("success", $extras)（plugins request handler 現役）
	public function ok($message, $extras=array())
	{
		$output_results = array();
		$output_results["error"] 			= 0;
		$output_results["error_msg"] 		= $message;
		$output_results["message"] 			= $message;
		$output_results["data"] 			= null;
		$output_results["alert_title"] 		= sc_translate("notice");
		$output_results["alert_content"] 	= $message;
		
		// 合併額外參數（可覆蓋 data 等欄位）
		if (is_array($extras) && !empty($extras)) {
			$output_results = array_replace($output_results, $extras);
		}
		
		output_json($output_results,true);
	}
	
	// == 過複雜不抄（依使用者原則——需時再補）： ==
	// == Laravel 的 prepare($request)：依請求調整編碼/charset/內容長度——PHP 自動處理，本版不需 ==
	// == Laravel 的 view()/redirect 助手/Response 工廠：你的 view=include 機制、無需工廠 ==
	
}
