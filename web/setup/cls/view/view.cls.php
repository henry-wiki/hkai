<?php

namespace sc\setup\view;

// == HKAI setup view：view 域本體（域本體——singleton；呼叫形 setup()->view()->load_setup_page()）
// == 內容：class（singleton）＋主動作 load_setup_page（本檔直落）＋各動作（trait——一檔一動作）由本檔頂 require
// == 註冊表：來源＝sections 域 sections_Class（唔在本域 inlined）｜次序＝welcome → prerequisite → database → settings → install → completed
// == section 單元：cls/sections/<格>/<格>.php（每格一目錄·檔名單數；呼叫形 setup()->sections()-><id>()；細節住入面）

// 載入五個頁段動作 trait（一檔一動作——trait 先過 class）
require_once __DIR__ . "/build_page_head.php";
require_once __DIR__ . "/build_page_body.php";
require_once __DIR__ . "/build_modal_left.php";
require_once __DIR__ . "/build_modal_right.php";
require_once __DIR__ . "/build_page_foot.php";
require_once __DIR__ . "/get_form_input.php";
require_once __DIR__ . "/get_form_select.php";
require_once __DIR__ . "/render_setup_check.php";
require_once __DIR__ . "/get_check_item_template.php";

class view_Class
{
    use build_page_head;
    use build_page_body;
    use build_modal_left;
    use build_modal_right;
    use build_page_foot;
    use get_form_input;
    use get_form_select;
    use render_setup_check;
    use get_check_item_template;

    private static $instance = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\view\view_Class
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    // 出整頁（獨立入口 GET）
    public function load_setup_page()
    {
        // 三段組裝（每段一檔一動作——body 收註冊表；檢查結果由 modal right 自己攞）
        $page_lines = [];

        $head_lines = $this->build_page_head();

        // 逐行入列（一行一動作——統一 $page_lines[] 形）
        foreach ($head_lines as $head_line_item) {
            $page_lines[] = $head_line_item;
        }

        $body_lines = $this->build_page_body(setup()->sections()->get_sections());

        // 逐行入列（同上）
        foreach ($body_lines as $body_line_item) {
            $page_lines[] = $body_line_item;
        }

        $foot_lines = $this->build_page_foot();

        // 逐行入列（同上）
        foreach ($foot_lines as $foot_line_item) {
            $page_lines[] = $foot_line_item;
        }

        echo implode("\r\n", $page_lines);
    }
}
