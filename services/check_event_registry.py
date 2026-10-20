#!/usr/bin/env python3
"""
check_event_registry.py

msg_log event／fsm_map 驗證工具（dev tool——不屬 aider_daemon 套件，禁止被 daemon import）。

功能：
    1. check（預設）：掃描 python / PHP 寫入端的事件 literal → 比對
       sc_config/hkai/fsm_map.json（事件目錄段）：
        - 未登記的 event literal（漂移）→ 報錯
        - registry 標記 renamed_from 的舊名仍被使用 → 報錯
        - registry 內 py/php writer 事件在程式碼找不到對應常數/寫法 → 報錯
    2. stats <logs_dir>：統計歸檔 msg_log_*.txt 實際出現的 event 分佈；
       出現但 registry 缺漏 → 報 registry 過期
    3. --gen-doc：由 fsm_map 生成 .docs/msg-log-event-catalog.md（人讀版）

用法：
    python check_event_registry.py                # check（預設）
    python check_event_registry.py stats <logs>   # 實測統計
    python check_event_registry.py --gen-doc      # 產生 catalog md
"""

import argparse
import json
import re
import sys
from pathlib import Path

# 路徑根（本檔住 <工作區根>/services/——工作區根 = parents[1]；app 目錄（hkai）＝ 工作區根/web/sc_contents/sc_app/hkai）
REPO_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = REPO_ROOT / "web" / "sc_contents" / "sc_app" / "hkai"
PYTHON_ROOT = Path(__file__).resolve().parent
FSM_MAP_PATH = REPO_ROOT / "web" / "sc_config" / "hkai" / "fsm_map.json"
PYTHON_DIR = PYTHON_ROOT / "aider_daemon"
PHP_DIR = PROJECT_ROOT / "plugins"
CATALOG_PATH = REPO_ROOT / ".docs" / "msg-log-event-catalog.md"

# 歷史 artifact 事件（非 registry、非 renamed——舊寫法遺留，歸檔歷史中允許存在）
LEGACY_EVENTS = {"WARN"}

# python append 呼叫內的事件 literal（可跨行）
PY_EVENT_PATTERN = re.compile(r'append_(?:info|success|error|warn)_log\(\s*"([A-Z_]+)"', re.DOTALL)
# stream_log kind literal
PY_KIND_PATTERN = re.compile(r'\{"kind": "([A-Z_]+)"\}')
# PHP event context literal
PHP_EVENT_PATTERN = re.compile(r'"event"\s*=>\s*"([A-Z_]+)"')


def load_fsm_map() -> dict:
    """載入 fsm_map（狀態機唯一契約——status 定義源）。"""
    return json.loads(FSM_MAP_PATH.read_text(encoding="utf-8"))


def iter_py_files() -> list:
    return [path for path in PYTHON_DIR.rglob("*.py") if "__pycache__" not in path.parts]


def iter_php_files() -> list:
    return [path for path in PHP_DIR.rglob("*.php")]


def find_py_literals() -> dict:
    # 回傳：{literal: [檔名相對路徑...]}——append 呼叫內的字串 literal
    found = {}
    for file_path in iter_py_files():
        content = file_path.read_text(encoding="utf-8")
        for literal in PY_EVENT_PATTERN.findall(content):
            found.setdefault(literal, []).append(str(file_path.relative_to(REPO_ROOT)))
        for literal in PY_KIND_PATTERN.findall(content):
            found.setdefault(literal, []).append(str(file_path.relative_to(REPO_ROOT)))
    return found


def find_php_literals() -> dict:
    found = {}
    for file_path in iter_php_files():
        content = file_path.read_text(encoding="utf-8")
        for literal in PHP_EVENT_PATTERN.findall(content):
            found.setdefault(literal, []).append(str(file_path.relative_to(PROJECT_ROOT)))
    return found


def find_py_constant_usages() -> set:
    # 回傳：程式碼中有引用的 EVENT_* 常數集合（驗證已常數化）
    used_constants = set()
    for file_path in iter_py_files():
        content = file_path.read_text(encoding="utf-8")
        for constant_name in re.findall(r"\b(EVENT_[A-Z_]+)\b", content):
            used_constants.add(constant_name)
    return used_constants


def check_states(fsm_map: dict, catalog_names: set) -> bool:
    """驗證 event_map 與 events 一致（雙向覆蓋、status 值合法——同一檔）。"""
    has_error = False
    status_block = fsm_map.get("status")
    if not status_block:
        print("ERROR: fsm_map 缺 status 區塊")
        return True
    event_map = fsm_map.get("event_map")
    if not event_map:
        print("ERROR: fsm_map 缺 event_map 區塊")
        return True

    valid_status = set(status_block["order"]) | set(status_block["terminal"])
    mapped_events = set()

    for status_name, status_events in event_map.items():
        if status_name not in valid_status:
            print(f"ERROR: event_map 出現未定義 status「{status_name}」")
            has_error = True
        for event_name in status_events:
            mapped_events.add(event_name)
            if event_name not in catalog_names:
                print(f"ERROR: event_map 引用未登記 event「{event_name}」（status={status_name}）")
                has_error = True

    for event_name in sorted(catalog_names - mapped_events):
        print(f"ERROR: event「{event_name}」未納入 event_map")
        has_error = True


    return has_error


def run_check() -> int:
    fsm_map = load_fsm_map()
    catalog_names = {event["name"] for event in fsm_map["events"]}
    renamed_from = {event["renamed_from"] for event in fsm_map["events"] if event.get("renamed_from")}
    py_writer_names = {
        event["name"] for event in fsm_map["events"]
        if event["writer"].startswith("py:")
    }
    php_writer_names = {
        event["name"] for event in fsm_map["events"]
        if event["writer"].startswith("php:")
    }

    has_error = False

    # 1. python literal 漂移檢查
    py_literals = find_py_literals()
    for literal, usages in sorted(py_literals.items()):
        if literal not in catalog_names:
            print(f"ERROR: 未登記 event literal「{literal}」→ {usages}")
            has_error = True
        if literal in renamed_from:
            print(f"ERROR: 使用已改名舊 event「{literal}」→ {usages}（fsm_map renamed_from）")
            has_error = True

    # 2. PHP literal 漂移檢查
    php_literals = find_php_literals()
    for literal, usages in sorted(php_literals.items()):
        if literal not in catalog_names:
            print(f"ERROR: 未登記 PHP event「{literal}」→ {usages}")
            has_error = True
        if literal in renamed_from:
            print(f"ERROR: PHP 使用已改名舊 event「{literal}」→ {usages}")
            has_error = True

    # 3. registry 內 py writer 事件必須在程式碼有常數引用（常數化驗證）
    py_text = "".join(path.read_text(encoding="utf-8") for path in iter_py_files())
    for name in sorted(py_writer_names):
        constant_name = "EVENT_" + name
        if constant_name not in py_text:
            print(f"ERROR: py-writer event「{name}」找不到常數引用「{constant_name}」")
            has_error = True

    # 4. event_map／status 一致性（同一檔）
    if check_states(fsm_map, catalog_names):
        has_error = True

    # 4. registry 內 php writer 事件必須在 PHP 有寫法
    php_text = "".join(path.read_text(encoding="utf-8") for path in iter_php_files())
    for name in sorted(php_writer_names):
        if name not in php_text:
            print(f"WARN: php-writer event「{name}」在 PHP 程式碼找不到寫法（可能未實作，如 CANCELLED）")

    if has_error:
        print("RESULT: FAIL")
        return 1
    print(f"RESULT: OK（py literal {len(py_literals)} 筆、php literal {len(php_literals)} 筆全數登記）")
    return 0


def run_stats(logs_dir: str) -> int:
    fsm_map = load_fsm_map()
    catalog_names = {event["name"] for event in fsm_map["events"]}
    renamed_legacy = {event["renamed_from"] for event in fsm_map["events"] if event.get("renamed_from")}
    logs_path = Path(logs_dir)
    event_counts = {}
    has_error = False

    # 掃描 msg_log_*.txt（JSONL——每行解析取 event 欄位）
    for log_file in logs_path.glob("msg_log_*.txt"):
        for line in log_file.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line == "":
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError:
                continue
            event_name = row.get("event")
            if not event_name:
                continue
            event_counts[event_name] = event_counts.get(event_name, 0) + 1

    # 實測出現但 registry 缺漏 → 分類：舊名（歷史預期）／artifact（歷史預期）／真漂移（registry 過期）
    for event_name in sorted(event_counts):
        marker = ""
        if event_name not in catalog_names:
            if event_name in renamed_legacy or event_name in LEGACY_EVENTS:
                marker = "  <-- 歷史舊名（改名/舊寫法前歸檔——預期）"
            else:
                marker = "  <-- ERROR: catalog 缺漏"
                has_error = True
        print(f"  {event_name}: {event_counts[event_name]}{marker}")

    if has_error:
        print("RESULT: FAIL（event 目錄過期）")
        return 1
    print(f"RESULT: OK（共 {len(event_counts)} 種 event：新名全數在目錄，其餘為歷史舊名）")
    return 0


def gen_doc() -> int:
    fsm_map = load_fsm_map()
    output_lines = []
    output_lines.append("# msg-log Event Catalog（由 sc_config/hkai/fsm_map.json 生成——勿手改）")
    output_lines.append("")
    output_lines.append("> 生成指令：`python services/check_event_registry.py --gen-doc`")
    output_lines.append("> 權威來源：`web/sc_config/hkai/fsm_map.json`（SSOT——狀態機＋事件目錄）")
    output_lines.append("")
    output_lines.append("## 事件分域表")
    output_lines.append("")
    output_lines.append("| event | 域 | 寫者 | level | 頻率 | 契約 | 說明 |")
    output_lines.append("|---|---|---|---|---|---|---|")
    for event in fsm_map["events"]:
        contract_mark = "✅" if event["contract"] else ""
        renamed_note = f"（原 {event['renamed_from']}）" if event.get("renamed_from") else ""
        output_lines.append(
            f"| `{event['name']}` | {event['domain']} | {event['writer']} | "
            f"{event['level']} | {event['frequency']} | {contract_mark} | "
            f"{event['description']}{renamed_note} |"
        )
    output_lines.append("")
    output_lines.append("## 狀態機（單一詞彙：status）")
    output_lines.append("")
    output_lines.append(f"- 順序：{' → '.join(fsm_map['status']['order'])}")
    output_lines.append(f"- 終態：{'／'.join(fsm_map['status']['terminal'])}")
    output_lines.append("")
    output_lines.append("| status | 事件 |")
    output_lines.append("|---|---|---|")
    for status_name in fsm_map["status"]["order"] + fsm_map["status"]["terminal"]:
        status_events = fsm_map["event_map"].get(status_name, [])
        event_cells = "、".join(f"`{event_name}`" for event_name in status_events)
        output_lines.append(f"| `{status_name}` | {event_cells} |")
    output_lines.append("")
    output_lines.append("### 轉移表（transitions——FSM 核心）")
    output_lines.append("")
    output_lines.append("| from | event | to |")
    output_lines.append("|---|---|---|")
    for transition in fsm_map.get("transitions", []):
        output_lines.append(f"| `{transition['from']}` | `{transition['event']}` | `{transition['to']}` |")
    output_lines.append("")
    output_lines.append("| 邊界 | 說明 |")
    output_lines.append("|---|---|")
    for boundary_state, boundary_note in fsm_map["status"]["boundary"].items():
        output_lines.append(f"| `{boundary_state}` | {boundary_note} |")
    output_lines.append("")
    output_lines.append("## stream_log 結構")
    output_lines.append("")
    output_lines.append(fsm_map["stream_log"]["note"])
    output_lines.append("")
    output_lines.append("## 契約消費者")
    output_lines.append("")
    output_lines.append("- `sc_ai_chats_fsm.php`／`msg_state_machine.py`：狀態機＋事件目錄唯一契約＝`sc_config/hkai/fsm_map.json`（status／transitions／events／event_map）")
    output_lines.append("- `poll/log.php` 活動行顯示分組＝`event_to_display_state`：QUERY_ACCEPTED→initiating／SUBPROCESS_*、WAITING_FOR_RESPONSE→connecting／SUMMARY_STREAM_*→summarizing／CONNECTION_ESTABLISHED、REPLY_STREAM_STARTED→replying")
    output_lines.append("")
    output_lines.append("> 新增/改名 event 流程：改 `sc_config/hkai/fsm_map.json` → 改 `msg_events.py` 常數 → 改寫入端 → 跑 check 驗證")
    CATALOG_PATH.parent.mkdir(parents=True, exist_ok=True)
    CATALOG_PATH.write_text("\r\n".join(output_lines), encoding="utf-8")
    print(f"CATALOG: {CATALOG_PATH}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="msg_log event／fsm_map 驗證工具")
    parser.add_argument("mode", nargs="?", default="check", help="check | stats")
    parser.add_argument("--gen-doc", action="store_true", help="由 fsm_map 生成 catalog md")
    parser.add_argument("logs_dir", nargs="?", default="", help="stats 模式：歸檔目錄（messages/archive）")
    args = parser.parse_args()

    if args.gen_doc:
        return gen_doc()
    if args.mode == "stats":
        if not args.logs_dir:
            print("ERROR: stats 模式需要 logs 目錄參數")
            return 1
        return run_stats(args.logs_dir)
    return run_check()


if __name__ == "__main__":
    sys.exit(main())
