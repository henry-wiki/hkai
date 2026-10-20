<?php
namespace sc\framework\foundation\bootstrap;

// == sc_boot_load_routes_Class：引導③——載入路由 ==
// == 對應 Laravel Kernel bootstrappers 的 RegisterProviders（RouteServiceProvider 載入路由） ==
// == 職責：集中路由（sc_routes/api.php + app.php + channels.php——Laravel RouteServiceProvider 對應） ==
// ==        + 掃描 sc_body 各域 __register__.php（plugin / asset 註冊，路由已集中） ==
// == 由 kernel::bootstrap() 依 $bootstrappers 陣列順序執行 ==

class sc_boot_load_routes_Class
{
    // 執行引導步驟（Laravel Bootstrapper::bootstrap 同構——單一方法）
    public function bootstrap()
    {
        // ① 集中路由（Laravel RouteServiceProvider::map 對應——sc_routes/api.php + app.php + request.php + channels.php）
        require_once SC_DIR_CONTENTS . "/sc_routes/api.php";
        require_once SC_DIR_CONTENTS . "/sc_routes/app.php";
        require_once SC_DIR_CONTENTS . "/sc_routes/request.php";
        require_once SC_DIR_CONTENTS . "/sc_routes/channels.php";
        
        // ② 應用資料層（舊 session-store 已備份至 sc_app/models_bak——新結構由 plugins 提供）
        //require_once SC_DIR_APP . "/models/session-store.php";
        
        // ③ 各域 class/func 檔（sc_api 的 bridging/controller、sc_app 各 app 的 cls——全域函數與 controller 定義）
        //sc_file_loader()->load(SC_DIR_API, "re:/\.(cls|func)\.php$/i");
        //sc_file_loader()->load(SC_DIR_APP, "re:/\.(cls|func)\.php$/i");
        
        // ④ sample 示範域（4 種 handler 型別示範——已被 ③ 全樹涵蓋，無需重複載入）
    }
}
