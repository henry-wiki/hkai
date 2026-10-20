<?php
namespace sc\framework\foundation\bootstrap;

// == sc_boot_register_error_handler_Class：引導②——掛載錯誤食者 ==
// == 對應 Laravel Kernel bootstrappers 的 HandleExceptions ==
// == 職責：sc_error()->register()（現役 handler，捕 E_USER_* 與 E_ERROR/E_PARSE） ==
// == 由 kernel::bootstrap() 依 $bootstrappers 陣列順序執行 ==

class sc_boot_register_error_handler_Class
{
    // 執行引導步驟（Laravel Bootstrapper::bootstrap 同構——單一方法）
    public function bootstrap()
    {
        sc_error()->register();
    }
}
