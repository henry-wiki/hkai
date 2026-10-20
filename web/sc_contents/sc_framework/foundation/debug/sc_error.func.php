<?php

// == foundation 對外 API（純函數：錯誤觸發薄包裝） ==
// == 觸發後由 register 的 sc_error::handle_error 攔截顯示 ==

function sc_trigger_error($message)
{
    // 附呼叫者位置（薄包裝上一層 = 真正呼叫 sc_trigger_error 的位置）
    $backtrace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller       = (isset($backtrace[0]) ? $backtrace[0] : null);
    $full_message = $message;
    if (@$caller) {
        $full_message .= " (at " . $caller["file"] . " : " . $caller["line"] . ")";
    }
    trigger_error($full_message, E_USER_ERROR);
    die();
}

function sc_trigger_warning($message)
{
    $backtrace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller       = (isset($backtrace[0]) ? $backtrace[0] : null);
    $full_message = $message;
    if (@$caller) {
        $full_message .= " (at " . $caller["file"] . " : " . $caller["line"] . ")";
    }
    trigger_error($full_message, E_USER_WARNING);
}
