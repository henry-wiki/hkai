<?php
// == sc_app/dbviewer/externals/adminer/dbviewer_adminer_class.php：Adminer 定制 class（extends 原版 Adminer） ==
// == 載入時機：僅由 adminer_object() 掛鉤呼叫時 require（原版執行到掛鉤檢查處——class Adminer 已定義） ==
// == 唯一覆寫點：login()——原版拒絕空密碼（sqlite 本機檔案無帳密概念）；其餘外觀與行為全部源用原版 ==

class dbviewer_adminer_Class extends Adminer
{
    // 空密碼放行（原版 login() 拒絕空密碼——sqlite 本機檔案無帳密）
    public function login($login, $password)
    {
        return true;
    }
}
