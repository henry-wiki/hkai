<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics;

// == sc_ai_projects tab_diagnostics：tab_diagnostics 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_diagnostics()） ==
// == 動作（trait——一檔一動作；2026-10-03 立（診斷 tab——檔案隱患掃描；首項＝路徑長度）） ==

class tab_diagnostics_Class
{
    use action\fetch_section;
    use filepath\get_diagnostics_headline;
    use filepath\render_file_path_result;
    use general\render_general_result;
    use git\render_git_config_result;
    use git\render_git_key_configs;
    use view\build_diagnostics_nav_button;
    use view\build_diagnostics_pane_footer;
    use view\build_diagnostics_section;
    use view\build_tab_diagnostics_body;
    use view\build_tab_diagnostics_foot;
    use view\build_tab_diagnostics_head;
    use view\render_diagnostics_result;

    private static $instance = null;

    private $initial_data = [];
    private $is_initial = 0;

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
    }

    // initial data 組裝（第一次呼叫才執行——diagnostics_sections：左側 tab section 清單）
    private function initial()
    {
        $diagnostics_sections = [];
        $diagnostics_sections["general"] = [
            "id"   => "general",
            "name" => hkai__i18n()->get("diagnostics.tab_general"),
        ];
        $diagnostics_sections["file_path"] = [
            "id"   => "file_path",
            "name" => hkai__i18n()->get("diagnostics.tab_path"),
        ];
        $diagnostics_sections["git"] = [
            "id"   => "git",
            "name" => hkai__i18n()->get("diagnostics.tab_git"),
        ];

        $initial_data = [];
        $initial_data["diagnostics_sections"] = $diagnostics_sections;

        $this->initial_data = $initial_data;
    }

    // 取得 initial data（第一次呼叫才執行 initial；$idx null → 全部）
    public function get_initial_data($idx = null)
    {
        // 判定：未初始化 → 執行 initial（只行一次）
        if (!$this->is_initial) {
            $this->is_initial = 1;
            $this->initial();
        }

        // 判定：未指定索引 → 回全部
        if ($idx === null) {
            return $this->initial_data;
        }

        return $this->initial_data[$idx];
    }

    // 提示殼（新建語境——無專案列——空狀態卡）
    private function build_notice_pane($notice_text)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='diagnostics'>";
        $outputLines[] = "<div class='diagnostics'>";
        $outputLines[] = sc_empty_state($notice_text, false);
        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}
