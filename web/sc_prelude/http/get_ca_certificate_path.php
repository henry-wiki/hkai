<?PHP

/**
 * 取得 CA 憑證路徑（只讀；唔再下載／更新）
 *
 * 次序：
 * 1. 資料根 ssl/cacert.pem（sc_data_path()）
 * 2. 內建 web/sc_config/ssl/cacert.pem
 *
 * @return string|false
 */
function get_ca_certificate_path()
{
    $data_cert_path = sc_data_path()->get_data_sub_dir("ssl/cacert.pem");

    // 判定：資料根有憑證 → 用佢
    if (file_exists($data_cert_path) === true) {
        return $data_cert_path;
    }

    $bundled_cert_path = SC_DIR_FRAMEWORK . "/../config/ssl/cacert.pem";

    // 判定：內建憑證存在 → 用佢
    if (file_exists($bundled_cert_path) === true) {
        return $bundled_cert_path;
    }

    return false;
}
