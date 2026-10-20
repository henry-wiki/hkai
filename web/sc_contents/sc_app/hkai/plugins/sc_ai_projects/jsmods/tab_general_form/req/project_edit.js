// jsmods/tab_general_form/req/project_edit.js —— 編輯專案（連線側——一 handler 一檔）
// 職責：與伺服器連接部分——project_edit（before_save → 後端 project_edit → 三態）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）


class _project_edit {
    constructor() {}

    // 編輯專案（與伺服器連接部分——只做連線）：before 程序 → 後端 project_edit → 三態派發
    async project_edit(form_data, callbacks) {
        // registry 取值（靜態——直回單例）
        const shared = registry().shared();

        // before 程序（經 before_save callback——存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_save
        ) {            callbacks.before_save();
        }

        const result = await shared.sc_request().post_json("/request/hkai/project_edit/", form_data);

        // 三態派發：null ⇒ E_NETWORK，ok === false ⇒ callbacks.error(result)，ok === true ⇒ callbacks.success(result)
        if (result == null) {
            if (
                callbacks
                &&  callbacks.error
            ) {                callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });
            }

            return;
        }

        if (result.ok === false) {
            if (
                callbacks
                &&  callbacks.error
            ) {                callbacks.error(result);
            }

            return;
        }

        if (result.ok === true) {
            if (
                callbacks
                &&  callbacks.success
            ) {                callbacks.success(result);
            }
        }
    }
}

export const project_edit = (function (instance) {
    return function () {
        return instance;
    };
})(new _project_edit());
