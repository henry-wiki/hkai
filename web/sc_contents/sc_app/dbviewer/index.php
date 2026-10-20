<?php
// == sc_app/dbviewer/index.php：dbviewer app 執行檔（慣例檔——controller run() 載入語境後 require） ==
// == 語境載入順序：app>cls>*.func.php（bridging）→ __init__.php（設定注入）→ 本檔（執行） ==

// 執行進入流程（回傳 string／response 物件——走框架 prepare_response 四態）
return dbviewer__main()->run();
