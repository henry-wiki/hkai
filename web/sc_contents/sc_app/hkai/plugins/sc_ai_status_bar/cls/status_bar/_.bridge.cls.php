<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar;

// == sc_ai_status_bar main：main 域本體（bridge 檔——singleton；呼叫形 sc_ai_status_bar()->status_bar()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat main_Class 合成） ==
// == 2026-11-03：外部注入 status item（add_status_item／apply_status_items 整條鏈）已移除——零消費者 ==

class status_bar_Class
{
    use state\update;
    use render\render_project_region;
    use render\render_chat_region;
    use render\render_workspace_region;

    private $groups = [];
    private $item_states = [];

    // 定義 status bar groups（靜態設定）
    private function initial_groups()
    {
        // conn 首屏真值（之後由 SSE conn target 接手；讀本機心跳檔，零 HTTP）
        $server_status = hkai()->process()->server()->get_server_status();

        $conn_default = hkai()->i18n()->get("status_bar.conn_offline");
        $conn_class   = "bad";

        // 判定：bridge 心跳在 → 首屏直接顯示已連線
        if ($server_status["is_alive"] === true) {
            $conn_default = hkai()->i18n()->get("status_bar.conn_online");
            $conn_class   = "ok";
        }

        // group: project（對應專案欄）
        $this->groups["project"] = [
            "items" => [
                "sb_project" => [
                    "icon"     => "📁",
                    "default"  => "—",
                    "priority" => 10,
                ],
            ],
            // 掛載槽（外部模組注入用——id 沿用既有契約）
            "slot" => ["region" => "project"],
        ];
        
        // group: chat（對應對話欄）
        $this->groups["chat"] = [
            "items" => [
                "sb_chat" => [
                    "icon"     => "💬",
                    "default"  => "—",
                    "priority" => 10,
                ],
            ],
            // 掛載槽（外部模組注入用——id 沿用既有契約）
            "slot" => ["region" => "chat"],
        ];
        
        // group: main（對應主區——分 left / right 兩個 sub）
        $this->groups["workspace"] = [
            "subs" => [
                "left" => [
                    "items" => [],
                ],
                "right" => [
                    "items" => [
                        "sb_conn" => [
                            "icon"     => "●",
                            "default"  => $conn_default,
                            "class"    => $conn_class,
                            "priority" => 20,
                            "click"    => ["type" => "settings", "tab" => "server"],
                        ],
                        "sb_clock" => [
                            "icon"     => "",
                            "default"  => "--:--",
                            "priority" => 30,
                        ],
                    ],
                ],
            ],
        ];
        
        // 初始化 item_states（從 groups 結構提取預設值）
        $this->initial_item_states();
    }

    // 從 groups 結構提取所有 items 的預設值
    private function initial_item_states()
    {
        foreach ($this->groups as $group_id => $group_config) {
            // 有 subs 的 group（如 main）
            if (isset($group_config["subs"])) {
                foreach ($group_config["subs"] as $sub_id => $sub_config) {
                    $this->extract_items($sub_config["items"] ?? []);
                }
            }
            // 直接有 items 的 group（如 project、chat）
            else {
                $this->extract_items($group_config["items"] ?? []);
            }
        }
    }

    // 提取 items 到 item_states（預設值——render 初值）
    private function extract_items($items)
    {
        foreach ($items as $item_id => $item_config) {
            $this->item_states[$item_id] = [
                "text"   => $item_config["default"] ?? "",
                "class"  => $item_config["class"] ?? "",
                "hidden" => $item_config["hidden"] ?? false,
            ];
        }
    }

    // 按頁面情境渲染指定區（三端點共用——情境還原：provider closures 讀 $_SERVER["REQUEST_URI"] 判當前頁，請求情境暫時換成頁面 URL；render 完還原）
    private function render_region_for_page($region_key, $project_slug, $chat_sid)
    {
        // 頁面段拼回頁面 URL（project_slug ＋ 對話段 chat_sid〔含歸檔語境形 archive@<chat_sid>〕）
        $page_uri = "/" . $project_slug . "/";

        // 判定：有對話段 → 補入頁面 URL
        if ($chat_sid !== "") {
            $page_uri .= $chat_sid . "/";
        }

        $previous_request_uri   = $_SERVER["REQUEST_URI"];
        $_SERVER["REQUEST_URI"] = $page_uri;

        $region_html = "";

        // 判定：project 區
        if ($region_key === "project") {
            $region_html = $this->render_project_region();
        }

        // 判定：chat 區
        if ($region_key === "chat") {
            $region_html = $this->render_chat_region();
        }

        // 判定：workspace 區
        if ($region_key === "workspace") {
            $region_html = $this->render_workspace_region();
        }

        // 情境還原（交還請求自身嘅 URL 情境）
        $_SERVER["REQUEST_URI"] = $previous_request_uri;

        return $region_html;
    }

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
    {
        $this->initial_groups();
    }
}
