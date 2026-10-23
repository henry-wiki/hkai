#!/usr/bin/env python3
"""
sc_http.py — HTTP 相關工具（bridge 專用；只依賴標準庫，可獨立搬走）

- port_check_result    port 檢查結果（is_available／message）
- port_available()     試 bind 一次即放（唔會長期佔用）
"""

import socket
from typing import NamedTuple


class port_check_result(NamedTuple):
    """port 檢查結果（失敗時 message 帶原因；成功為空字串）。"""

    is_available: bool
    message: str


def _format_socket_error(socket_error: BaseException) -> str:
    """OSError → 標準 [Errno N] 原因；其餘型別＋內容。"""
    # 判定：OSError 帶 errno → 標準格式
    if isinstance(socket_error, OSError) and socket_error.errno is not None:
        return f"[Errno {socket_error.errno}] {socket_error.strerror}"

    return f"{type(socket_error).__name__}: {socket_error}"


def port_available(host: str, port: int) -> port_check_result:
    """試 bind 一次即放：可用 (True, "")；不可用 (False, 原因字串)。"""
    try:
        address_info = socket.getaddrinfo(host, int(port), type=socket.SOCK_STREAM)[0]
    except socket.gaierror as resolve_error:
        return port_check_result(False, _format_socket_error(resolve_error))

    address_family = address_info[0]
    address_sockaddr = address_info[4]
    probe_socket = socket.socket(address_family, socket.SOCK_STREAM)

    try:
        probe_socket.bind(address_sockaddr)
        return port_check_result(True, "")
    except OSError as bind_error:
        return port_check_result(False, _format_socket_error(bind_error))
    finally:
        probe_socket.close()
