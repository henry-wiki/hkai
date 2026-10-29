<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\upload;

// == sc_ai_chats attachment write_thumbnail：寫縮圖（長邊 ≤ hkai.draft.thumb_side_px；成功＝true）（trait——掛入 attachment_Class） ==
// == 上游：process_uploaded_image；下游：GD（imagepng／imagejpeg） ==

trait write_thumbnail
{

    // 寫縮圖（長邊 ≤ hkai.draft.thumb_side_px；成功＝true）
    public function write_thumbnail($binary, $thumb_path)
    {
        $image_resource = @imagecreatefromstring($binary);

        // 判定：解唔到圖 → 假
        if ($image_resource === false) {
            return false;
        }

        $source_width  = imagesx($image_resource);
        $source_height = imagesy($image_resource);
        $max_side_px   = (int)sc_get_config("hkai.draft.thumb_side_px");
        $scale_ratio   = 1.0;
        $longest_side  = max($source_width, $source_height);

        // 判定：長邊超過上限 → 計縮放比
        if ($longest_side > $max_side_px) {
            $scale_ratio = $max_side_px / $longest_side;
        }

        $thumb_width  = max(1, (int)round($source_width * $scale_ratio));
        $thumb_height = max(1, (int)round($source_height * $scale_ratio));

        $thumb_resource = imagecreatetruecolor($thumb_width, $thumb_height);

        // 白底（PNG 透明 → JPEG）
        $white_color = imagecolorallocate($thumb_resource, 255, 255, 255);
        imagefilledrectangle($thumb_resource, 0, 0, $thumb_width, $thumb_height, $white_color);
        imagecopyresampled($thumb_resource, $image_resource, 0, 0, 0, 0, $thumb_width, $thumb_height, $source_width, $source_height);

        $thumb_saved = @imagejpeg($thumb_resource, $thumb_path, 80);

        imagedestroy($thumb_resource);
        imagedestroy($image_resource);

        // 判定：存唔到 → 假
        if ($thumb_saved === false) {
            return false;
        }

        return true;
    }
}
