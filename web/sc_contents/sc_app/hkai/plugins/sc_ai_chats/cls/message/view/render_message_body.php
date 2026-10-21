<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message render_message_body：message 域動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-09-27 自 chat/view.php 分獨立檔；2026-09-27 歸 message 域（訊息渲染） ==

trait render_message_body
{
    
    // 訊息主區（sc_topbar＋訊息容器＋sc_composer＋主區 status bar）——由 index.php 呼叫；內容由 JS 動態載入
    public function render_message_body()
    {
        $outputLines   = [];
        $outputLines[] = "<div class='sc_workspace'>";
            // topnav（導覽鈕／路徑列／右上工具鈕；行為由 jsmods/sc_topnav/sc_topnav.js 綁定）
            $outputLines[] = "<div class='sc_topbar'>";
                // 對話欄收合掣（topbar 最左——永遠揾到；icon 兩態由 body[data-chats-collapsed] 純 CSS 閘；行為由 sc_sidebar.js 綁定）
                $outputLines[] = "<button class='sc_topbar__nav_btn' data-action='toggle_chats_sidebar' title='向右收起對話欄' aria-expanded='true'>";
                    $outputLines[] = "<svg class='sc_topbar__icon_close' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                        $outputLines[] = "<rect x='3.5' y='4' width='17' height='16' rx='2'/>";
                        $outputLines[] = "<path d='M15 4v16'/>";
                        $outputLines[] = "<path d='M12 9l3 3-3 3'/>";
                    $outputLines[] = "</svg>";
                    $outputLines[] = "<svg class='sc_topbar__icon_open' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.7' stroke-linecap='round' stroke-linejoin='round'>";
                        $outputLines[] = "<rect x='3.5' y='4' width='17' height='16' rx='2'/>";
                        $outputLines[] = "<path d='M15 4v16'/>";
                        $outputLines[] = "<path d='M15 9l-3 3 3 3'/>";
                    $outputLines[] = "</svg>";
                $outputLines[] = "</button>";
                $outputLines[] = "<button class='sc_topbar__nav_btn' data-action='nav_refresh' title='重新整理'>⟳</button>";
                // 專案路徑列（空殼——路徑由前端動態載入；未填＝placeholder 顯；bdi＝隔離路徑 LTR 內容——配合 CSS direction:rtl 令長路徑顯尾、開頭省略號）
                $outputLines[] = "<div class='sc_topbar__path' data-placeholder='Project path'>";
                    $outputLines[] = "<bdi></bdi>";
                    // 複製路徑掣（列右側——目標由按鈕聲明 data-copy-target；撳＝複製條列顯示嘅專案路徑）
                    $outputLines[] = "<button class='sc_topbar__path_copy' data-copy-target='.sc_topbar__path bdi' title='複製專案路徑'><i class='fa-regular fa-copy'></i></button>";
                $outputLines[] = "</div>";
                // 右上工具按鈕（margin-left:auto 推至最右——照 reference3 topnav（模型／成員／分享／通知／帳戶）——未接功能：自帶 data-cmd='coming_soon'（撳＝提示即將推出））
                $outputLines[] = "<div class='sc_topbar__actions'>";
                    // 模型顯示（MODEL 細標＋型號名——名由後續鏈填；空＝淨顯標籤）
                    $outputLines[] = "<button class='sc_topbar__model' data-cmd='coming_soon' title='目前模型與對話設定'><small>MODEL</small><bdi></bdi></button>";
                    // 協作成員（預留——成員 stack；現時＝你＋AI）
                    $outputLines[] = "<button class='sc_topbar__people' data-cmd='coming_soon' title='協作成員'>";
                        $outputLines[] = "<span class='sc_topbar__people_stack'>";
                            $outputLines[] = "<span>你</span>";
                            $outputLines[] = "<span>AI</span>";
                        $outputLines[] = "</span>";
                    $outputLines[] = "</button>";
                    // 分享（預留——未接功能）
                    $outputLines[] = "<button class='sc_topbar__share' data-cmd='coming_soon' title='分享'>";
                        $outputLines[] = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'>";
                            $outputLines[] = "<path d='M15 8.5 12 5l3-3.5M12 5h3.5A4.5 4.5 0 0 1 20 9.5v1'/>";
                            $outputLines[] = "<path d='M19.5 12.5v5A2.5 2.5 0 0 1 17 20H6.5A2.5 2.5 0 0 1 4 17.5v-7A2.5 2.5 0 0 1 6.5 8H9'/>";
                        $outputLines[] = "</svg>";
                        $outputLines[] = "<span>分享</span>";
                    $outputLines[] = "</button>";
                    // 通知（預留——未接功能；紅點＝未讀指示）
                    $outputLines[] = "<button class='sc_topbar__notify' data-cmd='coming_soon' title='通知'>";
                        $outputLines[] = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'>";
                            $outputLines[] = "<path d='M18 10a6 6 0 1 0-12 0c0 7-2.5 7-2.5 8.5h17C20.5 17 18 17 18 10ZM9.5 21h5'/>";
                        $outputLines[] = "</svg>";
                        $outputLines[] = "<i class='sc_topbar__notify_dot'></i>";
                    $outputLines[] = "</button>";
                    // 帳戶（預留——頭像＋選單；選單開合＝純 CSS：wrapper:focus-within（0 JS、點其他位自動收））
                    $outputLines[] = "<div class='sc_topbar__account_wrap'>";
                        $outputLines[] = "<button class='sc_topbar__account' data-cmd='coming_soon' title='帳戶選單' aria-haspopup='menu'>";
                            $outputLines[] = "<span class='sc_topbar__account_avatar'>你</span>";
                            $outputLines[] = "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'>";
                                $outputLines[] = "<path d='m6.5 9 5.5 5.5L17.5 9'/>";
                            $outputLines[] = "</svg>";
                        $outputLines[] = "</button>";
                        // 帳戶選單（預留——純設計骨架；項目未接任何功能）
                        $outputLines[] = "<div class='sc_topbar__account_menu' role='menu'>";
                            $outputLines[] = "<div class='sc_topbar__account_summary'>你<span>hkai · 本機</span></div>";
                            $outputLines[] = "<hr>";
                            $outputLines[] = "<button type='button' role='menuitem'>帳戶與個人設定</button>";
                            $outputLines[] = "<button type='button' role='menuitem'>切換工作區</button>";
                            $outputLines[] = "<button type='button' role='menuitem'>成員與帳單</button>";
                            $outputLines[] = "<hr>";
                            $outputLines[] = "<button type='button' class='danger' role='menuitem'>登出</button>";
                        $outputLines[] = "</div>";
                    $outputLines[] = "</div>";
                $outputLines[] = "</div>";
            $outputLines[] = "</div>";
            // 訊息容器（捲動層＋底部固定錨點（#sc_scroll_anchor——唯一來源；前端 insertBefore 靠佢）＋導航點 rail .sc_thread__scroll_rail（骨架喺後端——前端只建點／亮燈）＋回到最新按鈕——不含任何訊息；訊息由 JS 經 /request/hkai/message_list_fetch/ 動態載入）
            
            
            $outputLines[] = "<div class='sc_thread'>";
                // 「係唔係最底」初始聲明（開對話預設跟底——1；行起來之後只准 user 捲動／跳轉改）
                $outputLines[] = "<div class='sc_thread__viewport' data-chat_sid='' data-update_time='' data-message_count='0' data-has_more='0' data-load_status='ready' data-is_at_bottom='1' data-mermaid='idle'>";
                    // 未選對話空狀態（長駐窗口——未選顯示／有對話隱藏；前端只切 hidden 屬性）
                    $outputLines[] = sc_empty_state(hkai__i18n()->get("empty.start_chat"), true, "sc_thread__empty_state");
                    $outputLines[] = "<div id='sc_scroll_anchor'></div>";
                $outputLines[] = "</div>";
                // 導航點 rail（右側固定——骨架喺後端；前端只建點／亮燈）
                $outputLines[] = "<div class='sc_thread__scroll_rail'></div>";
                // 浮問題條（常駐；顯示條件／填內容／撳跳由 jsmods/message/obs/message_prompt_observer.js 主導；顯隱＝data-visible 純 CSS 閘）
                $outputLines[] = "<div class='sc_float_user_prompt' data-visible='0' data-message_id=''></div>";
                // 發送列 + chat mode + model information（prompt 區塊——浮層：絕對定位釘喺 .sc_thread 底；捲動層 padding-bottom 留位、訊息捲到佢後面）；外層長駐、內裏由前端動態載入（prompt_fetch 整包換入），入嗰刻空白
                $outputLines[] = "<div class='sc_composer' data-loaded='0'></div>";
            $outputLines[] = "</div>";
            // 主區底 status bar（sc_ai_status_bar plugin 產生——workspace region；嵌喺 .sc_workspace 內，唔係 fixed footer）
            $outputLines[] = sc_ai_status_bar()->status_bar()->render_region("workspace");
        $outputLines[] = "</div>";
        
        return implode("\r\n", $outputLines);
    }
    
}
