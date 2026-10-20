<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar;

// == sc_ai_status_bar main：main 域本體（bridge 檔——singleton；呼叫形 sc_ai_status_bar()->status_bar()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat main_Class 合成） ==
// == 2026-10-04：add_status_item 併入本檔（原 item/ trait 檔刪除——方法直挂 class） ==

class status_bar_Class
{
    use request\project_status_bar_fetch;
    use request\chat_status_bar_fetch;
    use request\workspace_status_bar_fetch;
    use state\update;
    use state\get_states;
    use render\render_region;

    private $groups = [];
    private $item_states = [];
    private $status_items = [];
    private $status_items_applied = false;

    // 定義 status bar groups（靜態設定）
    private function initial_groups()
    {
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
                    "items" => [
                        "sb_model" => [
                            "icon"     => "",
                            "default"  => "—",
                            "priority" => 20,
                        ],
                    ],
                ],
                "right" => [
                    "items" => [
                        "sb_conn" => [
                            "icon"     => "●",
                            "default"  => hkai__i18n()->get("status_bar.conn_offline"),
                            "class"    => "bad",
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

    // 外部貢獻：加入 status item（region_key 如 "main/left"／"project"；item 只接受 array 或 Closure）
    // 用法：sc_ai_status_bar()->status_bar()->add_status_item("workspace/left", function () { return sc_ai_diagnostics()->git()->get_git_status_item(); });
    // 語意：array＝靜態定義；Closure＝render 時求值——回 array＝config item（plugin 砌殼）；回字串＝完整 item HTML（provider 全權決定內容）
    public function add_status_item($region_key, $item)
    {
        // item 為 array → 直接收集（靜態定義）
        if (is_array($item)) {
            $this->status_items[$region_key][] = $item;

            return $this;
        }

        // item 為 Closure → 收集（render 時求值）
        if ($item instanceof \Closure) {
            $this->status_items[$region_key][] = $item;

            return $this;
        }

        // 型別不符（只接受 array／Closure）→ 拒絕
        return $this;
    }

    // 套用外部貢獻（add_status_item——region_key "group"／"group/sub"）
    private function apply_status_items()
    {
        // 已套用過 → 不重複（三欄 render_region 共用同一 instance）
        if ($this->status_items_applied === true) {
            return;
        }
        $this->status_items_applied = true;
        
        // 逐 region 套用貢獻
        foreach ($this->status_items as $region_key => $region_items) {
            $region_parts = explode("/", $region_key);
            $group_id     = $region_parts[0];
            $sub_id       = $region_parts[1] ?? "";
            
            // 逐貢獻項掛載（Closure 於此刻求值）
            foreach ($region_items as $region_item) {
                $this->attach_status_item($group_id, $sub_id, $region_item);
            }
        }
    }

    // 掛載單一貢獻項（array 直接用；Closure 此刻求值）
    private function attach_status_item($group_id, $sub_id, $region_item)
    {
        $item_config = $this->resolve_status_item($region_item);

        // 解析結果為字串 → 自由 HTML 內容（provider 全權決定 item 內容——只決定擺去邊）
        if (is_string($item_config)) {
            $this->attach_html_item($group_id, $sub_id, $item_config);

            return;
        }
        
        // 解析結果無 id → 無法掛載
        if (!isset($item_config["id"])) {
            return;
        }
        
        $item_id = $item_config["id"];
        
        // region 的 group 不存在 → 不掛
        if (!isset($this->groups[$group_id])) {
            return;
        }
        
        // group 分 subs（如 main/left）→ 掛到指定 sub
        if ($sub_id !== "") {
            // sub 不存在 → 不掛
            if (!isset($this->groups[$group_id]["subs"][$sub_id]["items"])) {
                return;
            }
            
            $this->groups[$group_id]["subs"][$sub_id]["items"][$item_id] = $item_config;
        }
        // group 直接有 items（如 project、chat）→ 掛到 group
        else {
            // group 無 items 容器 → 不掛
            if (!isset($this->groups[$group_id]["items"])) {
                return;
            }
            
            $this->groups[$group_id]["items"][$item_id] = $item_config;
        }
        
        // 同步當前狀態（text／class／hidden 取貢獻項當下值）
        $this->item_states[$item_id] = [
            "text"   => $item_config["text"] ?? "",
            "class"  => $item_config["class"] ?? "",
            "hidden" => $item_config["hidden"] ?? false,
        ];
    }

    // 掛載自由 HTML 貢獻（字串＝完整 item HTML——provider 自帶 class／data-click 契約）
    private function attach_html_item($group_id, $sub_id, $item_html)
    {
        // 空字串 → 不掛（provider 當下不適用）
        if ($item_html === "") {
            return;
        }

        // region 的 group 不存在 → 不掛
        if (!isset($this->groups[$group_id])) {
            return;
        }

        // group 分 subs（如 workspace/left）→ 掛到指定 sub 的自由內容列
        if ($sub_id !== "") {
            // sub 不存在 → 不掛
            if (!isset($this->groups[$group_id]["subs"][$sub_id])) {
                return;
            }

            $this->groups[$group_id]["subs"][$sub_id]["html_items"][] = $item_html;

            return;
        }

        $this->groups[$group_id]["html_items"][] = $item_html;
    }

    // 解析貢獻項（Closure → 此刻求值；array → 原樣；字串 → 自由 HTML 原樣）
    private function resolve_status_item($region_item)
    {
        // Closure → 求值（貢獻項於 render 時才產生資料）
        if ($region_item instanceof \Closure) {
            $resolved_item = $region_item();
            
            // 求值結果為字串 → 自由 HTML 內容原樣
            if (is_string($resolved_item)) {
                return $resolved_item;
            }

            // 求值結果非 array → 視為無效
            if (!is_array($resolved_item)) {
                return [];
            }
            
            return $resolved_item;
        }
        
        return $region_item;
    }

    // 渲染單一 item（狀態色＋可點擊契約＋隱藏）——div:flex 容器（icon ＋ 文字槽 sc-text）
    private function render_item($item_id, $item_config)
    {
        $state = $this->item_states[$item_id] ?? [];
        
        $icon    = $item_config["icon"] ?? "";
        $text    = $state["text"] ?? "";
        $class   = $state["class"] ?? "";
        $hidden  = $state["hidden"] ?? false;
        
        // class 組裝（base ＋ 狀態色 ＋ 可點擊）
        $class_parts = ["sc_statusbar__item"];
        if ($class !== "") {
            $class_parts[] = $class;
        }
        if (isset($item_config["click"])) {
            $class_parts[] = "sc_statusbar__item--clickable";
        }
        
        $html = "<div class='" . implode(" ", $class_parts) . " sc_statusbar__item--" . str_replace("sb_", "", $item_id) . "'";
        $html .= $this->render_click_attrs($item_config);
        
        if ($hidden) {
            $html .= " style='display:none'";
        }
        
        $html .= ">";
        
        if ($icon !== "") {
            $html .= $icon . " ";
        }
        
        $html .= "<span class='sc-text'>" . htmlspecialchars($text, ENT_QUOTES) . "</span>";
        $html .= "</div>";
        
        return $html;
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

        $region_html = $this->render_region($region_key);

        // 情境還原（交還請求自身嘅 URL 情境）
        $_SERVER["REQUEST_URI"] = $previous_request_uri;

        return $region_html;
    }

    // 渲染掛載槽（外部模組注入內容用；契約＝class sc_statusbar__indicators ＋ data-region）
    private function render_slot($slot_config)
    {
        $slot_region = $slot_config["region"];
        
        $slot_attr = [];
        $slot_attr[] = "class='sc_statusbar__indicators'";
        $slot_attr[] = "data-region='{$slot_region}'";
        
        $slot_attr = implode(" ", $slot_attr);
        
        $html = "<div {$slot_attr}></div>";
        return $html;
    }

    // items 依 priority 排序（小者在前；無 priority 視為 0）
    private function sort_items_by_priority($items)
    {
        uasort($items, function ($item_config_a, $item_config_b) {
            $priority_a = $item_config_a["priority"] ?? 0;
            $priority_b = $item_config_b["priority"] ?? 0;
            
            return $priority_a - $priority_b;
        });
        
        return $items;
    }

    // 渲染可點擊契約（data-click／data-click-tab；無 click 設定回空字串＝不可點）
    private function render_click_attrs($item_config)
    {
        // 無 click 設定 → 不可點
        if (!isset($item_config["click"])) {
            return "";
        }
        
        $click = $item_config["click"];
        
        $attrs = " data-click='" . htmlspecialchars($click["type"], ENT_QUOTES) . "'";
        
        // 有附帶分頁（如 settings 的 tab）→ 一併輸出
        if (isset($click["tab"])) {
            $attrs .= " data-click-tab='" . htmlspecialchars($click["tab"], ENT_QUOTES) . "'";
        }
        
        return $attrs;
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
