<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\init;

// == sc_ai_projects project get_initial_data：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_init.php 分遷入（one file one action） ==

trait get_initial_data
{
    public function get_initial_data($idx = null)
    {
        if (!@$this->is_initial) {
            $this->is_initial = 1;
            $this->initial();
        }
        
        if (!@$idx) {
            return $this->initial_data;
        } else {
            return $this->initial_data[$idx];
        }
    }
}
