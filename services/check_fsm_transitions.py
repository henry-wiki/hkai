#!/usr/bin/env python3
"""
check_fsm_transitions.py

FSM 轉移表驗證（對照 sc_config/hkai/fsm_map.json 的 transitions／status.terminal／event_to_status——純表檢查，不碰檔案系統）。

用法：python check_fsm_transitions.py
"""

import json
from pathlib import Path

FSM_MAP_PATH = Path(__file__).resolve().parents[1] / "web" / "sc_config" / "hkai" / "fsm_map.json"


def load_fsm_map() -> dict:
    """載入 fsm_map（FSM 契約——SSOT）。"""
    return json.loads(FSM_MAP_PATH.read_text(encoding="utf-8"))


def step(state: str, event: str, transitions: dict, terminal: list) -> str:
    """單步轉移（與 msg_state_machine 同邏輯）。"""
    # 判定：終態 → 不回跳（FSM 全域不變式）
    if state in terminal:
        return state

    # 精確 → 萬用
    next_state = transitions.get((state, event))
    # 判定：無精確 → 查萬用
    if next_state is None:
        next_state = transitions.get(("*", event))
    # 判定：未定義 → 不改
    if next_state is None:
        return state
    return next_state


# 9 個必測案例（events → 逐步期望狀態；起點＝無狀態（QUERY_ACCEPTED 由 * 入 queued）；RESPONSE_COMPLETED 後等 COMPLETED 先入終態）
CASES = [
    ("正常流程（含派工與 spawn）", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "SUMMARY_STREAM_OPENED", "CONNECTION_ESTABLISHED", "RESPONSE_COMPLETED", "COMPLETED"],
     ["queued", "queued", "connecting", "summarizing", "replying", "replying", "completed"]),
    ("無 summary", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "CONNECTION_ESTABLISHED", "RESPONSE_COMPLETED", "COMPLETED"],
     ["queued", "queued", "connecting", "replying", "replying", "completed"]),
    ("摘要超限（轉回 replying）", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "SUMMARY_STREAM_OPENED", "SUMMARY_TOKEN_LIMIT_REACHED", "CONNECTION_ESTABLISHED", "RESPONSE_COMPLETED", "COMPLETED"],
     ["queued", "queued", "connecting", "summarizing", "replying", "replying", "replying", "completed"]),
    ("取消競態（先取消勝出）", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "SUMMARY_STREAM_OPENED", "CONNECTION_ESTABLISHED", "CANCELLATION_REQUESTED", "RESPONSE_COMPLETED"],
     ["queued", "queued", "connecting", "summarizing", "replying", "cancelled", "cancelled"]),
    ("子程序啟動超時", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "SUBPROCESS_STARTUP_TIMEOUT"],
     ["queued", "queued", "connecting", "failed"]),
    ("重複接受（忽略）", ["QUERY_ACCEPTED", "QUERY_ACCEPTED", "QUERY_DISPATCHED"],
     ["queued", "queued", "queued"]),
    ("終端後遲到（忽略）", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "CONNECTION_ESTABLISHED", "RESPONSE_COMPLETED", "COMPLETED", "CANCELLATION_REQUESTED"],
     ["queued", "queued", "connecting", "replying", "replying", "completed", "completed"]),
    ("失敗終態", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "CONNECT_TIMEOUT"],
     ["queued", "queued", "connecting", "failed"]),
    ("守衛擋下終態 * 轉移", ["QUERY_ACCEPTED", "QUERY_DISPATCHED", "SUBPROCESS_SPAWNED", "CONNECTION_ESTABLISHED", "RESPONSE_COMPLETED", "COMPLETED", "FAILURE_RECORDED"],
     ["queued", "queued", "connecting", "replying", "replying", "completed", "completed"]),
]

def main() -> int:
    """逐案例逐步比對（不符即列出）。"""
    fsm_map = load_fsm_map()
    fsm_status = fsm_map["status"]
    terminal = fsm_status["terminal"]

    transitions = {}
    # 逐條登錄轉移（含守衛）
    for transition in fsm_map["transitions"]:
        transitions[(transition["from"], transition["event"])] = transition["to"]

    known_events = {transition["event"] for transition in fsm_map["transitions"]}

    failure_count = 0
    # 逐案例驗證
    for (case_name, events, expected_states) in CASES:
        state = ""
        # 逐事件推進
        for (event, expected_state) in zip(events, expected_states):
            state = step(state, event, transitions, terminal)
            # 判定：與期望不符 → 記錄
            if state != expected_state:
                print(f"FAIL [{case_name}] event={event} expected={expected_state} got={state}")
                failure_count += 1
                break

    # 事件登錄檢查（case 用到的 event 必須在 fsm_map transitions 出現）
    used_events = {event for (_, events, _) in CASES for event in events}
    # 逐事件比對登錄
    for event in sorted(used_events - known_events):
        print(f"FAIL [fsm_map] event={event} 未出現於 transitions")
        failure_count += 1

    # 詞彙檢查（event_to_status——單一詞彙：值一律叫 status）
    event_to_status = fsm_map.get("event_to_status", {})
    valid_status = set(fsm_status["order"]) | set(terminal)
    
    # 逐事件驗詞彙（event 已登錄＋status 合法）
    for (event, status) in event_to_status.items():
        # 判定：event_to_status 的 event 必須有轉移定義（由 transitions 歸納）
        if event not in known_events:
            print(f"FAIL [fsm_map] event_to_status 的 event={event} 未出現於 transitions")
            failure_count += 1
        # 判定：status 必須合法
        if status not in valid_status:
            print(f"FAIL [fsm_map] event_to_status[{event}]={status} 唔係合法 status")
            failure_count += 1
    
    print(f"CASES: {len(CASES)}; FAILURES: {failure_count}")
    print("RESULT: " + ("OK" if failure_count == 0 else "FAIL"))
    return 0 if failure_count == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
