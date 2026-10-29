<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\guard;

// == sc_ai_chats attachment webp_to_jpg_or_gif_to_png：轉 provider 格式（GIF→PNG 保留透明；WebP 等→JPEG 白底；失敗回空 array）（trait——掛入 attachment_Class） ==
// == 上游：attachment 上傳／匯入路徑；下游：GD 函數 ==

trait webp_to_jpg_or_gif_to_png
{

    // 轉 provider 格式（GIF→PNG 保留透明；WebP 等→JPEG 白底；失敗回空 array；public：draft 上傳共用）
    // 原因：ACP 圖片 block 只收 PNG／JPEG——GIF 保留透明轉 PNG（取第一幀），其餘（WebP 等）轉 JPEG 白底
    public function webp_to_jpg_or_gif_to_png($binary, $detected_mime_type)
    {
        $image_resource = @imagecreatefromstring($binary);

        // 判定：GD 解唔到 → 回空
        if ($image_resource === false) {
            return [];
        }

        $image_width  = imagesx($image_resource);
        $image_height = imagesy($image_resource);

        $canvas = imagecreatetruecolor($image_width, $image_height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent_color = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $image_width, $image_height, $transparent_color);
        imagecopy($canvas, $image_resource, 0, 0, 0, 0, $image_width, $image_height);

        // 判定：GIF → PNG（保留透明；動畫取第一幀）
        if ($detected_mime_type === "image/gif") {
            ob_start();
            $write_result = imagepng($canvas);
            $output_binary = ob_get_clean();

            imagedestroy($image_resource);
            imagedestroy($canvas);

            // 判定：寫 PNG 失敗 → 回空
            if ($write_result === false) {
                return [];
            }

            return ["mime_type" => "image/png", "binary" => (string)$output_binary];
        }

        // 其餘（WebP 等）→ JPEG 白底（避免透明變黑；體積遠細過 PNG）
        $white_color = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $image_width, $image_height, $white_color);
        imagecopy($canvas, $image_resource, 0, 0, 0, 0, $image_width, $image_height);

        ob_start();
        $write_result = imagejpeg($canvas, null, 90);
        $output_binary = ob_get_clean();

        imagedestroy($image_resource);
        imagedestroy($canvas);

        // 判定：寫 JPEG 失敗 → 回空
        if ($write_result === false) {
            return [];
        }

        return ["mime_type" => "image/jpeg", "binary" => (string)$output_binary];
    }
}
