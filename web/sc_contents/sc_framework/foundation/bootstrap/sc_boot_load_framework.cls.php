<?php
namespace sc\framework\foundation\bootstrap;

// == sc_boot_load_framework_Class：引導①——載入框架核心 ==
// == 對應 Laravel Kernel bootstrappers 的 LoadEnvironmentVariables/LoadConfiguration/RegisterFacades ==
// == 職責：polyfill / function / foundation + 各域 func 檔（全域函數與核心 class 定義） ==
// == 由 kernel::bootstrap() 依 $bootstrappers 陣列順序執行 ==

class sc_boot_load_framework_Class
{
    // 執行引導步驟（Laravel Bootstrapper::bootstrap 同構——單一方法）
    public function bootstrap()
    {
        sc_file_loader()->load(SC_DIR_FRAMEWORK . "/polyfill");
        sc_file_loader()->load(SC_DIR_FRAMEWORK . "/function");
        sc_file_loader()->load(SC_DIR_FRAMEWORK . "/foundation/");

        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/http/"   );
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/routing/");
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/view/"   );
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/i18n/"   );
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/helper/" );
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/utils/"  );
        sc_file_loader()->load_func(SC_DIR_FRAMEWORK . "/database/");

        // 固定結構：func 檔只住 {app}/cls 與 {app}/func
        // 各 app 的 cls（{app}/cls/**）
        foreach (glob(SC_DIR_APP . "/*/cls", GLOB_ONLYDIR) as $app_cls_dir) {
            sc_file_loader()->load_func($app_cls_dir);
        }

        // 各 app 的 func（{app}/func/**）
        foreach (glob(SC_DIR_APP . "/*/func", GLOB_ONLYDIR) as $app_func_dir) {
            sc_file_loader()->load_func($app_func_dir);
        }

        // 各 plugin 的 cls（{app}/plugins/{plugin}/cls/**）
        foreach (glob(SC_DIR_APP . "/*/plugins/*/cls", GLOB_ONLYDIR) as $plugin_cls_dir) {
            sc_file_loader()->load_func($plugin_cls_dir);
        }
    }
}
