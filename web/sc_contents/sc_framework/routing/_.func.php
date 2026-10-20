<?php

// == routing 對外 API（bridging：回傳 class 單例／工廠——bridging 集中於域根） ==
// == 集中：sc_router（路由管理器單例）、sc_controller（執行語境工廠——每次路由執行 new） ==

use sc\framework\routing\router;
use sc\framework\routing\controller;

/**
 * 取得路由管理器單例
 * @return router\sc_router_Class
 */
function sc_router()
{
    return router\sc_router_Class::getInstance();
}

/**
 * 取得 controller（Laravel Illuminate\Routing\Controller 對應）
 * @param array $route 路由匹配資料（route_path/request_path/route_data/group）；null = 主 controller（管理器）
 * @return controller\sc_controller_Class
 */
function sc_controller($route = null)
{
    return controller\sc_controller_Class::get_instance($route);
}
