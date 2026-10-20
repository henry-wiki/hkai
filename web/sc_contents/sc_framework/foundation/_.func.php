<?php
use sc\framework\foundation;

require_once __DIR__ . "/loader/sc_autoload.cls.php";


// =========================================================================================================================
// == foundation 對外 API（bridging：回傳 class 單例） ==
// == 集中：getClass / sc_file_loader / sc_autoload / sc_error / sc_session / sc_plugin / sc_settings —— 對外入口一律 global ==
// == 規則：bridging 只放域根檔，子目錄不放 bridging ==
// =========================================================================================================================

function getClass($class_name, $args = [])
{
    $class_name = sc_trim($class_name);
    
    // == 解析完整 class 名：已含 _Class 結尾 → 直接用；否則依命名慣例拼 _Class ==
    if (substr($class_name, -6) !== "_Class") {
        if (strpos($class_name, "\\") !== false) {
            $class_name = $class_name . "_Class";
        } else {
            $class_name = "sc\\" . $class_name . "_Class";
        }
    }
    
    $args = @is_array($args) ? $args : [];
    
    if (class_exists($class_name)) {
        $class = call_user_func_array($class_name . '::getInstance', $args);
    } else {
        $dieMessage = "";
        $dieMessage .= $class_name . " cannot be found.<br/>";
        $dieMessage .= __FILE__ . " (Line : " . __LINE__ . ")<br/>";
        
        sc_trigger_error($dieMessage, E_USER_ERROR);
    }
    return $class;
}

/**
* 取得目錄載入器單例（遞迴 include + 過濾——原名 sc_loader/sc_bootstrap：純載入器、非引導器）
* @return foundation\loader\sc_file_loader_Class
*/
function sc_file_loader()
{
    return foundation\loader\sc_file_loader_Class::getInstance();
}
function sc_autoload()
{
    return foundation\loader\sc_autoload_Class::getInstance();
}

/**
* 取得應用（Application）單例
* @param string|null $basePath 應用根路徑（預設 SC_DOCUMENT_ROOT）
* @return foundation\application\sc_application_Class
*/
function sc_application($basePath = null)
{
    return foundation\application\sc_application_Class::getInstance($basePath);
}

/**
* 取得 HTTP Kernel 單例（引導 + 分發合體——Laravel Foundation\Http\Kernel 對應）
* @return foundation\http\kernel\sc_kernel_Class
*/
function sc_kernel()
{
    return foundation\http\kernel\sc_kernel_Class::getInstance();
}

/**
* 取得 Console Kernel 單例（CLI 入口——Laravel Console\Kernel 對應）
* @return foundation\console\sc_console_kernel_Class
*/
function sc_console_kernel()
{
    return foundation\console\sc_console_kernel_Class::getInstance();
}

/**
* 註冊 CLI 命令（對應 Laravel Artisan::command——由 sc_routes/console.php 呼叫）
* @param string $commandName 命令名（如 "status"）
* @param string $commandFile 命令檔路徑（如 SC_DOCUMENT_ROOT . "/console/commands/status.php"）
*/
function sc_console_command($commandName, $commandFile)
{
    sc_console_kernel()->register_command($commandName, $commandFile);
}
function sc_error()
{
    return foundation\debug\sc_error_Class::getInstance();
}

function sc_session_file()
{
    return foundation\session\sc_session_file_Class::getInstance();
}
function sc_session()
{
    return foundation\session\sc_session_Class::getInstance();
}

/**
* 取得 plugin（對稱 sc_controller 模式）
* @param string $plugin_id       plugin id（null = 主 plugin 管理器）
* @param string $base_path       plugin 根目錄（首次建立時傳入）
* @param array  $plugin_settings plugin settings（可選——缺省值由 plugin class 合併）
* @return foundation\plugin\sc_plugin_Class
*/
function sc_plugin($plugin_id = null, $plugin_dir = null, $plugin_settings = [])
{
    return foundation\plugin\sc_plugin_Class::getInstance($plugin_id, $plugin_dir, $plugin_settings);
}

function sc_settings()
{
    return foundation\settings\sc_settings_Class::getInstance();
}

// sc_config 域 API（sc_config／sc_get_config／sc_set_config／sc_set_configs）已進域：SC_WEB_ROOT . "/sc_config/cls/sc_config.func.php"（load.php 首先 require）
