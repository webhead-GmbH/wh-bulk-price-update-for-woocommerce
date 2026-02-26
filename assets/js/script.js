jQuery(document).ready(function ($) {
    const MAIN_TAB_QUERY_PARAM = 'tab';
    const MAIN_TAB_SELECTOR = '#v-pills-tab [data-bs-toggle="pill"]';

    function adjast_sidebar() {
        $(".wh-settings-sidebar").css("width", $(".wh-nav-pills").width());
    }

    function updateMainTabQuery(targetSelector) {
        if (!targetSelector || targetSelector.charAt(0) !== '#') {
            return;
        }

        const targetId = targetSelector.substring(1);
        const url = new URL(window.location.href);
        if (url.searchParams.get(MAIN_TAB_QUERY_PARAM) === targetId) {
            return;
        }

        url.searchParams.set(MAIN_TAB_QUERY_PARAM, targetId);
        window.history.replaceState({}, '', url.toString());
    }

    function activateMainTabFromQuery() {
        const url = new URL(window.location.href);
        const tabId = (url.searchParams.get(MAIN_TAB_QUERY_PARAM) || '').trim();
        if (!tabId.length) {
            return;
        }

        const $tabButton = $(`${MAIN_TAB_SELECTOR}[data-bs-target="#${tabId}"]`).first();
        if (!$tabButton.length) {
            return;
        }

        if (window.bootstrap && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance($tabButton.get(0)).show();
        } else {
            $tabButton.trigger('click');
        }
    }

    $('select.multiple').each(function () {
        $(this).attr("multiple", "multiple");
    });

    $(".wh-select2").filter(":not(.wc-product-search)").each(function () {
        $(this).val('');
        $(this).select2({
            placeholder: $(this).attr("aria-label") ?? wh_script_params.i18n_select_an_option,
            width: "100%",
        });
    });

    adjast_sidebar();

    $(window).on("resize", (function () {
        adjast_sidebar();
    }));

    $(document).on('wp-menu-state-set wp-collapse-menu', function (event, eventData) {
        adjast_sidebar();
    });

    $(".nav-link").on("click", function () {
        adjast_sidebar();
    });

    $(document).on('click', MAIN_TAB_SELECTOR, function () {
        const targetSelector = $(this).attr('data-bs-target');
        updateMainTabQuery(targetSelector);
        adjast_sidebar();
    });

    let header_offset_top = $(".wh-setting-header").offset().top;
    $(window).scroll((function () {
        let header = $(".wh-setting-header");
        $(window).scrollTop() >= header_offset_top ? header.addClass("fixed").css({
            width: $(".wh-nav-contents").width()
        }) : header.removeClass("fixed").css({
            width: "auto"
        });
    }
    ));

    $("#apply_to").on("change", function () {
        let apply_to = $(this).val();
        $(".specific_products_wrapper").each(function () {
            if (apply_to === "all")
                $(this).addClass("d-none");
            else
                $(this).removeClass("d-none");
        });
    });

    $("#has_exclude_products").on("change", function () {
        let exclude_products_wrapper = $("#exclude_products_wrapper");

        if ($(this).is(":checked"))
            exclude_products_wrapper.removeClass("d-none");
        else
            exclude_products_wrapper.addClass("d-none");
    });

    //--------------------------------------------------------------------------------
    //Example price
    function format_price(price) {
        return accounting.formatMoney(price, {
            symbol: wh_script_params.currency_format_symbol,
            decimal: wh_script_params.currency_format_decimal_sep,
            thousand: wh_script_params.currency_format_thousand_sep,
            precision: wh_script_params.currency_format_num_decimals,
            format: wh_script_params.currency_format
        });
    }

    function get_custom_placeholder_map() {
        const map = {};
        const placeholders = Array.isArray(wh_script_params.custom_expression_placeholders)
            ? wh_script_params.custom_expression_placeholders
            : [];

        placeholders.forEach(function (placeholder) {
            const token = String(placeholder.token || '').toLowerCase().trim();
            if (!token) {
                return;
            }

            map[token] = Number(placeholder.sample_value || 0);
        });

        return map;
    }

    function evaluate_custom_expression(expression, placeholder_values) {
        const raw_expression = String(expression || '').trim();
        if (!raw_expression) {
            return null;
        }

        const available_values = placeholder_values || {};
        let unknown_placeholder = false;

        const resolved_expression = raw_expression.replace(/\{([a-zA-Z0-9_]+)\}/g, function (full_match, token) {
            const normalized_token = String(token || '').toLowerCase().trim();
            if (!Object.prototype.hasOwnProperty.call(available_values, normalized_token)) {
                unknown_placeholder = true;
                return '0';
            }

            return String(available_values[normalized_token]).replace(',', '.');
        });

        if (unknown_placeholder) {
            return null;
        }

        const compact_expression = resolved_expression.replace(/\s+/g, '').replace(/,/g, '.');
        if (!compact_expression || /[a-zA-Z_{}]/.test(compact_expression)) {
            return null;
        }

        if (!/^[0-9+\-*/().]+$/.test(compact_expression)) {
            return null;
        }

        try {
            const result = Function('"use strict"; return (' + compact_expression + ');')();
            if (typeof result !== 'number' || !Number.isFinite(result)) {
                return null;
            }

            return result;
        } catch (error) {
            return null;
        }
    }

    function get_price_value_state() {
        const raw_value = String($("#price_value").val() || '').trim();
        const action_type = $("#action_type").val();

        if (action_type === 'custom') {
            const preview_values = get_custom_placeholder_map();
            preview_values.regular_price = parseFloat($("#ex_current_price").attr("data-value")) || 0;
            const expression_result = evaluate_custom_expression(raw_value, preview_values);

            return {
                raw: raw_value,
                is_custom: true,
                expression_result: expression_result,
                is_valid: expression_result !== null
            };
        }

        const numeric_value = parseFloat(raw_value.replace(',', '.'));

        return {
            raw: raw_value,
            is_custom: false,
            numeric_value: numeric_value,
            is_valid: !Number.isNaN(numeric_value) && numeric_value > 0
        };
    }

    function update_action_type_ui() {
        const action_type = $("#action_type").val();
        const ex_action_type = $("#ex_action_type");
        const ex_action_type_code = $("#ex_action_type code");
        const price_input = $("#price_value");

        if (action_type === "custom") {
            price_input
                .attr("type", "text")
                .attr("inputmode", "text")
                .removeAttr("min")
                .removeAttr("step");

            ex_action_type.addClass("d-none");
            $("#ex_change_type").addClass("d-none");
            $("#change_type").addClass("d-none");
            $("#fixed-price-help").addClass("d-none");
            $("#wh-custom-expression-help").removeClass("d-none");
            $("#ex_equal").html("=>");
            $("#ex_price_value").removeClass("d-none");
            price_input.attr("placeholder", wh_script_params.i18n_price_value_placeholder_custom || "e.g. {regular_price} * 0.8");
            if (String(price_input.val() || '').trim() === '0') {
                price_input.val('');
            }
            return;
        }

        $("#wh-custom-expression-help").addClass("d-none");
        price_input
            .attr("type", "number")
            .attr("inputmode", "decimal")
            .attr("min", "0")
            .attr("step", "any")
            .attr("placeholder", wh_script_params.i18n_price_value_placeholder_numeric || "e.g. 10");

        if (action_type === "fixed") {
            ex_action_type.addClass("d-none");
            $("#ex_price_value").addClass("d-none");
            $("#ex_change_type").addClass("d-none");
            $("#change_type").addClass("d-none");
            $("#fixed-price-help").removeClass("d-none");
            $("#ex_equal").html("=>");
            return;
        }

        ex_action_type.removeClass("d-none");
        $("#ex_price_value").removeClass("d-none");
        $("#fixed-price-help").addClass("d-none");
        $("#change_type").removeClass("d-none");
        $("#ex_equal").html("=");

        if ($("#change_type").val() === "percentage") {
            $("#ex_change_type").removeClass("d-none");
        }

        if (action_type === "increase")
            ex_action_type_code.html('+');
        else if (action_type === "decrease")
            ex_action_type_code.html('-');
        else if (action_type === "multiply")
            ex_action_type_code.html('*');
        else if (action_type === "divide")
            ex_action_type_code.html('/');
    }

    function validate_price_value_input() {
        const state = get_price_value_state();

        if (state.is_valid) {
            $("#price_value").removeClass("is-invalid");
        } else {
            $("#price_value").addClass("is-invalid");
        }

        return state.is_valid;
    }

    function show_preview_price() {
        const price_state = get_price_value_state();
        let current_price = parseFloat($("#ex_current_price").attr("data-value"));
        let action_type = $("#action_type").val();
        let change_type = $("#change_type").val();
        let new_price = 0;

        if (!price_state.is_valid) {
            $("#ex_result").html(format_price(0));
            if (action_type === 'custom') {
                $("#ex_price_value").text(price_state.raw || '{regular_price}');
            } else {
                $("#ex_price_value").text(price_state.raw || '0');
            }
            return;
        }

        if (action_type === 'custom') {
            $("#ex_price_value").html("<code>" + $("<div>").text(price_state.raw).html() + "</code>");
            $("#ex_result").html(format_price(price_state.expression_result));
            return;
        }

        $("#ex_price_value").html(price_state.numeric_value);

        if (action_type === 'fixed') {
            new_price = price_state.numeric_value;
        } else {
            if (change_type === 'fixed') {
                if (action_type === 'increase') {
                    $("#ex_price_value").html(format_price(price_state.numeric_value));
                    new_price = current_price + price_state.numeric_value;
                } else if (action_type === 'decrease') {
                    $("#ex_price_value").html(format_price(price_state.numeric_value));
                    new_price = current_price - price_state.numeric_value;
                } else if (action_type === 'multiply')
                    new_price = current_price * price_state.numeric_value;
                else if (action_type === 'divide' && price_state.numeric_value > 0)
                    new_price = current_price / price_state.numeric_value;

            } else if (change_type === 'percentage') {
                if (action_type === 'increase')
                    new_price = current_price + ((current_price * price_state.numeric_value) / 100);
                else if (action_type === 'decrease')
                    new_price = current_price - ((current_price * price_state.numeric_value) / 100);
                else if (action_type === 'multiply' && price_state.numeric_value > 0)
                    new_price = current_price * (price_state.numeric_value / 100);
                else if (action_type === 'divide' && price_state.numeric_value > 0)
                    new_price = current_price / (price_state.numeric_value / 100);
            }
        }

        $("#ex_result").html(format_price(new_price));
    }

    $("#price_value, #change_type, #action_type").on("change input", function () {
        show_preview_price();
        validate_price_value_input();
    });

    $("#change_type").on("change", function () {
        if ($(this).val() === "percentage")
            $("#ex_change_type").removeClass("d-none");
        else
            $("#ex_change_type").addClass("d-none");
    });

    $("#action_type").on("change", function () {
        update_action_type_ui();
        show_preview_price();
        validate_price_value_input();
    });

    $("#wh-custom-placeholder-list").on("click", ".wh-custom-placeholder-item", function () {
        const input = $("#price_value").get(0);
        const placeholder = String($(this).data("placeholder") || "");

        if (!input || !placeholder) {
            return;
        }

        const start = Number(input.selectionStart ?? input.value.length);
        const end = Number(input.selectionEnd ?? input.value.length);
        const current = String(input.value || "").trim() === '0' ? "" : (input.value || "");
        if (current !== input.value) {
            input.value = "";
        }
        const before = current.slice(0, start);
        const after = current.slice(end);

        let insertion = placeholder;
        if (before && !/[\s+\-*/(]$/.test(before)) {
            insertion = " " + insertion;
        }

        if (after && !/^[\s+\-*/)]/.test(after)) {
            insertion += " ";
        }

        input.setRangeText(insertion, start, end, "end");
        input.focus();
        $(input).trigger("input");
    });
    //--------------------------------------------------------------------------------
    //Preview prices

    function do_change(is_preview = false) {
        if (!validate_price_value_input()) {
            return;
        }

        let _data = {
            action: 'webhead_bulk_price_update_update_product_price',
            security: wh_script_params.update_product_price_nonce,
            is_preview: is_preview ? 1 : 0
        }

        $("#v-pills-update-price").find("input, select").each(function () {
            if ($(this).attr("name") != undefined)
                _data[$(this).attr("name")] = $(this).val();

            if ($(this).attr("type") === "checkbox")
                _data[$(this).attr("name")] = $(this).is(":checked") ? 1 : 0;
        });

        $.ajax({
            url: wh_script_params.ajax_url,
            type: "post",
            data: _data,
            beforeSend: function () {
                $("#pp_spinner").addClass("is-active");
            },
            success: function (response) {
                $("#pp_spinner").removeClass("is-active");
                $("#preview-products-result").removeClass("d-none").html(response);
            }
        });
    }

    $("#preview-prices").on("click", function (e) {
        e.preventDefault();

        do_change(true)
    });

    $("#wh-do-update-price").on("click", function (e) {
        e.preventDefault();

        $('#wh-confirm-update-price').modal('hide');
        do_change()
    });

    //--------------------------------------------------------------------------------
    //Settings

    $("#wh-settings-form").on("submit", function (e) {
        e.preventDefault();

        let btn_submit = $(this).find('button[type="submit"]');
        let btn_icon = btn_submit.find(".btn-icon");
        let btn_text = btn_submit.find(".btn-text");
        let btn_spinner = btn_submit.find(".btn-spinner");
        let btn_status = btn_submit.find(".btn-status");
        const wh_toast = bootstrap.Toast.getOrCreateInstance($("#wh-toast"));

        let _data = new FormData(this);
        _data.append('security', wh_script_params.save_settings_nonce);
        _data.append('action', 'webhead_bulk_price_update_save_settings');

        $.ajax({
            url: wh_script_params.ajax_url,
            type: "post",
            contentType: false,
            processData: false,
            cache: false,
            data: _data,
            beforeSend: function () {
                btn_submit.addClass("opacity-75").attr("disabled", "disabled");
                btn_icon.addClass("d-none");
                btn_text.addClass("d-none");
                btn_spinner.removeClass("d-none");
                btn_status.removeClass("d-none");
            },
            success: function (response) {
                btn_submit.removeClass("opacity-75").removeAttr("disabled");
                btn_icon.removeClass("d-none");
                btn_text.removeClass("d-none");
                btn_spinner.addClass("d-none");
                btn_status.addClass("d-none");

                wh_toast.show();
            }
        });
    });

    //--------------------------------------------------------------------------------
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    //---------------------------------------------------------------------------------

    let posts_loaded = false;
    function load_about_tab_content() {
        if (posts_loaded)
            return;

        posts_loaded = true;
        $.ajax({
            url: wh_script_params.ajax_url,
            type: "post",
            cache: true,
            data: {
                action: 'webhead_bulk_price_update_get_blog_posts',
                security: wh_script_params.get_blog_posts_nonce,
                lang: $("html").attr('lang')
            },
            beforeSend: function () {
                $("#wh-blog-posts-wrapper .spinner").addClass("is-active");
            },
            success: function (response) {
                $("#wh-blog-posts").html(response);
                $("#wh-blog-posts-wrapper .spinner").removeClass("is-active");

                $.ajax({
                    url: wh_script_params.ajax_url,
                    type: "post",
                    cache: true,
                    data: {
                        action: 'webhead_bulk_price_update_get_plugins',
                        security: wh_script_params.get_plugins_nonce
                    },
                    beforeSend: function () {
                        $("#wh-plugins-wrapper .spinner").addClass("is-active");
                    },
                    success: function (response) {
                        $("#wh-plugins").html(response);
                        $("#wh-plugins-wrapper .spinner").removeClass("is-active");
                    }
                });
            }
        });
    }

    $("#v-pills-about-tab").on("click", function () {
        load_about_tab_content();
    });

    update_action_type_ui();
    show_preview_price();
    validate_price_value_input();

    activateMainTabFromQuery();
    if ((new URL(window.location.href)).searchParams.get(MAIN_TAB_QUERY_PARAM) === 'v-pills-about') {
        load_about_tab_content();
    }

});
