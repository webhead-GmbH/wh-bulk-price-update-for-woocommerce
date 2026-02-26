jQuery(document).ready(function ($) {
    const $ruleModal = $('#wh-rule-form-modal');
    const $ruleForm = $('#wh-rule-form');
    const $stepElements = $('.wh-rule-step');
    const $stepIndicators = $('.wh-step-indicator');
    const $stepNavigation = $('.wh-step-navigation');
    const $stepActions = $('.wh-step-actions');
    const $btnPrevStep = $('.wh-step-prev');
    const $btnNextStep = $('.wh-step-next');
    const $btnPreviewStep = $('#wh-preview-rule-btn');
    const $btnSaveRule = $('#wh-save-rule-btn');
    const $previewSection = $('#wh-preview-rule-section');
    const $previewResult = $('#wh-preview-rule-result');
    const $logsModal = $('#wh-rule-logs-modal');
    const $logsContent = $('#wh-rule-logs-content');
    const totalSteps = $stepElements.length;
    let currentStep = 1;
    let suppressPreviewHideUntil = 0;
    let activeLogsRuleId = 0;
    let logsRequest = null;

    function hidePreviewResult(clearContent = false) {
        $previewSection.addClass('d-none');

        if (clearContent) {
            $previewResult.empty();
        }
    }

    function showPreviewResult(html) {
        $previewResult.html(html);
        initializeTooltips($previewResult);

        if (currentStep === totalSteps) {
            $previewSection.removeClass('d-none');
        }
    }

    function initializeTooltips($scope) {
        if (typeof bootstrap === 'undefined' || typeof bootstrap.Tooltip === 'undefined') {
            return;
        }

        const $tooltipScope = $scope && $scope.length ? $scope : $(document.body);
        $tooltipScope.find('[data-bs-toggle="tooltip"]').each(function () {
            const existingTooltip = bootstrap.Tooltip.getInstance(this);
            if (existingTooltip) {
                existingTooltip.dispose();
            }

            new bootstrap.Tooltip(this, {
                container: 'body',
                trigger: 'hover focus'
            });
        });
    }

    function disposeTooltip($element) {
        if (
            typeof bootstrap === 'undefined'
            || typeof bootstrap.Tooltip === 'undefined'
            || !$element
            || !$element.length
        ) {
            return;
        }

        const tooltipInstance = bootstrap.Tooltip.getInstance($element.get(0));
        if (tooltipInstance) {
            tooltipInstance.dispose();
        }
    }

    function setStep(step) {
        currentStep = Math.min(Math.max(step, 1), totalSteps);
        $stepElements.hide();
        $stepElements.filter(`[data-step="${currentStep}"]`).show();
        $stepIndicators.removeClass('active').attr('aria-selected', 'false');
        $stepIndicators.filter(`[data-step="${currentStep}"]`).addClass('active').attr('aria-selected', 'true');

        $btnPrevStep.prop('disabled', currentStep === 1);
        $btnNextStep.toggleClass('d-none', currentStep === totalSteps);
        $btnPreviewStep.toggleClass('d-none', currentStep !== totalSteps);
        if (currentStep !== totalSteps) {
            hidePreviewResult();
        } else if ($.trim($previewResult.html()) !== '') {
            $previewSection.removeClass('d-none');
        }

        if ($('#wh-rule-type').val() === 'margin_check') {
            validateMarginTiers();
        }
        updateSaveButtonState();
    }

    function resetSteps() {
        $stepNavigation.removeClass('d-none');
        $stepActions.removeClass('d-none');
        setStep(1);
        hidePreviewResult(true);
    }

    resetSteps();
    initializeTooltips($ruleModal);

    $stepIndicators.on('click', function () {
        const target = parseInt($(this).data('step'), 10);
        if (isNaN(target) || target === currentStep) {
            return;
        }

        if (target < currentStep) {
            setStep(target);
            return;
        }

        for (let step = currentStep; step < target; step++) {
            if (!validateStep(step, true)) {
                return;
            }
        }

        setStep(target);
    });

    $btnPrevStep.on('click', function () {
        if (currentStep > 1) {
            setStep(currentStep - 1);
        }
    });

    $btnNextStep.on('click', function () {
        if (!validateStep(currentStep, true)) {
            return;
        }

        if (currentStep < totalSteps) {
            setStep(currentStep + 1);
        }
    });

    // ==========================================================================
    // Scheduled Price Rules
    // ==========================================================================

    // Initialize Select2 in the rules form
    $('#wh-rule-tags, #wh-rule-brands').select2({
        dropdownParent: $('#wh-rule-form-modal')
    });

    // Initialize Select2 with AJAX for Attributes
    $('#wh-rule-attributes').select2({
        dropdownParent: $('#wh-rule-form-modal'),
        ajax: {
            url: wh_script_params.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    action: 'webhead_bulk_price_update_search_attributes',
                    security: wh_script_params.search_attributes_nonce
                };
            },
            processResults: function (data) {
                return {
                    results: data.data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2,
    });

    function getLogsLoadingMarkup() {
        const loadingText = wh_script_params.i18n_loading_logs || 'Loading logs...';
        return `
            <div class="text-center p-4">
                <span class="spinner-border text-success" role="status"></span>
                <p class="mt-2 text-muted">${loadingText}</p>
            </div>
        `;
    }

    function initLogsFilterSelect2() {
        const $taxonomyFilters = $logsContent.find('.wh-logs-filter-select');
        if ($taxonomyFilters.length) {
            $taxonomyFilters.each(function () {
                const $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            });

            $taxonomyFilters.select2({
                dropdownParent: $logsModal,
                width: '100%',
                placeholder: wh_script_params.i18n_select_an_option || 'Select an option'
            });
        }

        const $attributeFilter = $logsContent.find('.wh-logs-attributes-select');
        if ($attributeFilter.length) {
            if ($attributeFilter.hasClass('select2-hidden-accessible')) {
                $attributeFilter.select2('destroy');
            }

            $attributeFilter.select2({
                dropdownParent: $logsModal,
                width: '100%',
                placeholder: wh_script_params.i18n_select_an_option || 'Select an option',
                ajax: {
                    url: wh_script_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            action: 'webhead_bulk_price_update_search_attributes',
                            security: wh_script_params.search_attributes_nonce
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: (data && data.data && data.data.results) ? data.data.results : []
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2
            });
        }
    }

    function collectLogsFilters($form) {
        const payload = {};
        if (!$form || !$form.length) {
            return payload;
        }

        $form.serializeArray().forEach(function (field) {
            const name = String(field.name || '');
            const value = String(field.value || '').trim();

            if (!name.length) {
                return;
            }

            if (name.endsWith('[]')) {
                const key = name.slice(0, -2);
                if (!payload[key]) {
                    payload[key] = [];
                }

                if (value.length) {
                    payload[key].push(value);
                }

                return;
            }

            payload[name] = value;
        });

        return payload;
    }

    function getLogsRequestPayload(ruleId, page = 1) {
        const payload = {
            action: 'webhead_bulk_price_update_get_price_rule_logs',
            security: wh_script_params.get_price_rule_logs_nonce,
            rule_id: ruleId,
            page: page
        };

        const $filterForm = $logsContent.find('#wh-rule-logs-filter-form');
        const filters = collectLogsFilters($filterForm);
        return Object.assign(payload, filters);
    }

    function extractAjaxErrorMessage(xhr, fallbackMessage) {
        const fallback = fallbackMessage || 'Unexpected error.';
        if (!xhr || !xhr.responseText) {
            return fallback;
        }

        try {
            const parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.data && parsed.data.message) {
                return parsed.data.message;
            }
        } catch (err) {
            // Response is expected to be HTML in normal flow.
        }

        return fallback;
    }

    function loadRuleLogs(ruleId, page = 1, showLoading = true) {
        const normalizedRuleId = parseInt(ruleId, 10);
        if (isNaN(normalizedRuleId) || normalizedRuleId <= 0) {
            return;
        }

        activeLogsRuleId = normalizedRuleId;

        if (showLoading) {
            $logsContent.html(getLogsLoadingMarkup());
        }

        if (logsRequest && logsRequest.readyState !== 4) {
            logsRequest.abort();
        }

        logsRequest = $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: getLogsRequestPayload(normalizedRuleId, page),
            success: function (response) {
                $logsContent.html(response);
                initLogsFilterSelect2();
                initializeTooltips($logsContent);
            },
            error: function (xhr, statusText) {
                if (statusText === 'abort') {
                    return;
                }

                const message = extractAjaxErrorMessage(xhr, 'Failed to load logs.');
                $logsContent.html(`<div class="alert alert-danger mb-0">${message}</div>`);
            }
        });
    }

    function resetLogsFilterForm($form) {
        if (!$form || !$form.length) {
            return;
        }

        $form.find('input[type="date"]').val('');
        $form.find('select').each(function () {
            $(this).val(null).trigger('change');
        });
    }

    // Handle Rule Type selection to show/hide relevant settings pane
    $('#wh-rule-type').on('change', function () {
        const type = $(this).val();

        // Update help text
        $('.wh-rule-type-help span').addClass('d-none');
        $(`.wh-rule-type-help span[data-type="${type}"]`).removeClass('d-none');

        // Show relevant settings panel
        $('.wh-rule-settings-panel').addClass('d-none');

        if (type === 'margin_check') {
            $('#wh-margin-check-settings').removeClass('d-none');
            validateMarginTiers(); // Validate immediately on switch
        } else if (type === 'max_discount_pct') {
            $('#wh-max-discount-settings').removeClass('d-none');
        } else if (type === 'price_adjustment') {
            $('#wh-price-adjustment-settings').removeClass('d-none');
        }

        updateSaveButtonState();
    });

    // Handle Schedule Type changes
    $('#wh-schedule-type').on('change', function () {
        const type = $(this).val();

        if (type === 'custom') {
            // For custom, require at least one time
            if ($('.wh-schedule-hour-row').length === 0) {
                $('#wh-add-hour-btn').trigger('click');
            }
        }
    });

    // Margin Tiers - Add new row
    $('#wh-add-tier-btn').on('click', function () {
        const currency = wh_script_params.currency_format_symbol;
        const row = `
            <tr class="wh-margin-tier-row">
                <td style="cursor: move;" class="text-center align-middle" title="Drag to sort"><i class="fa-solid fa-grip-lines text-muted"></i></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control wh-tier-min-cog" step="0.01" min="0" value="0">
                        <span class="input-group-text">${currency}</span>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control wh-tier-max-cog" step="0.01" min="0" value="" placeholder="0 = unlimited">
                        <span class="input-group-text">${currency}</span>
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control wh-tier-margin" step="0.1" min="0" value="" placeholder="e.g. 60">
                        <span class="input-group-text">%</span>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger wh-remove-tier-btn">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#wh-margin-tiers-body').append(row);
        validateMarginTiers();
        updateSaveButtonState();
    });

    // Margin Tiers - Sortable
    if ($.fn.sortable) {
        $('#wh-margin-tiers-body').sortable({
            handle: '.fa-grip-lines',
            axis: 'y',
            cursor: 'grabbing',
            update: function () {
                validateMarginTiers();
            }
        });
    }

    // Margin Tiers - Remove row
    $(document).on('click', '.wh-remove-tier-btn', function () {
        $(this).closest('tr').remove();
        validateMarginTiers();
        updateSaveButtonState();
    });

    // Margin Tiers - Realtime Validation
    $(document).on('input', '.wh-tier-min-cog, .wh-tier-max-cog, .wh-tier-margin', function () {
        validateMarginTiers();
        updateSaveButtonState();
    });

    function parseTierNumber(rawValue) {
        const normalizedValue = String(rawValue ?? '').trim();
        if (normalizedValue === '') {
            return null;
        }

        const parsedValue = parseFloat(normalizedValue);
        return Number.isFinite(parsedValue) ? parsedValue : null;
    }

    function parseMarginTierRow($row) {
        const minRaw = String($row.find('.wh-tier-min-cog').val() ?? '').trim();
        const maxRaw = String($row.find('.wh-tier-max-cog').val() ?? '').trim();
        const marginRaw = String($row.find('.wh-tier-margin').val() ?? '').trim();

        const minCog = parseTierNumber(minRaw);
        const marginPct = parseTierNumber(marginRaw);
        const parsedMaxCog = parseTierNumber(maxRaw);
        const maxIsUnlimited = maxRaw === '' || parsedMaxCog === 0;
        const maxCog = maxIsUnlimited ? 0 : parsedMaxCog;

        return {
            min_cog: minCog,
            max_cog: maxCog,
            margin_pct: marginPct,
            max_is_unlimited: maxIsUnlimited,
            $element: $row
        };
    }

    function collectMarginTiersFromForm() {
        const tiers = [];

        $('.wh-margin-tier-row').each(function () {
            const parsedTier = parseMarginTierRow($(this));

            if (
                parsedTier.min_cog === null
                || parsedTier.max_cog === null
                || parsedTier.margin_pct === null
                || parsedTier.min_cog < 0
                || parsedTier.max_cog < 0
                || parsedTier.margin_pct < 0
            ) {
                return;
            }

            tiers.push({
                min_cog: parsedTier.min_cog,
                max_cog: parsedTier.max_is_unlimited ? 0 : parsedTier.max_cog,
                margin_pct: parsedTier.margin_pct
            });
        });

        return tiers;
    }

    function validateMarginTiers() {
        if ($('#wh-rule-type').val() !== 'margin_check') {
            $('#wh-margin-tiers-error').addClass('d-none');
            $('.wh-margin-tier-row').removeClass('table-danger');
            return true;
        }

        const tiers = [];
        let validationError = null;
        let hasError = false;

        $('.wh-margin-tier-row').removeClass('table-danger'); // Reset colors

        const $rows = $('.wh-margin-tier-row');
        $rows.each(function () {
            const parsedTier = parseMarginTierRow($(this));

            if (parsedTier.min_cog === null || parsedTier.min_cog < 0) {
                validationError = 'Invalid Tier: "COG From" is required and must be 0 or greater.';
                parsedTier.$element.addClass('table-danger');
                hasError = true;
                return false;
            }

            if (parsedTier.max_cog === null || parsedTier.max_cog < 0) {
                validationError = 'Invalid Tier: "COG To" must be 0 or greater.';
                parsedTier.$element.addClass('table-danger');
                hasError = true;
                return false;
            }

            if (parsedTier.margin_pct === null || parsedTier.margin_pct < 0) {
                validationError = 'Invalid Tier: "Margin %" is required and must be 0 or greater.';
                parsedTier.$element.addClass('table-danger');
                hasError = true;
                return false;
            }

            if (!parsedTier.max_is_unlimited && parsedTier.max_cog <= parsedTier.min_cog) {
                validationError = `Invalid Tier: "COG To" (${parsedTier.max_cog}) must be greater than "COG From" (${parsedTier.min_cog}).`;
                parsedTier.$element.addClass('table-danger');
                hasError = true;
                return false;
            }

            if (parsedTier.min_cog === 0 && parsedTier.max_is_unlimited && parsedTier.margin_pct === 0) {
                validationError = 'Invalid Tier: a 0 / 0 (unlimited) / 0 row is not allowed.';
                parsedTier.$element.addClass('table-danger');
                hasError = true;
                return false;
            }

            tiers.push(parsedTier);
        });

        if (hasError) {
            showMarginError(validationError);
            return false;
        }

        if (tiers.length === 0) {
            showMarginError('Please add at least one margin tier.');
            return false;
        }

        // Validation 2: Check for overlapping tiers (excluding unlimited 0)
        // Keep track of original visual positions so we can highlight the right row
        const sortedTiers = [...tiers].sort((a, b) => a.min_cog - b.min_cog);

        for (let i = 0; i < sortedTiers.length - 1; i++) {
            const current = sortedTiers[i];
            const next = sortedTiers[i + 1];

            if (current.max_cog === 0) {
                // If current goes to infinity, any following tier overlaps it.
                validationError = `Overlapping Tiers: A tier starting at ${current.min_cog} has no maximum, but there is another tier starting at ${next.min_cog}.`;
                current.$element.addClass('table-danger');
                next.$element.addClass('table-danger');
                hasError = true;
                break;
            }

            // If current max is greater than next min, there's an overlap
            // E.g. [100-200] and [150-300] overlap
            if (current.max_cog > next.min_cog) {
                validationError = `Overlapping Tiers: The tier ending at ${current.max_cog} overlaps with the tier starting at ${next.min_cog}.`;
                current.$element.addClass('table-danger');
                next.$element.addClass('table-danger');
                hasError = true;
                break;
            }
        }

        if (hasError) {
            showMarginError(validationError);
            return false;
        }

        // Valid
        $('#wh-margin-tiers-error').addClass('d-none').empty();
        return true;
    }

    function showMarginError(message) {
        $('#wh-margin-tiers-error').html('<i class="fa-solid fa-triangle-exclamation me-1"></i> ' + message).removeClass('d-none');
    }

    // Schedule Hours - Add new row
    $('#wh-add-hour-btn').on('click', function () {
        const row = `
            <div class="input-group mb-2 wh-schedule-hour-row">
                <input type="time" class="form-control wh-schedule-hour-input" value="12:00">
                <button type="button" class="btn btn-outline-danger wh-remove-hour-btn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        `;
        $('#wh-schedule-hours-list').append(row);
        updateSaveButtonState();
    });

    // Schedule Hours - Remove row
    $(document).on('click', '.wh-remove-hour-btn', function () {
        $(this).closest('.wh-schedule-hour-row').remove();
        updateSaveButtonState();
    });

    // Schedule Type Change Logic
    $('#wh-schedule-type').on('change', function () {
        const type = $(this).val();
        const $addBtn = $('#wh-add-hour-btn');
        const $label = $('#wh-schedule-hours-label');
        const $helpIcon = $label.find('.wh-label-help');
        const $customText = $('#wh-schedule-helper-text-custom');
        const $intervalText = $('#wh-schedule-helper-text-interval');
        const $hoursList = $('#wh-schedule-hours-list');

        if (type === 'custom') {
            $addBtn.removeClass('d-none');
            // Change label and tooltip text
            $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith('Specific Execution Times ');
            $helpIcon.attr('data-bs-original-title', 'Specify the exact times at which this rule should run each day.');

            $intervalText.addClass('d-none');
            $customText.removeClass('d-none');

            // Show remove buttons
            $hoursList.find('.wh-remove-hour-btn').removeClass('d-none');
        } else {
            $addBtn.addClass('d-none');
            // Change label and tooltip text
            $label.contents().filter(function () { return this.nodeType === 3; }).first().replaceWith('Start Time ');
            $helpIcon.attr('data-bs-original-title', 'Specify the time at which this rule should run.');

            $customText.addClass('d-none');
            $intervalText.removeClass('d-none');

            // Hide remove buttons and keep only the first time input
            $hoursList.find('.wh-remove-hour-btn').addClass('d-none');
            const $rows = $hoursList.find('.wh-schedule-hour-row');
            if ($rows.length > 1) {
                // Remove all except the first one
                $rows.not(':first').remove();
            }
        }

        updateSaveButtonState();
    });

    function clearFieldError($field) {
        if (!$field.length) {
            return;
        }

        $field.get(0).setCustomValidity('');
    }

    function showFieldError($field, message, step) {
        if (!$field.length) {
            return;
        }

        const field = $field.get(0);
        field.setCustomValidity(message);

        if (typeof step === 'number') {
            setStep(step);
        }

        field.reportValidity();
        field.focus();
    }

    function validateBasicStep(showFeedback) {
        const $nameField = $('#wh-rule-name');
        const nameValue = ($nameField.val() || '').trim();

        if (!nameValue.length) {
            if (showFeedback) {
                showFieldError($nameField, 'Rule name is required.', 1);
            }
            return false;
        }

        clearFieldError($nameField);
        return true;
    }

    function validateScheduleStep(showFeedback) {
        const scheduleType = $('#wh-schedule-type').val();
        const $hourInputs = $('.wh-schedule-hour-input');

        if (!$hourInputs.length) {
            if (showFeedback) {
                alert('Please add at least one schedule time.');
                setStep(2);
            }
            return false;
        }

        const $firstEmpty = $hourInputs.filter(function () {
            return !$(this).val();
        }).first();

        if (scheduleType === 'custom' && $firstEmpty.length) {
            if (showFeedback) {
                showFieldError($firstEmpty, 'Please fill in all custom execution times.', 2);
            }
            return false;
        }

        const hasAnyTime = $hourInputs.toArray().some((input) => {
            return Boolean($(input).val());
        });

        if (!hasAnyTime) {
            if (showFeedback) {
                showFieldError($hourInputs.first(), 'Please enter a start time.', 2);
            }
            return false;
        }

        $hourInputs.each(function () {
            clearFieldError($(this));
        });

        return true;
    }

    function validateRuleSettingsStep(showFeedback) {
        const ruleType = $('#wh-rule-type').val();

        if (ruleType === 'margin_check') {
            if (!validateMarginTiers()) {
                if (showFeedback) {
                    setStep(4);
                }
                return false;
            }
            return true;
        }

        if (ruleType === 'max_discount_pct') {
            const $discountInput = $('#wh-max-discount-pct');
            const discountValue = parseFloat($discountInput.val());

            if (!Number.isFinite(discountValue) || discountValue < 0 || discountValue > 100) {
                if (showFeedback) {
                    showFieldError($discountInput, 'Enter a value between 0 and 100.', 4);
                }
                return false;
            }

            clearFieldError($discountInput);
            return true;
        }

        if (ruleType === 'price_adjustment') {
            const actionType = $('#wh-adj-action-type').val();
            const $valueInput = $('#wh-adj-price-value');
            const priceValue = parseFloat($valueInput.val());

            if (!Number.isFinite(priceValue) || priceValue < 0) {
                if (showFeedback) {
                    showFieldError($valueInput, 'Enter a valid non-negative value.', 4);
                }
                return false;
            }

            if (actionType === 'divide' && priceValue === 0) {
                if (showFeedback) {
                    showFieldError($valueInput, 'Division value must be greater than 0.', 4);
                }
                return false;
            }

            clearFieldError($valueInput);
            return true;
        }

        return true;
    }

    function validateStep(step, showFeedback) {
        switch (step) {
            case 1:
                return validateBasicStep(showFeedback);
            case 2:
                return validateScheduleStep(showFeedback);
            case 3:
                return true;
            case 4:
                return validateRuleSettingsStep(showFeedback);
            default:
                return true;
        }
    }

    function validateAllSteps(showFeedback) {
        for (let step = 1; step <= totalSteps; step++) {
            if (!validateStep(step, showFeedback)) {
                return false;
            }
        }

        return true;
    }

    function updateSaveButtonState() {
        $btnSaveRule.prop('disabled', !validateAllSteps(false));
    }

    function normalizeIdArray(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        return values
            .map((value) => {
                if (value && typeof value === 'object') {
                    if (typeof value.id !== 'undefined') {
                        return String(value.id);
                    }
                    if (typeof value.value !== 'undefined') {
                        return String(value.value);
                    }
                    return '';
                }

                return String(value ?? '');
            })
            .map((value) => value.trim())
            .filter((value) => value !== '' && value !== '0');
    }

    function normalizeLabelMap(values) {
        const labelsById = {};

        if (!Array.isArray(values)) {
            return labelsById;
        }

        values.forEach((value) => {
            if (!value || typeof value !== 'object') {
                return;
            }

            const rawId = typeof value.id !== 'undefined' ? value.id : value.value;
            if (typeof rawId === 'undefined') {
                return;
            }

            const id = String(rawId).trim();
            if (!id.length) {
                return;
            }

            labelsById[id] = value.text || value.name || '';
        });

        return labelsById;
    }

    function setMultiSelectValues($select, values) {
        if (!$select.length) {
            return;
        }

        const normalizedValues = normalizeIdArray(values);
        $select.val(normalizedValues).trigger('change');
    }

    function findOptionByValue($select, value) {
        const normalizedValue = String(value);
        return $select.find('option').filter(function () {
            return String($(this).val()) === normalizedValue;
        }).first();
    }

    function upsertSelectedOption($select, id, text) {
        let $option = findOptionByValue($select, id);
        const optionText = (text || '').trim() || `#${id}`;

        if (!$option.length) {
            $option = $(new Option(optionText, id, true, true));
            $select.append($option);
        } else {
            $option.text(optionText);
            $option.prop('selected', true);
        }
    }

    function hydrateSelectLabelsByIds($select, ids, action, nonceKey) {
        if (!$select.length || !Array.isArray(ids) || !ids.length) {
            return;
        }

        const securityToken = wh_script_params[nonceKey];
        if (!securityToken) {
            return;
        }

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: action,
                security: securityToken,
                ids: ids
            },
            success: function (response) {
                if (!response || !response.success || !response.data || !Array.isArray(response.data.results)) {
                    return;
                }

                const selectedValues = normalizeIdArray($select.val() || []);

                response.data.results.forEach((result) => {
                    if (!result || typeof result.id === 'undefined') {
                        return;
                    }

                    const id = String(result.id).trim();
                    if (!id.length) {
                        return;
                    }

                    const isSelected = selectedValues.includes(id);
                    const optionText = (result.text || `#${id}`).trim();
                    const $existingOption = findOptionByValue($select, id);

                    if ($existingOption.length) {
                        $existingOption.remove();
                    }

                    $select.append(new Option(optionText, id, isSelected, isSelected));
                });

                $select.val(selectedValues).trigger('change');
            }
        });
    }

    function setAttributeValues(values) {
        const $select = $('#wh-rule-attributes');
        if (!$select.length) {
            return;
        }

        const normalizedValues = normalizeIdArray(values);
        const labelsById = normalizeLabelMap(values);

        $select.empty();

        if (!normalizedValues.length) {
            $select.val(null).trigger('change');
            return;
        }

        normalizedValues.forEach((id) => {
            upsertSelectedOption($select, id, labelsById[id]);
        });

        $select.trigger('change');
        hydrateSelectLabelsByIds($select, normalizedValues, 'webhead_bulk_price_update_search_attributes', 'search_attributes_nonce');
    }

    function setProductSearchValues($select, values) {
        if (!$select.length) {
            return;
        }

        const normalizedValues = normalizeIdArray(values);
        const labelsById = normalizeLabelMap(values);

        $select.empty();

        if (!normalizedValues.length) {
            $select.val(null).trigger('change');
            return;
        }

        normalizedValues.forEach((id) => {
            upsertSelectedOption($select, id, labelsById[id]);
        });

        $select.trigger('change');
        hydrateSelectLabelsByIds($select, normalizedValues, 'webhead_bulk_price_update_search_products', 'search_products_nonce');
    }

    function addProductToExcludeSelection(productId, productName) {
        const $excludeSelect = $('#wh-rule-exclude-products');
        if (!$excludeSelect.length) {
            return false;
        }

        const normalizedId = String(productId || '').trim();
        if (!normalizedId.length || normalizedId === '0') {
            return false;
        }

        upsertSelectedOption($excludeSelect, normalizedId, productName);

        const selectedValues = normalizeIdArray($excludeSelect.val() || []);
        if (!selectedValues.includes(normalizedId)) {
            selectedValues.push(normalizedId);
            $excludeSelect.val(selectedValues);
        }

        // Keep preview visible while Select2/app events settle after this action.
        suppressPreviewHideUntil = Date.now() + 1500;
        $excludeSelect.trigger('change');
        hydrateSelectLabelsByIds($excludeSelect, [normalizedId], 'webhead_bulk_price_update_search_products', 'search_products_nonce');
        return true;
    }

    // Open Add New Rule Modal
    $('#wh-add-rule-btn, .wh-add-rule-trigger').on('click', function () {
        $('#wh-rule-form-title').text('Add New Rule');
        $('#wh-rule-id').val('0');
        $ruleForm[0].reset();
        resetSteps();

        // Reset selections
        $('#wh-rule-categories, #wh-rule-tags, #wh-rule-brands').val(null).trigger('change');
        setAttributeValues([]);
        $('#wh-rule-include-products, #wh-rule-exclude-products').empty().trigger('change');

        // Reset dynamic elements
        $('#wh-schedule-hours-list').html(`
            <div class="input-group mb-2 wh-schedule-hour-row">
                <input type="time" class="form-control wh-schedule-hour-input" value="08:00">
                <button type="button" class="btn btn-outline-danger wh-remove-hour-btn">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        `);

        // Reset margin tiers
        $('#wh-margin-tiers-body').empty();
        $('#wh-add-tier-btn').trigger('click');

        $('#wh-rule-type').trigger('change');
        $('#wh-schedule-type').trigger('change');
        hidePreviewResult(true);
        updateSaveButtonState();

        const modal = new bootstrap.Modal(document.getElementById('wh-rule-form-modal'));
        modal.show();
    });

    // Open Edit Rule Modal
    $('.wh-edit-rule').on('click', function () {
        resetSteps();
        hidePreviewResult(true);
        const rule = JSON.parse($(this).attr('data-rule'));

        $('#wh-rule-form-title').text('Edit Rule: ' + rule.name);
        $('#wh-rule-id').val(rule.id);

        // Basic Info
        $('#wh-rule-name').val(rule.name);
        $('#wh-rule-type').val(rule.rule_type).trigger('change');
        $('#wh-rule-status').val(rule.status);

        // Schedule
        $('#wh-schedule-type').val(rule.schedule_type);
        $('#wh-schedule-hours-list').empty();

        if (rule.schedule_hours && rule.schedule_hours.length > 0) {
            rule.schedule_hours.forEach(hour => {
                $('#wh-schedule-hours-list').append(`
                    <div class="input-group mb-2 wh-schedule-hour-row">
                        <input type="time" class="form-control wh-schedule-hour-input" value="${hour}">
                        <button type="button" class="btn btn-outline-danger wh-remove-hour-btn">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                `);
            });
        } else {
            $('#wh-schedule-hours-list').append(`
                <div class="input-group mb-2 wh-schedule-hour-row">
                    <input type="time" class="form-control wh-schedule-hour-input" value="08:00">
                    <button type="button" class="btn btn-outline-danger wh-remove-hour-btn">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            `);
        }

        // Trigger change after hours are populated so the logic correctly hides/shows elements
        $('#wh-schedule-type').trigger('change');

        // Filters
        setMultiSelectValues($('#wh-rule-categories'), []);
        setMultiSelectValues($('#wh-rule-tags'), []);
        setMultiSelectValues($('#wh-rule-brands'), []);
        setAttributeValues([]);
        setProductSearchValues($('#wh-rule-include-products'), []);
        setProductSearchValues($('#wh-rule-exclude-products'), []);

        if (rule.conditions) {
            setMultiSelectValues($('#wh-rule-categories'), rule.conditions.categories || rule.conditions.rule_categories || []);
            setMultiSelectValues($('#wh-rule-tags'), rule.conditions.tags || rule.conditions.rule_tags || []);
            setMultiSelectValues($('#wh-rule-brands'), rule.conditions.rule_brands || rule.conditions.brands || []);
            setAttributeValues(rule.conditions.rule_attributes || rule.conditions.attributes || []);

            setProductSearchValues(
                $('#wh-rule-include-products'),
                rule.conditions.include_products || rule.conditions.rule_include_products || []
            );

            setProductSearchValues(
                $('#wh-rule-exclude-products'),
                rule.conditions.exclude_products || rule.conditions.rule_exclude_products || []
            );
        }

        // Settings based on type
        if (rule.actions) {
            if (rule.rule_type === 'margin_check' && rule.actions.margin_tiers) {
                $('#wh-margin-tiers-body').empty();
                rule.actions.margin_tiers.forEach(tier => {
                    const row = `
                        <tr class="wh-margin-tier-row">
                            <td style="cursor: move;" class="text-center align-middle" title="Drag to sort"><i class="fa-solid fa-grip-lines text-muted"></i></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control wh-tier-min-cog" step="0.01" min="0" value="${tier.min_cog}">
                                    <span class="input-group-text">${wh_script_params.currency_format_symbol}</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control wh-tier-max-cog" step="0.01" min="0" value="${tier.max_cog}">
                                    <span class="input-group-text">${wh_script_params.currency_format_symbol}</span>
                                </div>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control wh-tier-margin" step="0.1" min="0" value="${tier.margin_pct}">
                                    <span class="input-group-text">%</span>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger wh-remove-tier-btn">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#wh-margin-tiers-body').append(row);
                });
            } else if (rule.rule_type === 'max_discount_pct' && rule.actions.max_discount_pct) {
                $('#wh-max-discount-pct').val(rule.actions.max_discount_pct);
            } else if (rule.rule_type === 'price_adjustment') {
                $('#wh-adj-price-type').val(rule.actions.price_type || 'sale_price');
                $('#wh-adj-action-type').val(rule.actions.action_type || 'increase');
                $('#wh-adj-price-value').val(rule.actions.price_value || 0);
                $('#wh-adj-change-type').val(rule.actions.change_type || 'percentage');
            }
        }

        hidePreviewResult(true);

        // Trigger real-time validation on modal open
        if (rule.rule_type === 'margin_check') {
            setTimeout(validateMarginTiers, 100);
        }

        updateSaveButtonState();
        setStep(1);

        const modal = new bootstrap.Modal(document.getElementById('wh-rule-form-modal'));
        modal.show();
    });

    // Function to collect form data
    function getRuleFormData(performValidation = true) {
        if (performValidation && !validateAllSteps(true)) {
            return false;
        }

        const rule_type = $('#wh-rule-type').val();

        // Compile Conditions
        const conditions = {
            categories: $('#wh-rule-categories').val() || [],
            tags: $('#wh-rule-tags').val() || [],
            rule_brands: $('#wh-rule-brands').val() || [],
            rule_attributes: $('#wh-rule-attributes').val() || [],
            include_products: $('#wh-rule-include-products').val() || [],
            exclude_products: $('#wh-rule-exclude-products').val() || [],
        };

        // Compile Schedule Hours
        const schedule_hours = [];
        $('.wh-schedule-hour-input').each(function () {
            if ($(this).val()) {
                schedule_hours.push($(this).val());
            }
        });

        // Compile Actions based on rule type
        const actions = {};
        if (rule_type === 'margin_check') {
            const tiers = collectMarginTiersFromForm();
            if (performValidation && !tiers.length) {
                return false;
            }

            actions.margin_tiers = tiers;
            // For backward compatibility, also put it in conditions
            conditions.margin_tiers = tiers;
        } else if (rule_type === 'max_discount_pct') {
            actions.max_discount_pct = parseFloat($('#wh-max-discount-pct').val()) || 0;
        } else if (rule_type === 'price_adjustment') {
            actions.price_type = $('#wh-adj-price-type').val();
            actions.action_type = $('#wh-adj-action-type').val();
            actions.change_type = $('#wh-adj-change-type').val();
            actions.price_value = parseFloat($('#wh-adj-price-value').val()) || 0;
        }

        return {
            name: ($('#wh-rule-name').val() || '').trim(),
            status: $('#wh-rule-status').val(),
            schedule_type: $('#wh-schedule-type').val(),
            schedule_hours: JSON.stringify(schedule_hours),
            rule_type: rule_type,
            conditions: JSON.stringify(conditions),
            actions: JSON.stringify(actions)
        };
    }

    // Save Rule
    $('#wh-save-rule-btn').on('click', function () {
        const formData = getRuleFormData();
        if (!formData) return;

        const data = {
            action: 'webhead_bulk_price_update_save_price_rule',
            security: wh_script_params.save_price_rule_nonce,
            rule_id: $('#wh-rule-id').val(),
            ...formData
        };

        const btn = $(this);
        const btnText = btn.find('.btn-text');
        const btnStatus = btn.find('.btn-status');
        const btnIcon = btn.find('.btn-icon');
        const btnSpinner = btn.find('.btn-spinner');

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: data,
            beforeSend: function () {
                btn.prop('disabled', true);
                btnIcon.addClass('d-none');
                btnText.addClass('d-none');
                btnSpinner.removeClass('d-none');
                btnStatus.removeClass('d-none');
            },
            success: function (response) {
                if (response.success) {
                    location.reload(); // Reload to refresh the list
                } else {
                    alert(response.data.message || 'Error saving rule.');
                    updateSaveButtonState();
                    btnIcon.removeClass('d-none');
                    btnText.removeClass('d-none');
                    btnSpinner.addClass('d-none');
                    btnStatus.addClass('d-none');
                }
            },
            error: function () {
                alert('Connection error.');
                updateSaveButtonState();
                btnIcon.removeClass('d-none');
                btnText.removeClass('d-none');
                btnSpinner.addClass('d-none');
                btnStatus.addClass('d-none');
            }
        });
    });

    // Toggle Rule Status
    $('.wh-toggle-rule-status').on('change', function () {
        const rule_id = $(this).data('rule-id');
        const is_checked = $(this).is(':checked');

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: {
                action: 'webhead_bulk_price_update_toggle_price_rule',
                security: wh_script_params.toggle_price_rule_nonce,
                rule_id: rule_id
            },
            success: function (response) {
                if (!response.success) {
                    alert(response.data.message);
                    $(this).prop('checked', !is_checked); // Revert
                }
            }.bind(this)
        });
    });

    // Delete Rule
    let deleteRuleId = 0;
    $('.wh-delete-rule').on('click', function () {
        deleteRuleId = $(this).data('rule-id');
        const modal = new bootstrap.Modal(document.getElementById('wh-confirm-delete-rule'));
        modal.show();
    });

    $('#wh-confirm-delete-rule-btn').on('click', function () {
        if (!deleteRuleId) return;

        const btn = $(this);
        const originalText = btn.html();

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: {
                action: 'webhead_bulk_price_update_delete_price_rule',
                security: wh_script_params.delete_price_rule_nonce,
                rule_id: deleteRuleId
            },
            beforeSend: function () {
                btn.prop('disabled', true).text('Deleting...');
            },
            success: function (response) {
                if (response.success) {
                    $('#wh-rule-row-' + deleteRuleId).fadeOut(function () {
                        $(this).remove();
                        if ($('.wh-rules-table tbody tr').length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    });
                    bootstrap.Modal.getInstance(document.getElementById('wh-confirm-delete-rule')).hide();
                } else {
                    alert(response.data.message || 'Error deleting rule.');
                }
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Run Rule
    $('.wh-run-rule').on('click', function () {
        const rule_id = $(this).data('rule-id');
        const btn = $(this);
        const icon = btn.find('i');

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: {
                action: 'webhead_bulk_price_update_run_price_rule',
                security: wh_script_params.run_price_rule_nonce,
                rule_id: rule_id
            },
            beforeSend: function () {
                btn.prop('disabled', true);
                icon.removeClass('fa-play').addClass('fa-spinner fa-spin');
            },
            success: function (response) {
                btn.prop('disabled', false);
                icon.removeClass('fa-spinner fa-spin').addClass('fa-play');

                if (response.success) {
                    $('#wh-rule-toast-body').html(`
                        <i class="fa-solid fa-check-circle text-success me-2"></i>
                        ${response.data.message}
                    `);
                    const toast = new bootstrap.Toast(document.getElementById('wh-rule-toast'));
                    toast.show();
                } else {
                    alert(response.data.message || 'Error running rule.');
                }
            }
        });
    });

    // View Rule Logs
    $('.wh-view-logs').on('click', function () {
        const rule_id = $(this).data('rule-id');
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('wh-rule-logs-modal'));

        $logsContent.html(getLogsLoadingMarkup());
        modal.show();
        loadRuleLogs(rule_id, 1, true);
    });

    $(document).on('submit', '#wh-rule-logs-filter-form', function (event) {
        event.preventDefault();
        const ruleId = parseInt($(this).data('rule-id'), 10) || activeLogsRuleId;
        loadRuleLogs(ruleId, 1, false);
    });

    $(document).on('click', '.wh-reset-logs-filters', function () {
        const $form = $('#wh-rule-logs-filter-form');
        const ruleId = parseInt($form.data('rule-id'), 10) || activeLogsRuleId;
        resetLogsFilterForm($form);
        loadRuleLogs(ruleId, 1, false);
    });

    $(document).on('click', '.wh-rule-logs-page-link', function (event) {
        event.preventDefault();
        const $link = $(this);
        const $pageItem = $link.closest('.page-item');
        const targetPage = parseInt($link.data('page'), 10);

        if (
            $pageItem.hasClass('disabled')
            || $pageItem.hasClass('active')
            || isNaN(targetPage)
            || targetPage <= 0
            || activeLogsRuleId <= 0
        ) {
            return;
        }

        loadRuleLogs(activeLogsRuleId, targetPage, false);
    });

    $(document).on('click', '.wh-clear-rule-logs', function () {
        const ruleId = parseInt($(this).data('rule-id'), 10) || activeLogsRuleId;
        if (!ruleId || ruleId <= 0) {
            return;
        }

        const confirmText = wh_script_params.i18n_confirm_clear_logs || 'Clear all logs for this rule?';
        if (!window.confirm(confirmText)) {
            return;
        }

        const $button = $(this);
        const originalHtml = $button.html();
        const clearingText = wh_script_params.i18n_clearing || 'Clearing...';
        $button.prop('disabled', true).html(`<i class="fa-solid fa-spinner fa-spin me-1"></i>${clearingText}`);

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'webhead_bulk_price_update_clear_price_rule_logs',
                security: wh_script_params.clear_price_rule_logs_nonce,
                rule_id: ruleId
            },
            success: function (response) {
                if (response && response.success) {
                    $('#wh-rule-toast-body').html(`
                        <i class="fa-solid fa-check-circle text-success me-2"></i>
                        ${response.data.message || 'Execution logs cleared.'}
                    `);
                    const toast = new bootstrap.Toast(document.getElementById('wh-rule-toast'));
                    toast.show();

                    loadRuleLogs(ruleId, 1, true);
                } else {
                    alert((response && response.data && response.data.message) ? response.data.message : 'Failed to clear logs.');
                }
            },
            error: function (xhr) {
                alert(extractAjaxErrorMessage(xhr, 'Failed to clear logs.'));
            },
            complete: function () {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });

    $logsModal.on('hidden.bs.modal', function () {
        if (logsRequest && logsRequest.readyState !== 4) {
            logsRequest.abort();
        }
        logsRequest = null;
        activeLogsRuleId = 0;
        $logsContent.html(getLogsLoadingMarkup());
    });

    // Preview Prices for Rules Form
    $('#wh-preview-rule-btn').on('click', function () {
        if (currentStep !== totalSteps) {
            return;
        }

        const formData = getRuleFormData(true);
        if (!formData) return;

        const btn = $(this);
        const icon = btn.find('i');

        $.ajax({
            url: wh_script_params.ajax_url,
            type: 'POST',
            data: {
                action: 'webhead_bulk_price_update_preview_price_rule',
                security: wh_script_params.preview_price_rule_nonce,
                rule_type: formData.rule_type,
                conditions: formData.conditions,
                actions: formData.actions
            },
            beforeSend: function () {
                btn.prop('disabled', true);
                icon.removeClass('fa-eye').addClass('fa-spinner fa-spin');
            },
            success: function (response) {
                btn.prop('disabled', false);
                icon.removeClass('fa-spinner fa-spin').addClass('fa-eye');

                let html = response;

                if (response && typeof response === 'object') {
                    if (response.success && response.data && typeof response.data.html === 'string') {
                        html = response.data.html;
                    } else if (response.data && response.data.message) {
                        alert(response.data.message);
                        return;
                    }
                } else if (typeof response === 'string') {
                    const trimmed = response.trim();
                    if (trimmed && (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[')) {
                        try {
                            const parsed = JSON.parse(trimmed);
                            if (parsed && parsed.success && parsed.data && typeof parsed.data.html === 'string') {
                                html = parsed.data.html;
                            } else if (parsed && parsed.data && parsed.data.message) {
                                alert(parsed.data.message);
                                return;
                            }
                        } catch (err) {
                            // Ignore parse errors and treat response as plain HTML.
                        }
                    }
                }

                if (typeof html !== 'string' || !html.trim()) {
                    alert('Error previewing rule.');
                    return;
                }

                showPreviewResult(html);
            },
            error: function () {
                alert('Connection error.');
                btn.prop('disabled', false);
                icon.removeClass('fa-spinner fa-spin').addClass('fa-eye');
            }
        });
    });

    $(document).on('click', '.wh-preview-add-exclude-btn', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }

        const productId = String($btn.data('productId') || '').trim();
        const productName = String($btn.data('productName') || '').trim();

        if (!addProductToExcludeSelection(productId, productName)) {
            return;
        }

        disposeTooltip($btn);
        $btn.prop('disabled', true).removeClass('btn-outline-danger').addClass('btn-success');
        const excludedText = wh_script_params.i18n_excluded || 'Excluded';
        $btn.attr('title', excludedText);
        $btn.attr('data-bs-title', excludedText);
        $btn.attr('aria-label', excludedText);
        $btn.html('<i class="fa-solid fa-check" aria-hidden="true"></i>');
        $btn.closest('tr').addClass('table-secondary');
    });

    $ruleModal.on('hidden.bs.modal', function () {
        resetSteps();
    });

    $ruleModal.on('shown.bs.modal', function () {
        const $body = $(this).find('.modal-body');
        $body.scrollTop(0);
        $body.scrollLeft(0);
        initializeTooltips($(this));
        updateSaveButtonState();
    });

    $ruleForm.on('input change', 'input, select, textarea', function () {
        const $field = $(this);
        clearFieldError($field);

        if (Date.now() < suppressPreviewHideUntil) {
            updateSaveButtonState();
            return;
        }

        hidePreviewResult();
        updateSaveButtonState();
    });
});
