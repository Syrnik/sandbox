(($) => {
    "use strict";

    class SandboxEnvironments {
        constructor(options) {
            options = options || {};
            this.l10n = Object.assign({
                name_required:  'Укажите название',
                confirm_delete: 'Удалить окружение?',
            }, options.l10n || {});
        }

        currentEnvId = null;

        init() {
            this.bindEvents();
        }

        bindEvents() {
            $(document).on("click", ".js-select-env", (e) => {
                e.preventDefault();
                const id = parseInt($(e.currentTarget).data("id"), 10);
                this.loadEnvironment(id);
                $(".js-select-env").parent().removeClass("selected");
                $(e.currentTarget).parent().addClass("selected");
            });

            $("#btn-add-env").on("click", () => this.newEnvironment());
            $("#btn-add-var").on("click", () => this.addVarRow("", ""));
            $("#btn-save-env").on("click", () => this.saveEnvironment());
            $("#btn-delete-env").on("click", () => this.deleteEnvironment());
            $("#btn-export-env").on("click", () => this.exportEnvironment());
        }

        loadEnvironment(id) {
            $.ajax({
                url: "?module=backend&action=environment",
                data: { id },
                cache: true,
                dataType: "json",
                success: ({ status, data: env }) => {
                    if (status !== "ok") return;
                    this.currentEnvId = env.id;
                    $("#env-id").val(env.id);
                    $("#env-name").val(env.name);
                    $("#env-shared").prop("checked", !!env.is_shared);
                    $("#vars-body").empty();
                    const vars = env.variables_parsed ?? {};
                    Object.entries(vars).forEach(([k, v]) => this.addVarRow(k, v));
                    this.showEditor();
                },
            });
        }

        newEnvironment() {
            this.currentEnvId = null;
            $("#env-id").val("");
            $("#env-name").val("").focus();
            $("#env-shared").prop("checked", false);
            $("#vars-body").empty();
            this.showEditor();
            $(".js-select-env").parent().removeClass("selected");
        }

        addVarRow(key = "", value = "") {
            const row = `
                <tr class="js-var-row">
                    <td><input type="text" class="js-var-key full-width" value="${$("<div>").text(key).html()}" placeholder="VARIABLE_NAME"></td>
                    <td><input type="text" class="js-var-value full-width" value="${$("<div>").text(value).html()}" placeholder="value"></td>
                    <td><button type="button" class="js-remove-var button red small">&#8722;</button></td>
                </tr>
            `;
            $("#vars-body").append(row);
            $(document).on("click", ".js-remove-var", function () {
                $(this).closest("tr").remove();
            });
        }

        saveEnvironment() {
            const name = $("#env-name").val().trim();
            if (!name) { alert(this.l10n.name_required); return; }

            const variables = {};
            $("#vars-body .js-var-row").each(function () {
                const key = $(this).find(".js-var-key").val().trim();
                const val = $(this).find(".js-var-value").val();
                if (key) variables[key] = val;
            });

            const data = {
                id:        this.currentEnvId ?? "",
                name,
                is_shared: $("#env-shared").is(":checked") ? 1 : 0,
                variables: JSON.stringify(variables),
            };

            $.ajax({
                url: "?module=backend&action=environmentSave",
                type: "POST",
                data,
                dataType: "json",
                success: ({ status, data: env }) => {
                    if (status !== "ok") return;
                    this.currentEnvId = env.id;
                    $("#env-id").val(env.id);
                    $.wa.notice("Окружение сохранено");
                    this.refreshEnvList();
                },
            });
        }

        deleteEnvironment() {
            if (!this.currentEnvId) return;
            if (!confirm(this.l10n.confirm_delete)) return;

            $.ajax({
                url: "?module=backend&action=environmentDelete",
                type: "POST",
                data: { id: this.currentEnvId },
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") location.reload();
                },
            });
        }

        exportEnvironment() {
            if (!this.currentEnvId) return;
            $.ajax({
                url: "?module=backend&action=export",
                data: { type: "environment", id: this.currentEnvId },
                cache: true,
                dataType: "json",
                success: ({ status, data }) => {
                    if (status !== "ok") return;
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = `sandbox_env_${this.currentEnvId}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                },
            });
        }

        refreshEnvList() {
            $.ajax({
                url: "?module=backend&action=environments",
                cache: true,
                dataType: "json",
                success: ({ status, data: envs }) => {
                    if (status !== "ok") return;
                    const $list = $("#env-list");
                    $list.empty();
                    envs.forEach(env => {
                        const shared = env.is_shared ? ' <span class="hint small">&#127760;</span>' : "";
                        $list.append(`<li><a href="#" class="js-select-env" data-id="${env.id}">${$("<div>").text(env.name).html()}${shared}</a></li>`);
                    });
                    // Mark current
                    $list.find(`[data-id="${this.currentEnvId}"]`).parent().addClass("selected");
                },
            });
        }

        showEditor() {
            $("#env-editor").show();
            $("#env-empty").hide();
        }
    }

    window.SandboxEnvironments = SandboxEnvironments;

})(jQuery);
