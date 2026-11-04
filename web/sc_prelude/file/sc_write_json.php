<?PHP

// 寫入 JSON 檔（美化格式——sc_pretty_json；原子替換：先寫暫存檔，再 rename 蓋正式檔）
// 職責：寫入期間唔會鎖住正式檔——讀者任何時候都讀到完整內容（同磁碟區 rename＝原子）
function sc_write_json($filepath, $array)
{
    $json_text = sc_pretty_json($array);

    // 目錄自愈（呼叫方唔需要自行 mkdir——目錄被移除都照寫得返）
    $directory_path = dirname($filepath);

    // 判定：目錄不存在 → 建立
    if (is_dir($directory_path) === false) {
        sc_mkdir($directory_path);
    }

    // 暫存檔（同目錄——rename 必須同磁碟區；進程 id ＋ 隨機數避免碰撞）
    $temp_filepath = $filepath . "." . getmypid() . "." . bin2hex(random_bytes(4)) . ".tmp";

    $written_bytes = file_put_contents($temp_filepath, $json_text, LOCK_EX);

    // 判定：寫暫存失敗 → 清走殘檔、回 false
    if ($written_bytes === false) {
        @unlink($temp_filepath);

        return false;
    }

    // rename 蓋正式檔（Windows：目標存在都可覆蓋）
    $is_renamed = @rename($temp_filepath, $filepath);

    // 判定：rename 失敗（讀者短暫持有 handle／防毒掃描）→ 等 20ms 重試，最多 3 次
    if ($is_renamed === false) {
        $retry_index = 0;

        // 逐次重試
        while ($retry_index < 3) {
            usleep(20000);

            $is_renamed = @rename($temp_filepath, $filepath);

            // 判定：今次成功 → 跳出
            if ($is_renamed === true) {
                break;
            }

            $retry_index = $retry_index + 1;
        }
    }

    // 判定：仍然失敗 → 清走暫存檔、回 false
    if ($is_renamed === false) {
        @unlink($temp_filepath);

        return false;
    }

    return $written_bytes;
}
