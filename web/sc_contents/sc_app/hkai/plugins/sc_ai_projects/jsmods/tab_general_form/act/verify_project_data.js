// jsmods/tab_general_form/act/verify_project_data.js —— 驗證專案資料（action 層——一 action 一檔）
// 職責：verify_project_data（送全表單 → 套欄位錯誤）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _verify_project_data {
    constructor() {}

    // 驗證專案資料（送全表單到後端——一次過：欄位正確？後台接受？）
    // ＋套用結果：欄位錯誤／路徑提示（純檢查）
    // [ remark ] 表單進入 loading 模式：驗證期間表單顯示載入中狀態
    // 排程（debounce）交 bind/modal_form_init.js——此處只做驗證本體
    async verify_project_data(j_modal) {
        // 表單整包（serialize＝name='formdata[x]' 字串——後台收到再解）
        const request_data = {};
        request_data["serialize"] = j_modal.find("*").serialize();

        // 橋接駁（同域 req 橋——新方式）
        const tab_general_form_instance = await registry().sc_ai_projects().tab_general_form();
        const verify_project_data_instance = await tab_general_form_instance.req().verify_project_data();

        // 無論如何都送後台檢查（唯一機制——前端唔做本地判斷）
        // 連線側 verify_project_data_instance.verify_project_data（名跟端點：request action＝檔名＝handle＝connector 方法）
        await verify_project_data_instance.verify_project_data(request_data, {
            before_load: function () {},
            success: async function (result) {
                // registry 取值（靜態——直回單例）
                const shared = registry().shared();

                // [ NEW ] 表單離開 loading 模式
                j_modal.find(".modal__content").removeClass("form_loading");

                // 套用所有欄位錯誤（標準 errors{field:message} → 自動定位欄位）
                shared.sc_web_utils().apply_form_errors(j_modal, result.errors);
            },
            error: function () {
                j_modal.find(".modal__content").removeClass("form_loading");
            }
        });
    }
}

export const verify_project_data = (function (instance) {
    return function () {

        return instance;
    };
})(new _verify_project_data());
