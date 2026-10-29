// jsmods/prompt/req/req_upload_attachment.js —— 圖片上傳（連線側——一 req 一檔）
// 職責：與伺服器連接部分——upload_attachment（File → base64 → 後端 upload_attachment → 派發）
// 結構同 callback 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _req_upload_attachment {
    constructor() {}

    // 上傳一張圖片（JSON base64——三態派發：null ⇒ E_NETWORK／ok === false ⇒ error／ok === true ⇒ success）
    async upload_attachment(file_item, project_slug, callbacks) {
        const thisObject = this;

        // before 程序（存在但唔強求執行）
        if (
            callbacks
            &&  callbacks.before_load
        ) {
            callbacks.before_load();
        }

        const data_base64 = await thisObject.read_file_base64(file_item);

        // 判定：讀檔失敗 → 派 error
        if (data_base64 === "") {
            callbacks.error({ ok: false, code: "E_FILE_READ", message: "file read failed" });

            return;
        }

        const payload = {};
        payload.project_slug = project_slug;
        payload.filename     = file_item.name;
        payload.mime_type    = file_item.type;
        payload.data_base64  = data_base64;

        const shared = registry().shared();
        const result = await shared.sc_request().post_json("/request/hkai/upload_attachment/", payload);

        // 判定：網路失敗 → E_NETWORK
        if (result == null) {
            callbacks.error({ ok: false, code: "E_NETWORK", message: "request failed" });

            return;
        }

        // 判定：後端拒絕 → error
        if (result.ok === false) {
            callbacks.error(result);

            return;
        }

        callbacks.success(result);
    }

    // 讀檔成 base64（去 data URL 前綴；失敗回空字串）
    read_file_base64(file_item) {
        return new Promise(function (resolve) {
            const reader = new FileReader();

            reader.onload = function () {
                const data_url = String(reader.result);
                const comma_index = data_url.indexOf(",");

                // 判定：冇逗號（格式錯）→ 空字串
                if (comma_index < 0) {
                    resolve("");

                    return;
                }

                resolve(data_url.slice(comma_index + 1));
            };

            reader.onerror = function () {
                resolve("");
            };

            reader.readAsDataURL(file_item);
        });
    }
}

export const upload_attachment = (function (instance) {
    return function () {

        return instance;
    };
})(new _req_upload_attachment());
