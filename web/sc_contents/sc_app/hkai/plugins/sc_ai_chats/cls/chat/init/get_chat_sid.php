<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\init;

// == sc_ai_chats chat get_chat_sid：取當前 chat_sid（trait——掛入 chat_Class；檔＝動作）
// == 職責：自行判斷情境——POST（request）＝payload；頁面＝$_GET → URL 第二段；冇值＝""
// == 上游：page view／render、fetch 端點；下游：$_POST／$_GET／sc_get_friendly()

trait get_chat_sid
{
    // 取 chat_sid（POST＝payload；頁面＝$_GET → URL 第二段；冇＝""）
    public function get_chat_sid()
    {
        $is_post_request = ($_SERVER["REQUEST_METHOD"] === "POST");

        $chat_sid = "";

        // 判定：POST（request）→ 讀 payload 嘅 chat_sid
        if ($is_post_request === true) {
            $input_data = sc_request()->json();

            // 判定：表單欄位有值 → 直接採用
            if (isset($_POST["chat_sid"])) {
                $chat_sid = $_POST["chat_sid"];
            } else if (isset($input_data["chat_sid"])) {
                // JSON body（fetch 端點用 JSON——同一份 payload）
                $chat_sid = $input_data["chat_sid"];
            }
        } else if (isset($_GET["chat_sid"])) {
            // 頁面情境：網址參數優先
            $chat_sid = $_GET["chat_sid"];
        } else {
            // 頁面情境：退回 URL 第二段
            $chat_sid = sc_get_friendly(1);
        }

        // 判定：null（URL 冇對話段）→ 空字串
        if ($chat_sid === null) {
            $chat_sid = "";
        }

        return $chat_sid;
    }
}
