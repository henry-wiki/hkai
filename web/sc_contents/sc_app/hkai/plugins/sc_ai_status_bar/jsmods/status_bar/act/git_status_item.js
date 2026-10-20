// jsmods/callback/git_status_item.js —— Git 狀態結果套上 sb_git（callback 層——一檔一事）
// 職責：git_status_item（git_status 結果 → sb_git 文字／顏色／tooltip 組裝）
// 結構同 sc_ai_projects 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _git_status_item {
    constructor() {}

    // 套用 Git 狀態結果（文字／顏色／tooltip）
    async apply_git_status(result) {
        const thisObject = this;

        // 動態載入（callback——status item render）
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const status_item_render_instance = await status_bar_domain.act().status_item_render();

        // 動態載入（registry——用先載）
        const i18n = registry().sc_i18n();

        // 判定：請求失敗 → 佔位（tooltip 說明）
        if (!result) {
            status_item_render_instance.set_item("sb_git", "—");
            status_item_render_instance.set_item_class("sb_git", "");
            status_item_render_instance.set_item_tooltip("sb_git", i18n.get("git.load_failed"));
            return;
        }

        // 判定：後端回報失敗 → 佔位（tooltip 帶原因）
        if (result.ok !== true) {
            let error_text = result.error;
            if (!error_text) {
                error_text = i18n.get("git.load_failed");
            }
            status_item_render_instance.set_item("sb_git", "—");
            status_item_render_instance.set_item_class("sb_git", "");
            status_item_render_instance.set_item_tooltip("sb_git", error_text);
            return;
        }

        // 判定：非 git 儲存庫 → 顯示未初始化
        if (result.is_repo !== true) {
            status_item_render_instance.set_item("sb_git", i18n.get("git.not_initialized"));
            status_item_render_instance.set_item_class("sb_git", "");
            status_item_render_instance.set_item_tooltip("sb_git", i18n.get("git.not_initialized_tip"));
            return;
        }

        let branch_text = result.branch;

        // 判定：detached HEAD → 短 sha
        if (result.detached === true) {
            branch_text = "HEAD@" + String(result.head_sha).substring(0, 7);
        }

        // 判定：無分支名 → 佔位
        if (!branch_text) {
            branch_text = i18n.get("git.no_branch");
        }

        let changes = result.changes;
        if (!changes) {
            changes = {};
        }

        const change_total = thisObject.get_number(changes.total);
        const ahead_count = thisObject.get_number(result.ahead);
        const behind_count = thisObject.get_number(result.behind);

        let has_upstream = false;
        // 判定：有遠端名 → has_upstream
        if (result.upstream) {
            has_upstream = true;
        }

        // 已同步＝有遠端＋無未上傳＋無未下載（逐項翻旗——扁平計算）
        let is_synced = has_upstream;
        // 判定：有未上傳 → 唔算同步
        if (ahead_count > 0) {
            is_synced = false;
        }
        // 判定：有未下載 → 唔算同步
        if (behind_count > 0) {
            is_synced = false;
        }

        let bar_text = branch_text;
        let bar_class = "";

        // 判定：有未提交變更 → 附 ●真數量（千分位）＋warn 色
        if (change_total > 0) {
            bar_text = bar_text + " ●" + thisObject.format_number(change_total);
            bar_class = "warn";
        }

        // 判定：未上傳 → 附 ↑N
        if (ahead_count > 0) {
            bar_text = bar_text + " ↑" + thisObject.format_number(ahead_count);
            bar_class = "warn";
        }

        // 判定：未下載 → 附 ↓N
        if (behind_count > 0) {
            bar_text = bar_text + " ↓" + thisObject.format_number(behind_count);
            bar_class = "warn";
        }

        // 判定：完全同步 → 附 ✓
        if (is_synced === true) {
            bar_text = bar_text + " ✓";

            // 判定：乾淨且同步 → ok 綠（有改動維持 warn）
            if (change_total === 0) {
                bar_class = "ok";
            }
        }

        status_item_render_instance.set_item("sb_git", bar_text);
        status_item_render_instance.set_item_class("sb_git", bar_class);
        status_item_render_instance.set_item_tooltip("sb_git", thisObject.build_git_tooltip(result, branch_text, changes, change_total));
    }

    // 組 Git tooltip（逐行：分支／同步／檔案／行數／最後提交／遠端——純文字·\n 分隔）
    build_git_tooltip(result, branch_text, changes, change_total) {
        const thisObject = this;

        // 動態載入（registry——用先載）
        const i18n = registry().sc_i18n();

        const tooltip_parts = [];

        // 首行：⎇ 分支（有提交附短 sha；無提交附「暫無提交」）
        let branch_line = "⎇ " + branch_text;
        if (result.has_commit === true) {
            branch_line = branch_line + "｜#" + String(result.head_sha).substring(0, 7);
        } else {
            branch_line = branch_line + i18n.get("git.no_commits");
        }
        tooltip_parts.push(branch_line);

        // 判定：有遠端 → 出同步行（未上傳／未下載；兩者皆 0 → 已同步）
        if (result.upstream) {
            const ahead_count = thisObject.get_number(result.ahead);
            const behind_count = thisObject.get_number(result.behind);
            const sync_parts = [];

            // 判定：未上傳 → 加一項
            if (ahead_count > 0) {
                sync_parts.push(i18n.get("git.sync_upload") + " " + thisObject.format_number(ahead_count) + i18n.get("git.sync_unit"));
            }

            // 判定：未下載 → 加一項
            if (behind_count > 0) {
                sync_parts.push(i18n.get("git.sync_download") + " " + thisObject.format_number(behind_count) + i18n.get("git.sync_unit"));
            }

            // 判定：兩者皆 0 → 已同步
            if (sync_parts.length === 0) {
                sync_parts.push(i18n.get("git.sync_synced") + " ✓");
            }

            tooltip_parts.push(i18n.get("git.line_sync") + sync_parts.join("・"));
        }

        // 判定：有改動 → 出檔案行（只列非零類別；新增＝未追蹤＋已 add）
        if (change_total > 0) {
            const file_parts = [];
            const added_count = thisObject.get_number(changes.added) + thisObject.get_number(changes.untracked);
            const modified_count = thisObject.get_number(changes.modified);
            const deleted_count = thisObject.get_number(changes.deleted);
            const renamed_count = thisObject.get_number(changes.renamed);
            const conflict_count = thisObject.get_number(changes.conflicts);

            // 判定：有新增 → 加一項
            if (added_count > 0) {
                file_parts.push(i18n.get("git.file_added") + " " + thisObject.format_number(added_count));
            }

            // 判定：有修改 → 加一項
            if (modified_count > 0) {
                file_parts.push(i18n.get("git.file_modified") + " " + thisObject.format_number(modified_count));
            }

            // 判定：有刪除 → 加一項
            if (deleted_count > 0) {
                file_parts.push(i18n.get("git.file_deleted") + " " + thisObject.format_number(deleted_count));
            }

            // 判定：有改名 → 加一項
            if (renamed_count > 0) {
                file_parts.push(i18n.get("git.file_renamed") + " " + thisObject.format_number(renamed_count));
            }

            // 判定：有衝突 → 加一項
            if (conflict_count > 0) {
                file_parts.push(i18n.get("git.file_conflicts") + " " + thisObject.format_number(conflict_count));
            }

            tooltip_parts.push(i18n.get("git.line_files") + file_parts.join("・"));
        }

        // 判定：有行數資料 → 出行數行（加＋減＝0 唔出）
        if (result.diff) {
            const diff_additions = thisObject.get_number(result.diff.additions);
            const diff_deletions = thisObject.get_number(result.diff.deletions);
            const diff_size = diff_additions + diff_deletions;

            // 判定：有加減行數 → 出一行
            if (diff_size > 0) {
                tooltip_parts.push(i18n.get("git.line_diff") + "＋" + thisObject.format_number(diff_additions) + " −" + thisObject.format_number(diff_deletions));
            }
        }

        // 判定：有最後提交 → 出提交行（時間＝YYYY-MM-DD HH:MM）
        if (result.last_commit) {
            const commit_time = String(result.last_commit.committed_at).substring(0, 16).replace("T", " ");
            tooltip_parts.push(i18n.get("git.line_commit") + commit_time + "　" + result.last_commit.subject);
        }

        // 判定：有遠端 URL → 出遠端行
        if (result.remote_origin) {
            tooltip_parts.push(i18n.get("git.line_remote") + "origin　" + result.remote_origin);
        }

        return tooltip_parts.join("\n");
    }

    // 千分位數字（1568 →「1,568」；缺值／非數字 →「0」）
    format_number(number_value) {
        return this.get_number(number_value).toLocaleString("en-US");
    }

    // 取數值（缺值／非數字 → 0）
    get_number(number_value) {
        const number_result = Number(number_value);

        // 判定：NaN／0 → 0
        if (!number_result) {
            return 0;
        }

        return number_result;
    }
}

export const git_status_item = (function (instance) {
    return function () {

        return instance;
    };
})(new _git_status_item());
