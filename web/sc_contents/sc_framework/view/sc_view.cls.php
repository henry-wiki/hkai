<?php
namespace sc\framework\view;

// == sc_view：延遲渲染 View 物件（對應 Laravel Illuminate\View\View——render 延遲到 Response 建立） ==
// == 使用：controller return sc_view("main/welcome", $data)——資料以陣列契約傳入，view 內以變數取得 ==
// == 渲染：PhpEngine::get 同款（ob + extract + require——純 PHP 最快，零編譯） ==

class sc_view_Class
{
    private $template_path;
    private $data;
    private $controller;
    
    public function __construct($template_path, $data = [])
    {
        $this->template_path = $template_path;
        $this->data          = $data;
    }
    
    // 注入執行語境 controller（由 sc_controller_Class::view() 建立時呼叫——模板內 $this->get_controller() 可達 controller）
    public function set_controller($controller)
    {
        $this->controller = $controller;
    }
    
    // 取得執行語境 controller（Yii2 view->context 對應——模板內可達路由資料：get_route_data()/get_request_path() 等）
    public function get_controller()
    {
        return $this->controller;
    }
    public function get_route()
    {
        return $this->controller->get_route();
    }
    
    // 渲染（PhpEngine::get 同款——ob + extract + require；純 PHP 最快）
    public function render()
    {
        $data = $this->data;
        ob_start();
        extract($data, EXTR_SKIP);
        require $this->template_path;
        return ob_get_clean();
    }
    
    // 取得資料（測試/檢查用——Laravel View::getData 對應）
    public function getData()
    {
        return $this->data;
    }
}
