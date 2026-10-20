<?PHP

// == utils 對外 API（bridging：回傳 class 單例） ==

use sc\framework\utils;


/**
 * 取得 debug profiler 工具單例
 * @return utils\debug_profiler\sc_debug_profiler_Class
 */
function sc_debug_profiler()
{
    return utils\debug_profiler\sc_debug_profiler_Class::getInstance();
}

/**
 * 取得檔案系統工具單例
 * @return utils\file_system\sc_directory_Class
 */
function sc_directory()
{
    return utils\file_system\sc_directory_Class::getInstance();
}
