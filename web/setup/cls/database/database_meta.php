<?php

namespace sc\setup\database;

// == setup database meta：資料庫管理資料（單元——class；呼叫形 \sc\setup\setup()->database_meta()）
// == 職責：寫／讀 meta 表的管理 key（來源、安裝時間）——版本號唔放呢度（用 database_migrations 記錄）
// == 上游：database 格新建庫（db_ready）、舊庫匯入（importer）
// == 下游：meta 表（key/value）

class database_meta_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口）
     *
     * @return \sc\setup\database\database_meta_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 寫入管理資料（冪等 upsert；$origin＝default／import）
    public function write_install_meta($pdo_connection, $origin, $imported_from)
    {
        $meta_values = [];
        $meta_values["db_origin"]        = $origin;
        $meta_values["db_imported_from"] = $imported_from;
        $meta_values["db_installed_at"]  = date("Y-m-d H:i:s");

        foreach ($meta_values as $meta_key => $meta_value) {
            $this->upsert_meta_value($pdo_connection, $meta_key, $meta_value);
        }
    }

    // 有冇 meta 表（來源庫守衛用）
    public function has_meta_table($pdo_connection)
    {
        $table_names = $pdo_connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);

        return in_array("meta", $table_names, true);
    }

    // 單一 key 寫入（有就更新，冇就插入）
    private function upsert_meta_value($pdo_connection, $meta_key, $meta_value)
    {
        $select_statement = $pdo_connection->prepare("SELECT meta_id FROM meta WHERE meta_key = :meta_key");

        $select_statement->execute([":meta_key" => $meta_key]);

        $meta_id = $select_statement->fetchColumn();

        $now = date("Y-m-d H:i:s");

        // 判定：已存在 → 更新
        if ($meta_id !== false) {
            $update_statement = $pdo_connection->prepare(
                "UPDATE meta SET meta_value = :meta_value, meta_updated_at = :meta_updated_at WHERE meta_id = :meta_id"
            );

            $update_statement->execute([
                ":meta_value"      => $meta_value,
                ":meta_updated_at" => $now,
                ":meta_id"         => $meta_id,
            ]);

            return;
        }

        $insert_statement = $pdo_connection->prepare(
            "INSERT INTO meta (meta_key, meta_value, meta_created_at, meta_updated_at)"
            . " VALUES (:meta_key, :meta_value, :meta_created_at, :meta_updated_at)"
        );

        $insert_statement->execute([
            ":meta_key"        => $meta_key,
            ":meta_value"      => $meta_value,
            ":meta_created_at" => $now,
            ":meta_updated_at" => $now,
        ]);
    }
}
