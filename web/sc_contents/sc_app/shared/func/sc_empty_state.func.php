<?php
// == sc_app/shared/func/sc_empty_state.func.php：共用空狀態區塊（全 app 通用——面板與主區共用同一份 markup） ==
// == 用法：sc_empty_state("請選擇專案", false) → 回 <div class='sc_empty_state'>…</div>（第二參數 true → 帶 hidden） ==
// == 樣式：sc_app/shared/styles/css/sc_empty_state.css（group "*" 全域載入）；文案由呼叫端經 i18n 取得 ==

// 空狀態區塊（純顯示——HTML 由 PHP 產生；顯隱由呼叫端在 PHP 端決定）
function sc_empty_state($empty_state_text, $empty_state_hidden, $empty_state_extra_class = "")
{
    $empty_state_text_html = htmlspecialchars($empty_state_text);

    $hidden_attribute = "";
    // 判定：需隱藏 → 加 hidden 屬性
    if ($empty_state_hidden) {
        $hidden_attribute = " hidden";
    }

    $extra_class_attribute = "";
    // 判定：呼叫端指定情境 class → 併入（空格分隔）
    if ($empty_state_extra_class !== "") {
        $extra_class_attribute = " " . $empty_state_extra_class;
    }

    return "<div class='sc_empty_state" . $extra_class_attribute . "'" . $hidden_attribute . ">" . $empty_state_text_html . "</div>";
}
