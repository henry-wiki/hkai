<?php

namespace sc\app\hkai\cls\web_page;

// == web_page_Class：web_page 域根（singleton——經 hkai()->web_page() 取得） ==
// == 子域：builder()（整頁 HTML 組裝）／activity()（頁面記憶讀寫）；class 各自獨立喺自己目錄
// == 上游：hkai_Class::web_page()（route 入口 index.php、activity request）；下游：cls/web_page/builder/builder_Class、cls/web_page/activity/activity_Class

class web_page_Class
{
    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    /**
     * builder 子域（整頁 HTML 組裝）
     *
     * @return \sc\app\hkai\cls\web_page\builder\builder_Class
     */
    public function builder()
    {
        return builder\builder_Class::getInstance();
    }

    /**
     * activity 子域（頁面記憶讀寫）
     *
     * @return \sc\app\hkai\cls\web_page\activity\activity_Class
     */
    public function activity()
    {
        return activity\activity_Class::getInstance();
    }
}
