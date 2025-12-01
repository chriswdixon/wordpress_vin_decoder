/**
 * VIN Decoder Frontend JavaScript
 */

(function($) {
    'use strict';

    let vinDecodeTimeout;
    let currentForm = null;

    $(document).ready(function() {
        // Initialize VIN decoder on Contact Form 7 forms
        initVINDecoder();
    });

    function initVINDecoder() {
        // Find Contact Form 7 forms
        const cf7Forms = $('.wpcf7-form');

        if (cf7Forms.length === 0) {
            return;
        }

        cf7Forms.each(function() {
            const $form = $(this);
            const vinField = findVINField($form);

            if (vinField.length > 0) {
                setupVINField($form, vinField);
            }
        });
    }

    /**
     * Find VIN field in a form
     */
    function findVINField($form) {
        // Look for input fields with VIN-related names, IDs, or classes
        const vinSelectors = [
            'input[name*="vin"]',
            'input[name*="vehicle"]',
            'input[id*="vin"]',
            'input[class*="vin"]',
            'input[placeholder*="VIN"]',
            'input[placeholder*="vin"]'
        ];

        for (let selector of vinSelectors) {
            const $field = $form.find(selector).first();
            if ($field.length > 0) {
                return $field;
            }
        }

        return $();
    }

    /**
     * Setup VIN field with decoder functionality
     */
    function setupVINField($form, $vinField) {
        // Add wrapper class
        $vinField.closest('.wpcf7-form-control-wrap').addClass('vin-decoder-field');

        // Add status indicator
        const statusHtml = '<span class="vin-decoder-status" style="display: none;"></span>';
        $vinField.after(statusHtml);

        const $status = $vinField.siblings('.vin-decoder-status');

        // Add preview container
        const previewHtml = '<div class="vin-decoder-preview"><div class="vin-decoder-preview-title">Vehicle Information:</div><div class="vin-decoder-content"></div></div>';
        $form.append(previewHtml);

        const $preview = $form.find('.vin-decoder-preview');

        // Bind events
        $vinField.on('input', function() {
            const vin = $(this).val().trim();

            // Clear previous timeout
            clearTimeout(vinDecodeTimeout);

            // Hide status and preview if VIN is too short
            if (vin.length < 17) {
                $status.hide();
                $preview.removeClass('show');
                return;
            }

            // Validate VIN format
            if (!isValidVIN(vin)) {
                showStatus($status, 'error', vinDecoderFrontend.strings.error_invalid_vin);
                $preview.removeClass('show');
                return;
            }

            // Debounce VIN decoding
            vinDecodeTimeout = setTimeout(function() {
                decodeVIN(vin, $form, $status, $preview);
            }, 500);
        });

        // Handle form submission
        $form.on('submit', function(e) {
            const vin = $vinField.val().trim();

            if (vin && isValidVIN(vin)) {
                // Check if we already decoded this VIN
                if (!$form.data('vin-decoded')) {
                    e.preventDefault();
                    decodeVIN(vin, $form, $status, $preview, true);
                }
            }
        });
    }

    /**
     * Decode VIN via AJAX
     */
    function decodeVIN(vin, $form, $status, $preview, isSubmission = false) {
        // Show processing status
        showStatus($status, 'processing', vinDecoderFrontend.strings.processing);

        $.ajax({
            url: vinDecoderFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'decode_vin',
                vin: vin,
                nonce: vinDecoderFrontend.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success status
                    showStatus($status, 'success', vinDecoderFrontend.strings.vin_decoded);

                    // Show preview
                    showVINPreview($preview, response.data.formatted);

                    // Store decoded data
                    $form.data('vin-decoded', true);
                    $form.data('vin-data', response.data.formatted);

                    // Add hidden field with VIN data
                    let $hiddenField = $form.find('input[name="vin_data"]');
                    if ($hiddenField.length === 0) {
                        $hiddenField = $('<input type="hidden" name="vin_data" />');
                        $form.append($hiddenField);
                    }
                    $hiddenField.val(response.data.formatted);

                    // If this was called during submission, resubmit the form
                    if (isSubmission) {
                        setTimeout(function() {
                            $form.off('submit'); // Prevent infinite loop
                            $form.submit();
                        }, 100);
                    }

                } else {
                    showStatus($status, 'error', response.data.message || vinDecoderFrontend.strings.error_decode_failed);
                    $preview.removeClass('show');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = vinDecoderFrontend.strings.error_network;

                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }

                showStatus($status, 'error', errorMessage);
                $preview.removeClass('show');
            }
        });
    }

    /**
     * Show status indicator
     */
    function showStatus($status, type, message) {
        $status.removeClass('processing success error').addClass(type);
        $status.html('<span class="vin-decoder-loading"></span>' + message);
        $status.show();

        // Remove loading spinner for non-processing states
        if (type !== 'processing') {
            $status.find('.vin-decoder-loading').remove();
        }
    }

    /**
     * Show VIN preview
     */
    function showVINPreview($preview, formattedData) {
        $preview.find('.vin-decoder-content').text(formattedData);
        $preview.addClass('show');
    }

    /**
     * Validate VIN format
     */
    function isValidVIN(vin) {
        // Remove non-alphanumeric characters
        const cleanVIN = vin.replace(/[^A-HJ-NPR-Z0-9]/gi, '').toUpperCase();

        // Must be exactly 17 characters
        if (cleanVIN.length !== 17) {
            return false;
        }

        // Must not contain I, O, Q
        if (/[IOQ]/.test(cleanVIN)) {
            return false;
        }

        // Basic pattern check
        const pattern = /^[A-HJ-NPR-Z0-9]{17}$/;
        return pattern.test(cleanVIN);
    }

    /**
     * Debug logging (only in development)
     */
    function debugLog(message, data = null) {
        if (vinDecoderFrontend.debug) {
            console.log('[VIN Decoder]', message, data);
        }
    }

})(jQuery);
