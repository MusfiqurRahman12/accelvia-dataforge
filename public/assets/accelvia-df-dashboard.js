/**
 * Accelvia DataForge – Frontend Dashboard Renderer
 *
 * Vanilla JS. Renders all charts in a dashboard grid and
 * implements local filtering (date range + category dropdown)
 * using ApexCharts native updateSeries/updateOptions.
 *
 * @package Accelvia_DataForge
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initDashboards();
    });

    /**
     * Find and initialize all dashboard containers.
     */
    function initDashboards() {
        var dashboards = document.querySelectorAll('.accelvia-df-dashboard-wrapper');
        if (!dashboards.length) return;

        dashboards.forEach(function (wrapper) {
            initDashboard(wrapper);
        });
    }

    /**
     * Initialize a single dashboard: render charts + bind filters.
     *
     * @param {Element} wrapper The dashboard wrapper element.
     */
    function initDashboard(wrapper) {
        var charts = wrapper.querySelectorAll('.accelvia-df-chart');
        var chartInstances = [];
        var originalConfigs = [];

        // Render each chart
        charts.forEach(function (container) {
            try {
                var configStr = container.getAttribute('data-chart-config');
                if (!configStr) return;

                var config = JSON.parse(configStr);
                config.chart = config.chart || {};
                config.chart.width = '100%';

                // Enrich animation config for interactive feel
                config.chart.animations = config.chart.animations || {};
                config.chart.animations.enabled = true;
                config.chart.animations.easing = config.chart.animations.easing || 'easeinout';
                config.chart.animations.speed = config.chart.animations.speed || 800;
                if (!config.chart.animations.animateGradually) {
                    config.chart.animations.animateGradually = { enabled: true, delay: 150 };
                }
                if (!config.chart.animations.dynamicAnimation) {
                    config.chart.animations.dynamicAnimation = { enabled: true, speed: 350 };
                }

                // Remove skeleton
                var skeleton = container.querySelector('.accelvia-df-loading-skeleton');
                if (skeleton) skeleton.remove();

                // Store original config for filtering
                var originalConfig = JSON.parse(JSON.stringify(config));
                originalConfigs.push(originalConfig);

                var chart = new ApexCharts(container, config);
                chart.render();
                chartInstances.push(chart);
                container._accelvia_chart = chart;
                container._accelvia_original_config = originalConfig;

            } catch (e) {
                console.error('Accelvia DataForge Dashboard: Chart render error', e);
                container.innerHTML = '<div class="accelvia-df-error"><p>Unable to render chart.</p></div>';
                chartInstances.push(null);
                originalConfigs.push(null);
            }
        });

        // Bind filter controls if present
        initFilters(wrapper, charts, chartInstances, originalConfigs);
    }

    /**
     * Initialize dashboard filter bar (date range + category dropdown).
     *
     * @param {Element}   wrapper         Dashboard wrapper.
     * @param {NodeList}   containers      Chart containers.
     * @param {Array}      chartInstances  ApexCharts instances.
     * @param {Array}      originalConfigs Original chart configs (for reset).
     */
    function initFilters(wrapper, containers, chartInstances, originalConfigs) {
        var filterBar = wrapper.querySelector('.accelvia-df-filter-bar');
        if (!filterBar) return;

        var dateFrom = filterBar.querySelector('#accelvia-df-filter-date-from');
        var dateTo   = filterBar.querySelector('#accelvia-df-filter-date-to');
        var catSelect = filterBar.querySelector('#accelvia-df-filter-category');
        var resetBtn = filterBar.querySelector('.accelvia-df-filter-reset');

        // Listen to filter changes
        if (dateFrom) dateFrom.addEventListener('change', applyFilters);
        if (dateTo)   dateTo.addEventListener('change', applyFilters);
        if (catSelect) catSelect.addEventListener('change', applyFilters);
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);

        function applyFilters() {
            var fromVal = dateFrom ? dateFrom.value : '';
            var toVal   = dateTo   ? dateTo.value   : '';
            var catVal  = catSelect ? catSelect.value : '';

            chartInstances.forEach(function (chart, i) {
                if (!chart || !originalConfigs[i]) return;

                var cfg = originalConfigs[i];
                var isPie = isPieType(cfg.chart.type);

                if (isPie) {
                    applyPieFilter(chart, cfg, catVal);
                } else {
                    applyCategoryFilter(chart, cfg, fromVal, toVal, catVal);
                }
            });
        }

        function resetFilters() {
            if (dateFrom) dateFrom.value = '';
            if (dateTo)   dateTo.value   = '';
            if (catSelect) catSelect.value = '';

            // Restore original data
            chartInstances.forEach(function (chart, i) {
                if (!chart || !originalConfigs[i]) return;
                var cfg = originalConfigs[i];
                var isPie = isPieType(cfg.chart.type);

                if (isPie) {
                    chart.updateOptions({
                        series: cfg.series,
                        labels: cfg.labels
                    }, false, true);
                } else {
                    chart.updateOptions({
                        series: cfg.series,
                        xaxis: { categories: cfg.xaxis.categories }
                    }, false, true);
                }
            });
        }
    }

    /**
     * Apply category/date filter to categorical (bar/line/area/radar) chart.
     */
    function applyCategoryFilter(chart, cfg, fromVal, toVal, catVal) {
        var categories = cfg.xaxis && cfg.xaxis.categories ? cfg.xaxis.categories : [];
        var series     = cfg.series || [];

        // Determine which indices to keep
        var indices = [];
        categories.forEach(function (label, idx) {
            var keep = true;

            // Category filter (substring match)
            if (catVal && String(label).toLowerCase().indexOf(catVal.toLowerCase()) === -1) {
                keep = false;
            }

            // Date range filter: treat labels as date-like strings
            if (keep && (fromVal || toVal)) {
                var labelDate = parseSimpleDate(label);
                if (labelDate) {
                    if (fromVal && labelDate < new Date(fromVal)) keep = false;
                    if (toVal && labelDate > new Date(toVal + 'T23:59:59')) keep = false;
                }
            }

            if (keep) indices.push(idx);
        });

        // Build filtered data
        var filteredCategories = indices.map(function (i) { return categories[i]; });
        var filteredSeries = series.map(function (s) {
            if (s && s.data) {
                return { name: s.name, data: indices.map(function (i) { return s.data[i]; }) };
            }
            return s;
        });

        chart.updateOptions({
            xaxis: { categories: filteredCategories },
            series: filteredSeries
        }, false, true);
    }

    /**
     * Apply category filter to pie/donut/radialBar chart.
     */
    function applyPieFilter(chart, cfg, catVal) {
        if (!catVal) {
            chart.updateOptions({
                series: cfg.series,
                labels: cfg.labels
            }, false, true);
            return;
        }

        var labels = cfg.labels || [];
        var series = cfg.series || [];
        var filteredLabels = [];
        var filteredSeries = [];

        labels.forEach(function (label, idx) {
            if (String(label).toLowerCase().indexOf(catVal.toLowerCase()) !== -1) {
                filteredLabels.push(label);
                filteredSeries.push(series[idx]);
            }
        });

        if (filteredLabels.length === 0) {
            filteredLabels = labels;
            filteredSeries = series;
        }

        chart.updateOptions({
            series: filteredSeries,
            labels: filteredLabels
        }, false, true);
    }

    /**
     * Try to parse a string as a date (supports various common formats).
     *
     * @param {string} str
     * @return {Date|null}
     */
    function parseSimpleDate(str) {
        if (!str) return null;

        // Month abbreviations: "Jan", "Feb 2024", "Jan-24", etc.
        var months = {
            jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
            jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11
        };

        var lower = String(str).toLowerCase().trim();

        // "Jan 2024" or "January 2024"
        for (var m in months) {
            if (lower.indexOf(m) === 0) {
                var yearMatch = lower.match(/(\d{4})/);
                var year = yearMatch ? parseInt(yearMatch[1], 10) : new Date().getFullYear();
                return new Date(year, months[m], 1);
            }
        }

        // ISO or standard date strings
        var d = new Date(str);
        if (!isNaN(d.getTime())) return d;

        return null;
    }

    /**
     * Check if chart type is a pie-like type.
     */
    function isPieType(type) {
        return type === 'pie' || type === 'donut' || type === 'radialBar';
    }

})();
