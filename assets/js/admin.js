/**
 * VIN Decoder Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        initVINDecoderAdmin();
    });

    function initVINDecoderAdmin() {
        // Handle VIN delete buttons
        $(document).on('click', '.vin-delete', function(e) {
            e.preventDefault();

            const $button = $(this);
            const vinId = $button.data('vin-id');

            if (!confirm(vinDecoderAjax.strings.confirm_delete)) {
                return;
            }

            deleteVIN(vinId, $button);
        });

        // Handle VIN details view
        $(document).on('click', '.vin-view-details', function(e) {
            e.preventDefault();

            const $button = $(this);
            const vinId = $button.data('vin-id');

            showVINDetails(vinId);
        });

        // Handle modal close
        $(document).on('click', '.vin-decoder-modal-close', function() {
            closeModal();
        });

        // Close modal on outside click
        $(document).on('click', '.vin-decoder-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Handle filter form submission
        $('.vin-decoder-filters').on('submit', function(e) {
            e.preventDefault();
            filterVINs();
        });

        // Handle pagination
        $(document).on('click', '.tablenav-pages a', function(e) {
            e.preventDefault();
            const url = new URL($(this).attr('href'));
            const paged = url.searchParams.get('paged') || 1;
            loadVINsPage(paged);
        });
    }

    /**
     * Delete a VIN decode
     */
    function deleteVIN(vinId, $button) {
        const originalText = $button.text();
        $button.prop('disabled', true).text(vinDecoderAjax.strings.deleting);

        $.ajax({
            url: vinDecoderAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vin_decoder_delete_decode',
                vin_id: vinId,
                nonce: vinDecoderAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row from the table
                    $button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        updateResultsInfo();
                    });
                } else {
                    alert(response.data || vinDecoderAjax.strings.error);
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(vinDecoderAjax.strings.error);
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Show VIN details modal
     */
    function showVINDetails(vinId) {
        // Show loading modal
        showModal('<h3>Loading VIN Details...</h3><div class="vin-loading"></div>');

        $.ajax({
            url: vinDecoderAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vin_decoder_get_vin_details',
                vin_id: vinId,
                nonce: vinDecoderAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const detailsHtml = generateVINDetailsHTML(response.data);
                    showModal(detailsHtml);
                } else {
                    showModal('<h3>Error</h3><p>' + (response.data || vinDecoderAjax.strings.error) + '</p>');
                }
            },
            error: function() {
                showModal('<h3>Error</h3><p>' + vinDecoderAjax.strings.error + '</p>');
            }
        });
    }

    /**
     * Generate VIN details HTML
     */
    function generateVINDetailsHTML(data) {
        let html = '<h3>VIN: ' + data.vin + '</h3>';

        if (data.decoded_data) {
            const sections = [
                { title: 'Basic Information', fields: ['year', 'make', 'model', 'trim', 'series'] },
                { title: 'Body & Configuration', fields: ['bodyClass', 'vehicleType', 'doors', 'seats'] },
                { title: 'Engine Specifications', fields: ['cylinders', 'displacement', 'engineModel', 'horsepower'] },
                { title: 'Fuel System', fields: ['fuelType'] },
                { title: 'Drivetrain', fields: ['transmission', 'driveType'] },
                { title: 'Dimensions & Weight', fields: ['gvwrFrom', 'curbWeight'] },
                { title: 'Safety Features', fields: ['airbags', 'abs'] },
                { title: 'Manufacturing', fields: ['manufacturer', 'plantCity', 'plantState', 'plantCountry'] }
            ];

            sections.forEach(function(section) {
                const items = [];
                section.fields.forEach(function(field) {
                    if (data.decoded_data[field]) {
                        items.push('<div class="vin-detail-item"><span class="vin-detail-label">' + field + ':</span> <span class="vin-detail-value">' + data.decoded_data[field] + '</span></div>');
                    }
                });

                if (items.length > 0) {
                    html += '<div class="vin-details-section">';
                    html += '<h4>' + section.title + '</h4>';
                    html += '<div class="vin-details-grid">' + items.join('') + '</div>';
                    html += '</div>';
                }
            });
        }

        html += '<div class="vin-details-section">';
        html += '<h4>Metadata</h4>';
        html += '<div class="vin-details-grid">';
        html += '<div class="vin-detail-item"><span class="vin-detail-label">Decoded:</span> <span class="vin-detail-value">' + data.decoded_at + '</span></div>';
        html += '<div class="vin-detail-item"><span class="vin-detail-label">API Source:</span> <span class="vin-detail-value">' + data.api_source + '</span></div>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    /**
     * Show modal dialog
     */
    function showModal(content) {
        // Remove existing modal
        $('.vin-decoder-modal').remove();

        // Create new modal
        const modal = `
            <div class="vin-decoder-modal">
                <div class="vin-decoder-modal-content">
                    <div class="vin-decoder-modal-header">
                        <span class="vin-decoder-modal-close">&times;</span>
                    </div>
                    <div class="vin-decoder-modal-body">
                        ${content}
                    </div>
                    <div class="vin-decoder-modal-footer">
                        <button class="button vin-decoder-modal-close">Close</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modal);
        $('.vin-decoder-modal').show();
    }

    /**
     * Close modal dialog
     */
    function closeModal() {
        $('.vin-decoder-modal').fadeOut(300, function() {
            $(this).remove();
        });
    }

    /**
     * Filter VINs
     */
    function filterVINs() {
        const formData = $('.vin-decoder-filters').serializeArray();
        const params = {};

        formData.forEach(function(field) {
            if (field.value) {
                params[field.name] = field.value;
            }
        });

        // Add page=1 to reset pagination
        params.page = 1;

        loadVINsPage(1, params);
    }

    /**
     * Load VINs page with filters
     */
    function loadVINsPage(page, filters = {}) {
        const $results = $('#vin-decoder-results');
        const $table = $results.closest('table');

        // Show loading
        $results.html('<tr><td colspan="5" style="text-align: center; padding: 40px;"><div class="vin-loading" style="display: inline-block; margin-right: 10px;"></div>Loading...</td></tr>');

        const data = {
            action: 'vin_decoder_get_decodes',
            page: page,
            nonce: vinDecoderAjax.nonce
        };

        // Add filters
        Object.keys(filters).forEach(function(key) {
            if (filters[key]) {
                data[key] = filters[key];
            }
        });

        // Add current filters from form
        $('.vin-decoder-filters input, .vin-decoder-filters select').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (name && value) {
                data[name] = value;
            }
        });

        $.ajax({
            url: vinDecoderAjax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $results.html(response.data.rows);

                    // Update pagination
                    if (response.data.pagination) {
                        $table.next('.tablenav').replaceWith(response.data.pagination);
                    }

                    // Update results info
                    updateResultsInfo(response.data.total);
                } else {
                    $results.html('<tr><td colspan="5">' + (response.data || vinDecoderAjax.strings.no_results) + '</td></tr>');
                }
            },
            error: function() {
                $results.html('<tr><td colspan="5">' + vinDecoderAjax.strings.error + '</td></tr>');
            }
        });
    }

    /**
     * Update results info text
     */
    function updateResultsInfo(total) {
        const $info = $('.vin-decoder-results-info');
        if ($info.length) {
            const currentFilters = $('.vin-decoder-filters input, .vin-decoder-filters select').filter(function() {
                return $(this).val();
            }).length;

            let infoText = 'Showing ' + (total || $('#vin-decoder-results tr').length) + ' VINs';
            if (currentFilters > 0) {
                infoText += ' (filtered)';
            }

            $info.text(infoText);
        }
    }

})(jQuery);
