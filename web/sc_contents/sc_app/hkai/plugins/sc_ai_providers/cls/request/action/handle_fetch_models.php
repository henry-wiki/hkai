<?php

namespace sc\app\hkai\plugins\sc_ai_providers\request\action;

// == sc_ai_providers request handle_fetch_models：域動作（trait——掛入 request_Class；檔＝動作） ==
// == 撳左欄導航逐個載入——單一 provider 嘅 model 列表（provider_id 指定） ==

trait handle_fetch_models
{
    // POST fetch_models：單一 provider model 列表（provider_id 指定；provider 須可用先有數）
    public function handle_fetch_models()
    {
        $input_data = sc_request()->json();
        $provider_id = isset($input_data["provider_id"]) ? $input_data["provider_id"] : "";
        
        // 可用 provider（聲明——存在＝前台可用）
        $available_providers = sc_ai_providers()->main()->get_available_providers();
        
        // provider 唔可用（唔喺聲明）→ 400（前端顯示失敗）
        if (!isset($available_providers[$provider_id])) {
            return sc_response()->json(["ok" => false, "error" => "unknown provider: " . $provider_id], 400);
        }
        
        // model 列表頁（具名分派——kilo／openrouter 直接跳；F12 單一 target；數據由包內自取）
        if ($provider_id === "kilo") {
            $model_list_page = sc_ai_providers()->instance__kilo()->view()->get_model_list_page($provider_id);
        } elseif ($provider_id === "openrouter") {
            $model_list_page = sc_ai_providers()->instance__openrouter()->view()->get_model_list_page($provider_id);
        } else {
            // 無對應 provider（唔係 kilo／openrouter）→ 400（前端顯示失敗）
            return sc_response()->json(["ok" => false, "error" => "unknown provider: " . $provider_id], 400);
        }
        
        // provider 名（標題用——可用 provider 聲明；逐層落同名變數）
        $provider_item = $available_providers[$provider_id];
        $provider_name = $provider_item["provider_name"];
        
        // 底兩條（efforts 帶＋底列——每頁自帶）
        $provider_footer_lines = [];
        $provider_footer_lines[] = "<div id='model_selector_efforts' class='modal__content-model_selector_efforts'></div>";
        $provider_footer_lines[] = "<div class='modal__content-footer'>";
            $provider_footer_lines[] = "<div class='modal__content-footer-info'></div>";
            $provider_footer_lines[] = "<button id='model_selector_apply' type='button' class='button button--primary'>Apply</button>";
        $provider_footer_lines[] = "</div>";
        
        // html：provider 頁（大 table——後端砌，前端只 insert；同設定 modal 做法）
        $html = [
            "header" => "<div class='modal__title'>" . $provider_name . "</div>",
            "body"   => $model_list_page,
            "footer" => implode("\r\n", $provider_footer_lines),
        ];
        
        return sc_response()->json([
            "ok"          => true,
            "provider_id" => $provider_id,
            "html"        => $html,
        ]);
    }
}
