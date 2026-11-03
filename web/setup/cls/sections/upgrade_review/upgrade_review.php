<?php

namespace sc\setup\sections;

// == HKAI setup sections upgrade_review：升級覆核格單元（格本體——view 側＝upgrade_review.view.php）
// == 職責：搬遷模式專用——顯示來源資料夾／偵測版本／目標版本／備份提示（確認後才按 Install）
// == 上游：sections 域註冊表（只喺 source_mode=import 出現）；下游：database_migration_source（掃描）

require_once __DIR__ . "/upgrade_review.view.php";

class upgrade_review_Class
{
    use upgrade_review_view;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct() {}
}
