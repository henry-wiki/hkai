<?php

use sc\app\hkai\plugins\sc_ai_projects;

/**
 * 插件頂層單例（sc_ai_projects_Class——域入口：sc_ai_projects()->project()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\sc_ai_projects_Class
 */
function sc_ai_projects()
{
    return sc_ai_projects\sc_ai_projects_Class::getInstance();
}
