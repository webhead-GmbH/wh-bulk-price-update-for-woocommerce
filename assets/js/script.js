jQuery(document).ready(function ($) {
    function adjast_sidebar() {
        $(".wh-settings-sidebar").css("width", $(".wh-nav-pills").width());
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

    function show_preview_price() {
        let price_value = parseFloat($("#price_value").val());
        let current_price = parseFloat($("#ex_current_price").attr("data-value"));
        let action_type = $("#action_type").val();
        let change_type = $("#change_type").val();
        let new_price = 0;

        $("#ex_price_value").html(price_value);

        if (action_type === 'fixed') {
            new_price = price_value;
        } else {
            if (change_type === 'fixed') {
                if (action_type === 'increase') {
                    $("#ex_price_value").html(format_price(price_value));
                    new_price = current_price + price_value;
                } else if (action_type === 'decrease') {
                    $("#ex_price_value").html(format_price(price_value));
                    new_price = current_price - price_value;
                } else if (action_type === 'multiply')
                    new_price = current_price * price_value;
                else if (action_type === 'divide' && price_value > 0)
                    new_price = current_price / price_value;

            } else if (change_type === 'percentage') {
                if (action_type === 'increase')
                    new_price = current_price + ((current_price * price_value) / 100);
                else if (action_type === 'decrease')
                    new_price = current_price - ((current_price * price_value) / 100);
                else if (action_type === 'multiply' && price_value > 0)
                    new_price = current_price * (price_value / 100);
                else if (action_type === 'divide' && price_value > 0)
                    new_price = current_price / (price_value / 100);
            }
        }

        $("#ex_result").html(format_price(new_price));
    }

    $("#price_value, #change_type, #action_type").on("change", function () {
        show_preview_price();

        if ($("#price_value").val() == 0)
            $("#price_value").addClass("is-invalid");
        else
            $("#price_value").removeClass("is-invalid");
    });

    $("#change_type").on("change", function () {
        if ($(this).val() === "percentage")
            $("#ex_change_type").removeClass("d-none");
        else
            $("#ex_change_type").addClass("d-none");
    });

    $("#action_type").on("change", function () {
        let ex_action_type = $("#ex_action_type");
        let ex_action_type_code = $("#ex_action_type code");

        if ($(this).val() === "fixed") {
            ex_action_type.addClass("d-none");
            $("#ex_price_value").addClass("d-none");
            $("#ex_change_type").addClass("d-none");
            $("#change_type").addClass("d-none");
            $("#fixed-price-help").removeClass("d-none");
            $("#ex_equal").html("=>");
        } else {
            ex_action_type.removeClass("d-none");
            $("#ex_price_value").removeClass("d-none");
            $("#fixed-price-help").addClass("d-none");
            $("#change_type").removeClass("d-none");
            $("#ex_equal").html("=");

            if ($("#change_type").val() === "percentage") {
                $("#ex_change_type").removeClass("d-none");
            }
        }

        if ($(this).val() === "increase")
            ex_action_type_code.html('+');
        else if ($(this).val() === "decrease")
            ex_action_type_code.html('-');
        else if ($(this).val() === "multiply")
            ex_action_type_code.html('*');
        else if ($(this).val() === "divide")
            ex_action_type_code.html('/');
    });
    //--------------------------------------------------------------------------------
    //Preview prices

    function do_change(is_preview = false) {
        if ($("#price_value").val() == 0) {
            $("#price_value").addClass("is-invalid")
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
    $("#v-pills-about-tab").on("click", function () {
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
    });
});