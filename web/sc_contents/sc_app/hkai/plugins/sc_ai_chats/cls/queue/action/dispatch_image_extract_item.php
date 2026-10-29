<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue dispatch_image_extract_item：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：pending 箱——通知抽取器（notify.image_extractor 標記 60 秒內有效；過期＝當未通知重發） ==
// == 上游：dispatch_polling_item；下游：notify_image_extract（bridge fire-and-forget） ==

trait dispatch_image_extract_item
{
    // 標記有效期（秒——過期即當「未通知」重試）
    private const NOTIFY_IMAGE_MARKER_FRESH_SEC = 60;

    // 通知抽取器（回 bool＝今次有冇送出通知）
    public function dispatch_image_extract_item($poll_item, $box_dir)
    {
        $notify_marker = $box_dir . "/notify.image_extractor";

        $is_marker_fresh = false;

        // 判定：有標記 → 計年齡
        if (is_file($notify_marker)) {
            $marker_age_sec = time() - (int)@filemtime($notify_marker);

            // 判定：喺有效期內 → 當已通知
            if ($marker_age_sec <= self::NOTIFY_IMAGE_MARKER_FRESH_SEC) {
                $is_marker_fresh = true;
            }
        }

        // 判定：標記新鮮 → 跳過
        if ($is_marker_fresh === true) {
            return false;
        }

        // 舊標記（過期／假成功）→ 清走，重新通知
        if (is_file($notify_marker)) {
            unlink($notify_marker);
        }

        $is_notified = sc_ai_chats()->queue()->notify_image_extract($poll_item);

        // 判定：送唔出（bridge 未起）→ 唔落標記（下拍再試）
        if ($is_notified === false) {
            return false;
        }

        touch($notify_marker);

        return true;
    }
}
