/**
 * Accelvia DataForge – Admin JavaScript
 *
 * Handles chart builder logic, live preview rendering,
 * data entry management, AJAX operations, and clipboard.
 * Supports Phase 2: Multi-series, CSV import, and 7 chart types.
 *
 * @package Accelvia_DataForge
 */

(function ($) {
    'use strict';

    // ========================================
    // State
    // ========================================
    let currentChart = null;
    let currentChartType = 'bar';
    let currentPalette = 'default';
    let debounceTimer = null;
    let seriesCount = 0;
    
    // CSV State
    let parsedCsvData = null;

    // ========================================
    // Initialization
    // ========================================
    $(document).ready(function () {
        initChartBuilder();
        initChartList();
        initDashboardBuilder();
        initDashboardList();
    });

    // ========================================
    // Chart Builder
    // ========================================
    function initChartBuilder() {
        const $builder = $('.accelvia-df-builder');
        if (!$builder.length) return;

        // Chart type buttons
        $(document).on('click', '.accelvia-df-type-btn', function () {
            $('.accelvia-df-type-btn').removeClass('active');
            $(this).addClass('active');
            currentChartType = $(this).data('type');
            updatePreview();
        });

        // Palette buttons
        $(document).on('click', '.accelvia-df-palette-btn', function () {
            $('.accelvia-df-palette-btn').removeClass('active');
            $(this).addClass('active');
            currentPalette = $(this).data('palette');
            updatePreview();
        });

        // Tab switching (Manual vs CSV)
        $(document).on('click', '.accelvia-df-data-tab', function () {
            $('.accelvia-df-data-tab').removeClass('active');
            $('.accelvia-df-data-panel').removeClass('active');
            
            $(this).addClass('active');
            const tab = $(this).data('tab');
            $('#accelvia-df-panel-' + tab).addClass('active');
            $('#accelvia-df-data-source').val(tab);
            
            updatePreview();
        });

        // Multi-Series Management
        $('#accelvia-df-add-series').on('click', function () {
            addSeriesPanel();
            updatePreview();
        });

        $(document).on('click', '.accelvia-df-remove-series', function () {
            if (confirm(accelvia_df.i18n.remove_series || 'Remove this series?')) {
                $(this).closest('.accelvia-df-series-panel').remove();
                updatePreview();
            }
        });

        $(document).on('click', '.accelvia-df-add-row', function () {
            const $tbody = $(this).closest('.accelvia-df-series-panel').find('.accelvia-df-data-rows');
            addDataRow($tbody, '', '');
            updatePreview();
        });

        $(document).on('click', '.accelvia-df-remove-row', function () {
            $(this).closest('.accelvia-df-data-row').remove();
            updatePreview();
        });

        // Data input changes — debounced live preview
        $(document).on('input', '.accelvia-df-data-rows input, .accelvia-df-series-name-input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(updatePreview, 300);
        });

        // Option toggles and inputs
        $(document).on('change', '[id^="accelvia-df-opt-"]', function () {
            updatePreview();
        });

        // Save button
        $('#accelvia-df-save-chart').on('click', saveChart);
        
        // CSV Import initialization
        initCsvImport();

        // Load existing or starter
        if (typeof accelvia_df !== 'undefined' && accelvia_df.editing_chart) {
            loadExistingChart(accelvia_df.editing_chart);
        } else {
            loadStarterData();
        }
    }

    // ========================================
    // Data Loading & Multi-Series
    // ========================================
    function loadStarterData() {
        const $panel = addSeriesPanel('Series 1');
        const $tbody = $panel.find('.accelvia-df-data-rows');
        
        const starterData = [
            { label: 'Jan', value: 30 },
            { label: 'Feb', value: 45 },
            { label: 'Mar', value: 35 },
            { label: 'Apr', value: 55 },
            { label: 'May', value: 42 },
        ];
        
        starterData.forEach(item => addDataRow($tbody, item.label, item.value));
        updatePreview();
    }

    function loadExistingChart(chartData) {
        $('#accelvia-df-chart-title').val(chartData.title);
        
        // Set chart type
        currentChartType = chartData.chart_type;
        $('.accelvia-df-type-btn').removeClass('active');
        $('.accelvia-df-type-btn[data-type="' + currentChartType + '"]').addClass('active');

        // Set data source
        const source = chartData.data_source || 'manual';
        $('#accelvia-df-data-source').val(source);
        if (source === 'csv') {
            $('.accelvia-df-data-tab[data-tab="csv"]').click();
        }

        const config = chartData.config;
        if (config) {
            // Options
            if (config.legend) $('#accelvia-df-opt-legend').prop('checked', config.legend.show !== false);
            if (config.grid) $('#accelvia-df-opt-grid').prop('checked', config.grid.show !== false);
            if (config.chart && config.chart.animations) $('#accelvia-df-opt-animation').prop('checked', config.chart.animations.enabled !== false);
            if (config.dataLabels) $('#accelvia-df-opt-datalabels').prop('checked', config.dataLabels.enabled === true);
            if (config.chart && config.chart.toolbar) $('#accelvia-df-opt-toolbar').prop('checked', config.chart.toolbar.show !== false);
            if (config.chart && config.chart.height) $('#accelvia-df-opt-height').val(config.chart.height);
            if (config.chart && config.chart.width) $('#accelvia-df-opt-width').val(config.chart.width);

            // Palette
            if (config.colors && typeof accelvia_df !== 'undefined') {
                const palettes = accelvia_df.color_palettes;
                for (const [key, colors] of Object.entries(palettes)) {
                    if (JSON.stringify(colors.slice(0, config.colors.length)) === JSON.stringify(config.colors)) {
                        currentPalette = key;
                        $('.accelvia-df-palette-btn').removeClass('active');
                        $('.accelvia-df-palette-btn[data-palette="' + key + '"]').addClass('active');
                        break;
                    }
                }
            }

            // Load Series
            $('#accelvia-df-series-container').empty();
            seriesCount = 0;

            const isPieType = ['pie', 'donut', 'radialBar'].includes(currentChartType);
            
            if (isPieType && config.labels && config.series) {
                const $panel = addSeriesPanel('Data');
                const $tbody = $panel.find('.accelvia-df-data-rows');
                config.labels.forEach((label, i) => {
                    addDataRow($tbody, label, config.series[i] || 0);
                });
            } else if (config.series && config.series.length > 0 && config.xaxis) {
                const categories = config.xaxis.categories || [];
                
                config.series.forEach(series => {
                    const $panel = addSeriesPanel(series.name || 'Series');
                    const $tbody = $panel.find('.accelvia-df-data-rows');
                    const data = series.data || [];
                    
                    categories.forEach((cat, i) => {
                        addDataRow($tbody, cat, data[i] || 0);
                    });
                });
            } else {
                loadStarterData();
            }
        }
        updatePreview();
    }

    function addSeriesPanel(name) {
        seriesCount++;
        const seriesName = name || (accelvia_df.i18n.series_name + ' ' + seriesCount);
        
        const html = `
            <div class="ac-card accelvia-df-series-panel" data-series-id="${seriesCount}">
                <div class="accelvia-df-series-header">
                    <input type="text" class="accelvia-df-series-name-input" value="${escapeAttr(seriesName)}" placeholder="Series Name" />
                    <button type="button" class="accelvia-df-remove-series" title="Remove Series"><span class="dashicons dashicons-trash"></span></button>
                </div>
                <div class="accelvia-df-data-header">
                    <span>Label</span>
                    <span>Value</span>
                    <span></span>
                </div>
                <div class="accelvia-df-data-rows"></div>
                <button type="button" class="ac-btn outline accelvia-df-btn-sm accelvia-df-add-row" style="margin-top:8px;width:100%;justify-content:center;">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Row
                </button>
            </div>
        `;
        
        const $panel = $(html);
        $('#accelvia-df-series-container').append($panel);
        
        // Add one empty row by default if creating new
        if (!name) {
            addDataRow($panel.find('.accelvia-df-data-rows'), '', '');
        }
        
        return $panel;
    }

    function addDataRow($tbody, label, value) {
        const html = `
            <div class="accelvia-df-data-row">
                <input type="text" class="accelvia-df-label-input" placeholder="Label" value="${escapeAttr(label)}" />
                <input type="number" class="accelvia-df-value-input" placeholder="0" value="${escapeAttr(value)}" step="any" />
                <button type="button" class="accelvia-df-remove-row" title="Remove"><span class="dashicons dashicons-dismiss"></span></button>
            </div>
        `;
        $tbody.append(html);
    }

    function getFormData() {
        const source = $('#accelvia-df-data-source').val();
        
        if (source === 'csv' && parsedCsvData) {
            return getCsvFormData();
        }

        const isPieType = ['pie', 'donut', 'radialBar'].includes(currentChartType);
        const result = { labels: [], series: [] };
        
        if (isPieType) {
            // Pie charts only use the first series panel logically
            const $firstPanel = $('.accelvia-df-series-panel').first();
            if ($firstPanel.length) {
                $firstPanel.find('.accelvia-df-data-row').each(function() {
                    const label = $(this).find('.accelvia-df-label-input').val().trim();
                    const value = parseFloat($(this).find('.accelvia-df-value-input').val()) || 0;
                    if (label || value) {
                        result.labels.push(label || 'Untitled');
                        result.series.push(value);
                    }
                });
            }
            return result;
        }

        // Multi-series for categorical charts
        let globalLabels = [];
        let labelsSet = false;

        $('.accelvia-df-series-panel').each(function() {
            const seriesName = $(this).find('.accelvia-df-series-name-input').val().trim() || 'Series';
            const data = [];
            const localLabels = [];
            
            $(this).find('.accelvia-df-data-row').each(function() {
                const label = $(this).find('.accelvia-df-label-input').val().trim();
                const value = parseFloat($(this).find('.accelvia-df-value-input').val()) || 0;
                
                if (label || value) {
                    localLabels.push(label || 'Untitled');
                    data.push(value);
                }
            });
            
            if (data.length > 0) {
                result.series.push({ name: seriesName, data: data });
                if (!labelsSet) {
                    globalLabels = localLabels;
                    labelsSet = true;
                }
            }
        });

        result.labels = globalLabels;
        return result;
    }

    // ========================================
    // CSV Import handling
    // ========================================
    function initCsvImport() {
        const $dropzone = $('#accelvia-df-csv-dropzone');
        const $fileInput = $('#accelvia-df-csv-file');

        $('#accelvia-df-csv-browse').on('click', () => $fileInput.click());

        $dropzone.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        }).on('dragleave drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            if (e.type === 'drop') {
                const files = e.originalEvent.dataTransfer.files;
                if (files.length) handleCsvFile(files[0]);
            }
        });

        $fileInput.on('change', function() {
            if (this.files.length) handleCsvFile(this.files[0]);
        });
        
        $('#accelvia-df-csv-apply').on('click', function() {
            updatePreview();
        });
        
        $('#accelvia-df-csv-cancel').on('click', function() {
            parsedCsvData = null;
            $('#accelvia-df-csv-mapping').hide();
            $('#accelvia-df-csv-dropzone').show();
            $('#accelvia-df-data-source').val('manual');
            $('.accelvia-df-data-tab[data-tab="manual"]').click();
        });
    }

    function handleCsvFile(file) {
        if (!file.name.endsWith('.csv')) {
            alert('Please select a valid CSV file.');
            return;
        }

        const maxSize = accelvia_df.max_csv_size || 2097152;
        if (file.size > maxSize) {
            alert(accelvia_df.i18n.csv_too_large || 'File is too large.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'accelvia_df_parse_csv');
        formData.append('nonce', accelvia_df.nonce);
        formData.append('csv_file', file);

        const $btn = $('#accelvia-df-csv-browse');
        const originalText = $btn.text();
        $btn.text('Uploading...').prop('disabled', true);

        $.ajax({
            url: accelvia_df.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    parsedCsvData = response.data;
                    renderCsvMappingUI(response.data);
                } else {
                    alert(response.data.message || accelvia_df.i18n.csv_error);
                }
            },
            error: function() {
                alert(accelvia_df.i18n.csv_error);
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
                $('#accelvia-df-csv-file').val('');
            }
        });
    }

    function renderCsvMappingUI(data) {
        $('#accelvia-df-csv-dropzone').hide();
        const $mapping = $('#accelvia-df-csv-mapping').show();
        
        // Build dropdowns
        let optionsHtml = '';
        data.headers.forEach((h, i) => {
            optionsHtml += `<option value="${i}">${escapeHtml(h)}</option>`;
        });

        // Mapping grid
        const isPieType = ['pie', 'donut', 'radialBar'].includes(currentChartType);
        let gridHtml = `
            <div class="ac-form-group">
                <label>Label Column (X-Axis)</label>
                <select id="csv-label-col" class="accelvia-df-select">${optionsHtml}</select>
            </div>
        `;
        
        if (isPieType) {
            gridHtml += `
                <div class="ac-form-group">
                    <label>Value Column (Y-Axis)</label>
                    <select id="csv-val-col-0" class="accelvia-df-select csv-val-select">${optionsHtml}</select>
                </div>
            `;
        } else {
            // Find numeric columns for multi-series
            let numOptions = '';
            data.columns.forEach(col => {
                const selected = col.type === 'numeric' ? 'selected' : '';
                numOptions += `<option value="${col.index}" ${selected}>${escapeHtml(col.name)}</option>`;
            });
            
            gridHtml += `
                <div class="ac-form-group">
                    <label>Data Series Columns (Hold Ctrl/Cmd to select multiple)</label>
                    <select id="csv-val-cols" class="accelvia-df-select" multiple style="height:120px;">
                        ${numOptions}
                    </select>
                </div>
            `;
        }
        
        $('#accelvia-df-csv-mapping-grid').html(gridHtml);

        // Preview table
        let tableHtml = '<thead><tr>';
        data.headers.forEach(h => tableHtml += `<th>${escapeHtml(h)}</th>`);
        tableHtml += '</tr></thead><tbody>';
        
        data.preview_rows.forEach(row => {
            tableHtml += '<tr>';
            row.forEach(cell => tableHtml += `<td>${escapeHtml(cell)}</td>`);
            tableHtml += '</tr>';
        });
        tableHtml += '</tbody>';
        $('#accelvia-df-csv-preview-table').html(tableHtml);
        
        // Set first column as label by default
        $('#csv-label-col').val('0');
        if (isPieType) $('#csv-val-col-0').val(data.headers.length > 1 ? '1' : '0');
    }

    function getCsvFormData() {
        if (!parsedCsvData) return { labels: [], series: [] };
        
        const labelCol = parseInt($('#csv-label-col').val(), 10) || 0;
        const isPieType = ['pie', 'donut', 'radialBar'].includes(currentChartType);
        
        const labels = [];
        const series = [];
        const rows = parsedCsvData.full_data.rows;
        const headers = parsedCsvData.headers;

        if (isPieType) {
            const valCol = parseInt($('#csv-val-col-0').val(), 10) || 0;
            rows.forEach(row => {
                labels.push(row[labelCol] || '');
                series.push(parseFloat(row[valCol]) || 0);
            });
            return { labels, series };
        } else {
            const valCols = $('#csv-val-cols').val() || [];
            valCols.forEach(colIdx => {
                series.push({
                    name: headers[colIdx] || `Series`,
                    data: []
                });
            });
            
            rows.forEach(row => {
                labels.push(row[labelCol] || '');
                valCols.forEach((colIdx, i) => {
                    series[i].data.push(parseFloat(row[colIdx]) || 0);
                });
            });
            
            return { labels, series };
        }
    }

    // ========================================
    // Live Preview Config Builder
    // ========================================
    function updatePreview() {
        const data = getFormData();
        if (!data.labels || data.labels.length === 0 || (data.series.length === 0 && !data.series[0])) {
            showPlaceholder();
            return;
        }

        const config = buildChartConfig(data);
        renderChart(config);
    }

    function buildChartConfig(data) {
        const palettes = (typeof accelvia_df !== 'undefined') ? accelvia_df.color_palettes : {};
        const colors = palettes[currentPalette] || ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        const isPieType = ['pie', 'donut', 'radialBar'].includes(currentChartType);

        const heightVal = $('#accelvia-df-opt-height').val() || '350';
        const widthVal = $('#accelvia-df-opt-width').val() || '100%';

        const config = {
            chart: {
                type: currentChartType,
                height: heightVal,
                width: widthVal,
                toolbar: { show: $('#accelvia-df-opt-toolbar').is(':checked') },
                animations: { enabled: $('#accelvia-df-opt-animation').is(':checked') },
                background: 'transparent',
            },
            theme: { mode: 'dark' },
            legend: { show: $('#accelvia-df-opt-legend').is(':checked') },
            dataLabels: { enabled: $('#accelvia-df-opt-datalabels').is(':checked') },
        };

        if (isPieType) {
            config.series = data.series;
            config.labels = data.labels;
            config.colors = colors.slice(0, Math.max(data.series.length, 1));
            
            if (currentChartType === 'radialBar') {
                config.plotOptions = {
                    radialBar: {
                        dataLabels: {
                            name: { show: true },
                            value: { show: true }
                        }
                    }
                };
            }
        } else {
            config.series = data.series;
            config.xaxis = { categories: data.labels };
            config.colors = colors.slice(0, Math.max(data.series.length, 1));
            config.grid = {
                show: $('#accelvia-df-opt-grid').is(':checked'),
                borderColor: '#2d3148',
            };
            
            if (currentChartType === 'line') {
                config.stroke = { curve: 'smooth', width: 3 };
            } else if (currentChartType === 'area') {
                config.stroke = { curve: 'smooth', width: 2 };
                config.fill = {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 }
                };
            } else if (currentChartType === 'radar') {
                config.stroke = { width: 2 };
                config.fill = { opacity: 0.25 };
                config.markers = { size: 4 };
            }
        }

        return config;
    }

    function renderChart(config) {
        const $container = $('#accelvia-df-chart-preview');
        $container.html(''); 

        if (currentChart) {
            try { currentChart.destroy(); } catch (e) {}
            currentChart = null;
        }

        try {
            currentChart = new ApexCharts($container[0], config);
            currentChart.render();
        } catch (e) {
            showPlaceholder(accelvia_df.i18n.invalid_config || 'Invalid configuration.');
        }
    }

    function showPlaceholder(msg) {
        const message = msg || (typeof accelvia_df !== 'undefined' ? accelvia_df.i18n.no_data : 'Add data to see preview.');
        $('#accelvia-df-chart-preview').html(
            '<div class="accelvia-df-preview-placeholder"><span class="accelvia-df-preview-placeholder-icon">📊</span><p>' + escapeHtml(message) + '</p></div>'
        );
    }

    // ========================================
    // Save Chart (AJAX)
    // ========================================
    function saveChart() {
        const title = $('#accelvia-df-chart-title').val().trim();
        if (!title) {
            $('#accelvia-df-chart-title').focus().css('border-color', '#ef4444');
            setTimeout(() => $('#accelvia-df-chart-title').css('border-color', ''), 2000);
            return;
        }

        const data = getFormData();
        if (!data.labels || data.labels.length === 0) return;

        const config = buildChartConfig(data);
        const chartId = $('#accelvia-df-chart-id').val() || 0;
        const source = $('#accelvia-df-data-source').val();

        const $btn = $('#accelvia-df-save-chart');
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="margin-right:6px;line-height:1.4;animation:spin 1s linear infinite;"></span> Saving...');

        $.ajax({
            url: accelvia_df.ajax_url,
            method: 'POST',
            data: {
                action: 'accelvia_df_save_chart',
                nonce: accelvia_df.nonce,
                title: title,
                chart_type: currentChartType,
                data_source: source,
                config: JSON.stringify(config),
                chart_id: chartId,
            },
            success: function (response) {
                if (response.success) {
                    $('#accelvia-df-chart-id').val(response.data.chart_id);
                    showToast(response.data.message, response.data.shortcode);
                    if (!chartId && window.history.replaceState) {
                        const newUrl = window.location.href + '&chart_id=' + response.data.chart_id;
                        window.history.replaceState(null, '', newUrl);
                    }
                } else {
                    alert(response.data.message || accelvia_df.i18n.save_error);
                }
            },
            error: () => alert(accelvia_df.i18n.save_error),
            complete: () => $btn.prop('disabled', false).html(originalText),
        });
    }

    // ========================================
    // Chart List Actions (Duplicate, Export, Import, Delete)
    // ========================================
    function initChartList() {
        // Copy Shortcode
        $(document).on('click', '.accelvia-df-copy-btn', function (e) {
            e.preventDefault();
            const shortcode = $(this).data('shortcode');
            if (!shortcode) return;

            copyToClipboard(shortcode).then(() => {
                const $icon = $(this).find('.dashicons');
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
                setTimeout(() => $icon.removeClass('dashicons-yes').addClass('dashicons-clipboard'), 1500);
            });
        });

        // Delete
        $(document).on('click', '.accelvia-df-delete-btn', function () {
            const chartId = $(this).data('chart-id');
            if (!confirm(accelvia_df.i18n.delete_confirm || 'Delete this chart?')) return;

            const $card = $(this).closest('.accelvia-df-chart-card');
            $card.css('opacity', '0.5');

            $.ajax({
                url: accelvia_df.ajax_url,
                method: 'POST',
                data: { action: 'accelvia_df_delete_chart', nonce: accelvia_df.nonce, chart_id: chartId },
                success: function (r) {
                    if (r.success) $card.slideUp(300, () => $card.remove());
                    else { $card.css('opacity', '1'); alert(r.data.message); }
                },
                error: () => $card.css('opacity', '1')
            });
        });

        // Duplicate
        $(document).on('click', '.accelvia-df-duplicate-btn', function () {
            const chartId = $(this).data('chart-id');
            const $btn = $(this);
            $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-update spin');

            $.ajax({
                url: accelvia_df.ajax_url,
                method: 'POST',
                data: { action: 'accelvia_df_duplicate_chart', nonce: accelvia_df.nonce, chart_id: chartId },
                success: function (r) {
                    if (r.success) {
                        location.reload(); // Simplest way to show the duplicated chart cleanly
                    } else {
                        alert(r.data.message);
                        $btn.prop('disabled', false).find('.dashicons').addClass('dashicons-admin-page').removeClass('dashicons-update spin');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).find('.dashicons').addClass('dashicons-admin-page').removeClass('dashicons-update spin');
                }
            });
        });

        // Export
        $(document).on('click', '.accelvia-df-export-btn', function () {
            const chartId = $(this).data('chart-id');
            const $btn = $(this);
            
            $.ajax({
                url: accelvia_df.ajax_url,
                method: 'POST',
                data: { action: 'accelvia_df_export_chart', nonce: accelvia_df.nonce, chart_id: chartId },
                success: function (r) {
                    if (r.success) {
                        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(r.data.export_data, null, 2));
                        const dlAnchorElem = document.createElement('a');
                        dlAnchorElem.setAttribute("href", dataStr);
                        dlAnchorElem.setAttribute("download", r.data.filename);
                        dlAnchorElem.click();
                    } else {
                        alert(r.data.message);
                    }
                }
            });
        });

        // Import Modal
        $('#accelvia-df-import-btn').on('click', () => $('#accelvia-df-import-modal').fadeIn(200));
        $('.accelvia-df-modal-close, .accelvia-df-modal-backdrop').on('click', () => $('#accelvia-df-import-modal').fadeOut(200));

        // Import Handler
        $('#accelvia-df-import-browse').on('click', () => $('#accelvia-df-import-file').click());
        
        $('#accelvia-df-import-dropzone').on('dragover', function(e) {
            e.preventDefault(); $(this).addClass('dragover');
        }).on('dragleave drop', function(e) {
            e.preventDefault(); $(this).removeClass('dragover');
            if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length) {
                handleImportFile(e.originalEvent.dataTransfer.files[0]);
            }
        });

        $('#accelvia-df-import-file').on('change', function() {
            if (this.files.length) handleImportFile(this.files[0]);
        });
    }

    function handleImportFile(file) {
        if (!file.name.endsWith('.json')) {
            alert('Please select a JSON file.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            $('#accelvia-df-import-status').html('<span class="dashicons dashicons-update spin"></span> Importing...').show();
            
            $.ajax({
                url: accelvia_df.ajax_url,
                method: 'POST',
                data: { action: 'accelvia_df_import_chart', nonce: accelvia_df.nonce, import_data: content },
                success: function(r) {
                    if (r.success) {
                        $('#accelvia-df-import-status').html('<span style="color:#10b981;">' + r.data.message + '</span>');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        $('#accelvia-df-import-status').html('<span style="color:#ef4444;">' + r.data.message + '</span>');
                    }
                },
                error: () => $('#accelvia-df-import-status').html('<span style="color:#ef4444;">Import failed.</span>')
            });
        };
        reader.readAsText(file);
    }

    // ========================================
    // Utilities
    // ========================================
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed'; textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select(); document.execCommand('copy');
        document.body.removeChild(textarea);
        return Promise.resolve();
    }

    function showToast(message, shortcode) {
        const $toast = $('#accelvia-df-toast');
        $toast.find('.accelvia-df-toast-message').text(message);
        $toast.find('.accelvia-df-toast-shortcode').text(shortcode);
        $toast.find('.accelvia-df-copy-btn').attr('data-shortcode', shortcode);
        $toast.show();
        setTimeout(() => $toast.fadeOut(300), 5000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str; return div.innerHTML;
    }

    function escapeAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ========================================
    // Phase 4: Dashboard Builder (Enhanced)
    // ========================================
    function initDashboardBuilder() {
        const $dashBuilder = $('.accelvia-df-dash-builder');
        if (!$dashBuilder.length) return;

        let draggedChart = null;
        const dashboardCharts = {}; // Store ApexCharts instances by widget index

        // Initialize jQuery UI Sortable for reordering widgets
        const $grid = $('#accelvia-df-dash-grid');
        $grid.sortable({
            items: '.accelvia-df-dash-widget',
            handle: '.accelvia-df-dash-widget-header',
            placeholder: 'accelvia-df-dash-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            opacity: 0.8,
            revert: 150,
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
                ui.placeholder.css('grid-column', ui.item.css('grid-column'));
            },
            update: function() {
                // Widgets reordered — row_order will be recalculated on save
            }
        });

        // Populate from localized data if editing
        if (typeof accelvia_df !== 'undefined' && accelvia_df.editing_dashboard) {
            $('#accelvia-df-dashboard-title').val(accelvia_df.editing_dashboard.title || '');
            const layoutData = accelvia_df.editing_dashboard.layout || [];
            const isObject = !Array.isArray(layoutData) && layoutData.widgets;
            const widgets = isObject ? layoutData.widgets : layoutData;
            const settings = isObject ? layoutData.settings : { width: '100%', height: 'auto' };
            
            $('#accelvia-df-dashboard-width').val(settings.width || '100%');
            $('#accelvia-df-dashboard-height').val(settings.height || 'auto');
            
            if (widgets.length > 0) {
                $('#accelvia-df-grid-empty').hide();
                widgets.forEach(widget => {
                    const $pickerItem = $(`.accelvia-df-chart-picker-item[data-chart-id="${widget.chart_id}"]`);
                    if ($pickerItem.length) {
                        addChartToGrid($pickerItem, widget.col_span);
                    }
                });
            }
        }

        // Drag Start from sidebar
        $(document).on('dragstart', '.accelvia-df-chart-picker-item', function(e) {
            draggedChart = $(this);
            e.originalEvent.dataTransfer.effectAllowed = 'copy';
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('chart-id'));
            $grid.addClass('drag-active');
        });

        $(document).on('dragend', '.accelvia-df-chart-picker-item', function() {
            $grid.removeClass('drag-active');
            draggedChart = null;
        });

        // Drop zone events
        $grid.on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
        });

        $grid.on('drop', function(e) {
            e.preventDefault();
            if (draggedChart) {
                $('#accelvia-df-grid-empty').hide();
                addChartToGrid(draggedChart);
            }
        });

        // Click to add
        $(document).on('click', '.accelvia-df-picker-add', function(e) {
            e.preventDefault();
            $('#accelvia-df-grid-empty').hide();
            addChartToGrid($(this).closest('.accelvia-df-chart-picker-item'));
        });

        // Remove from grid
        $(document).on('click', '.accelvia-df-dash-widget-remove', function() {
            const $widget = $(this).closest('.accelvia-df-dash-widget');
            const widgetKey = $widget.attr('data-widget-key');
            // Destroy ApexCharts instance
            if (dashboardCharts[widgetKey]) {
                try { dashboardCharts[widgetKey].destroy(); } catch (e) {}
                delete dashboardCharts[widgetKey];
            }
            $widget.remove();
            if ($grid.find('.accelvia-df-dash-widget').length === 0) {
                $('#accelvia-df-grid-empty').show();
            }
        });

        // Resize column span — re-render chart preview
        $(document).on('change', '.accelvia-df-dash-widget-span', function() {
            const span = parseInt($(this).val(), 10) || 6;
            const $widget = $(this).closest('.accelvia-df-dash-widget');
            $widget.css('grid-column', `span ${span}`);
            
            // Re-render chart preview after resize
            const widgetKey = $widget.attr('data-widget-key');
            if (dashboardCharts[widgetKey]) {
                setTimeout(() => {
                    try { dashboardCharts[widgetKey].destroy(); } catch (e) {}
                    delete dashboardCharts[widgetKey];
                    renderWidgetPreview($widget);
                }, 100);
            }
        });

        let widgetCounter = 0;

        function addChartToGrid($item, span) {
            span = span || 6;
            widgetCounter++;
            const chartId = $item.data('chart-id');
            const title = $item.data('chart-title');
            const type = $item.data('chart-type');
            const widgetKey = 'dw-' + widgetCounter;
            
            const html = `
                <div class="accelvia-df-dash-widget" data-chart-id="${chartId}" data-widget-key="${widgetKey}" style="grid-column: span ${span};">
                    <div class="accelvia-df-dash-widget-header">
                        <span class="accelvia-df-dash-widget-drag"><span class="dashicons dashicons-move"></span></span>
                        <span class="accelvia-df-dash-widget-title"><strong>${escapeHtml(title)}</strong> <small>(${escapeHtml(type)})</small></span>
                        <div class="accelvia-df-dash-widget-controls">
                            <select class="accelvia-df-dash-widget-span" title="Column width">
                                <option value="12" ${span===12?'selected':''}>Full (12)</option>
                                <option value="8" ${span===8?'selected':''}>2/3 (8)</option>
                                <option value="6" ${span===6?'selected':''}>Half (6)</option>
                                <option value="4" ${span===4?'selected':''}>1/3 (4)</option>
                                <option value="3" ${span===3?'selected':''}>1/4 (3)</option>
                            </select>
                            <button type="button" class="accelvia-df-dash-widget-remove" title="Remove">&times;</button>
                        </div>
                    </div>
                    <div class="accelvia-df-dash-widget-preview" id="dash-preview-${widgetKey}">
                        <div class="accelvia-df-loading-skeleton" style="height:180px;"></div>
                    </div>
                </div>
            `;
            $grid.append(html);
            $grid.sortable('refresh');

            // Render live chart preview
            const $widget = $grid.find(`[data-widget-key="${widgetKey}"]`);
            setTimeout(() => renderWidgetPreview($widget), 50);
        }

        function renderWidgetPreview($widget) {
            const chartId = $widget.data('chart-id');
            const widgetKey = $widget.attr('data-widget-key');
            const $container = $widget.find('.accelvia-df-dash-widget-preview');

            if (!chartId || typeof accelvia_df === 'undefined' || !accelvia_df.chart_configs) {
                $container.html('<p style="text-align:center;color:#9ca3af;padding:30px 0;">No preview</p>');
                return;
            }

            const config = accelvia_df.chart_configs[chartId];
            if (!config) {
                $container.html('<p style="text-align:center;color:#9ca3af;padding:30px 0;">Chart #' + chartId + '</p>');
                return;
            }

            // Clone config for preview (smaller height, no toolbar)
            const previewConfig = JSON.parse(JSON.stringify(config));
            previewConfig.chart = previewConfig.chart || {};
            previewConfig.chart.height = 200;
            previewConfig.chart.width = '100%';
            previewConfig.chart.toolbar = { show: false };
            previewConfig.chart.sparkline = { enabled: false };
            previewConfig.chart.background = 'transparent';
            previewConfig.theme = { mode: 'dark' };
            previewConfig.grid = previewConfig.grid || {};
            previewConfig.grid.borderColor = '#2d3148';

            const containerWidth = $container[0].getBoundingClientRect().width || $container[0].offsetWidth;
            if (containerWidth > 0 && containerWidth < 400) {
                previewConfig.legend = previewConfig.legend || {};
                previewConfig.legend.position = 'bottom';
            }

            $container.html('');

            try {
                const chart = new ApexCharts($container[0], previewConfig);
                chart.render();
                dashboardCharts[widgetKey] = chart;
            } catch (e) {
                $container.html('<p style="text-align:center;color:#ef4444;padding:30px 0;">Preview error</p>');
            }
        }

        // Save Dashboard
        $('#accelvia-df-save-dashboard').on('click', function() {
            const $btn = $(this);
            const title = $('#accelvia-df-dashboard-title').val().trim();
            const dashId = $('#accelvia-df-dashboard-id').val();
            const widgets = $grid.find('.accelvia-df-dash-widget');

            if (!title) {
                alert('Please enter a dashboard title.');
                $('#accelvia-df-dashboard-title').focus();
                return;
            }

            if (widgets.length === 0) {
                alert(accelvia_df.i18n.dash_no_charts);
                return;
            }

            const layoutWidgets = [];
            widgets.each(function(index) {
                const span = parseInt($(this).find('.accelvia-df-dash-widget-span').val(), 10) || 6;
                layoutWidgets.push({
                    chart_id: $(this).data('chart-id'),
                    col_start: 1,
                    col_span: span,
                    row_order: index
                });
            });

            const layoutObj = {
                settings: {
                    width: $('#accelvia-df-dashboard-width').val() || '100%',
                    height: $('#accelvia-df-dashboard-height').val() || 'auto'
                },
                widgets: layoutWidgets
            };

            $btn.addClass('loading').prop('disabled', true);

            $.post(accelvia_df.ajax_url, {
                action: 'accelvia_df_save_dashboard',
                nonce: accelvia_df.nonce,
                dashboard_id: dashId,
                title: title,
                layout: JSON.stringify(layoutObj)
            }, function(res) {
                $btn.removeClass('loading').prop('disabled', false);
                if (res.success) {
                    showToast(res.data.message, res.data.shortcode);
                    if (!dashId && res.data.dashboard_id) {
                        const newUrl = window.location.href + '&dashboard_id=' + res.data.dashboard_id;
                        window.history.pushState({path: newUrl}, '', newUrl);
                        $('#accelvia-df-dashboard-id').val(res.data.dashboard_id);
                    }
                } else {
                    alert(res.data?.message || accelvia_df.i18n.dash_save_error);
                }
            }).fail(function() {
                $btn.removeClass('loading').prop('disabled', false);
                alert('Server error.');
            });
        });
    }

    // ========================================
    // Phase 3: Dashboard List
    // ========================================
    function initDashboardList() {
        // Delete Dashboard
        $(document).on('click', '.accelvia-df-delete-dashboard-btn', function(e) {
            e.preventDefault();
            const dashId = $(this).data('dashboard-id');
            const $card = $(this).closest('.accelvia-df-chart-card');

            if (!confirm(accelvia_df.i18n.dash_delete_confirm)) return;

            const $btn = $(this);
            $btn.prop('disabled', true).css('opacity', '0.5');

            $.post(accelvia_df.ajax_url, {
                action: 'accelvia_df_delete_dashboard',
                nonce: accelvia_df.nonce,
                dashboard_id: dashId
            }, function(res) {
                if (res.success) {
                    $card.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(res.data?.message || 'Error deleting dashboard.');
                    $btn.prop('disabled', false).css('opacity', '1');
                }
            }).fail(function() {
                alert('Server error.');
                $btn.prop('disabled', false).css('opacity', '1');
            });
        });
    }

})(jQuery);

/* Inline spin animation */
var accelviaDfStyle = document.createElement('style');
accelviaDfStyle.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(accelviaDfStyle);
