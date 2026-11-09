<?PHP

/**
 * 資料覆寫路徑（data overrides）
 *
 * 用途：唯讀安裝（MSIX）之下，排行榜／providers 更新只准寫資料根；
 *       讀取時先查 data override，冇才用程式包內建檔。
 *
 * @param string $group_name      分組名（例 benchmark／providers）
 * @param string $source_file_path 程式包內原檔路徑
 * @return string 覆寫檔絕對路徑
 */
function sc_data_override_path($group_name, $source_file_path)
{
    $override_dir  = sc_data_path()->get_data_sub_dir("overrides/" . $group_name);
    $file_basename = basename($source_file_path);

    return $override_dir . "/" . $file_basename;
}
