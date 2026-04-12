(($) => {
    "use strict";

    class SandboxEnvironments {
        constructor(options) {
            options = options || {};
            this.l10n = Object.assign({
                name_required:    'Укажите название окружения',
                var_key_required: 'Укажите названия всех переменных',
                confirm_delete:   'Удалить окружение?',
                delete_btn:       'Удалить',
                cancel:           'Отмена',
            }, options.l10n || {});
        }

        currentEnvId = null;

        init() {
            this.bindEvents();
            this.adjustLayout();
            $(window).on('resize', () => this.adjustLayout());
        }

        adjustLayout() {
            const el = document.querySelector('.sandbox-env-layout');
            if (!el) return;
            el.style.height = (window.innerHeight - el.getBoundingClientRect().top) + 'px';
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

            $("#import-env-file").on("change", (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => this.importEnvironment(ev.target.result);
                reader.readAsText(file);
                $(e.target).val("");
            });
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
                    <td><input type="text" class="js-var-key full-width" value="${$("<div>").text(key).html()}" placeholder="VARIABLE_NAME" oninput="this.classList.remove('state-error')"></td>
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
            if (!name) {
                SandboxToast.show(this.l10n.name_required, 'error');
                $("#env-name").trigger('focus');
                return;
            }

            const variables = {};
            let hasEmptyKey = false;
            $("#vars-body .js-var-row").each(function () {
                const $key = $(this).find(".js-var-key");
                const key = $key.val().trim();
                const val = $(this).find(".js-var-value").val();
                if (!key) {
                    $key.addClass('state-error');
                    hasEmptyKey = true;
                } else {
                    $key.removeClass('state-error');
                    variables[key] = val;
                }
            });
            if (hasEmptyKey) {
                SandboxToast.show(this.l10n.var_key_required, 'error');
                return;
            }

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
                    SandboxToast.show('Окружение сохранено');
                    this.refreshEnvList();
                },
            });
        }

        deleteEnvironment() {
            if (!this.currentEnvId) return;

            $.wa.confirm({
                text:                 this.l10n.confirm_delete,
                success_button_title: this.l10n.delete_btn,
                success_button_class: 'red',
                cancel_button_title:  this.l10n.cancel,
                onSuccess: () => {
                    $.ajax({
                        url: "?module=backend&action=environmentDelete",
                        type: "POST",
                        data: { id: this.currentEnvId },
                        dataType: "json",
                        success: ({ status }) => {
                            if (status === "ok") location.reload();
                        },
                    });
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

        importEnvironment(jsonText) {
            $.ajax({
                url: "?module=backend&action=import",
                type: "POST",
                data: { data: jsonText },
                dataType: "json",
                success: ({ status, data: env }) => {
                    if (status !== "ok") return;
                    SandboxToast.show('Окружение импортировано');
                    this.refreshEnvList();
                    if (env?.id) this.loadEnvironment(env.id);
                },
            });
        }

        refreshEnvList() {
            $.ajax({
                url: "?module=backend&action=environmentList",
                cache: true,
                dataType: "json",
                success: ({ status, data: envs }) => {
                    if (status !== "ok") return;
                    const $list = $("#env-list");
                    $list.empty();
                    envs.forEach(env => {
                        const icon = env.is_shared
                            ? '<i class="fas fa-globe text-blue"></i>'
                            : '<i class="fas fa-lock text-yellow"></i>';
                        $list.append(`<li><a href="#" class="js-select-env" data-id="${env.id}">${icon} <span>${$("<div>").text(env.name).html()}</span></a></li>`);
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
