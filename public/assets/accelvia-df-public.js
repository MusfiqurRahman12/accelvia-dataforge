/**
 * Accelvia DataForge – Frontend Chart Renderer
 *
 * Vanilla JS (zero jQuery dependency on frontend).
 * Uses IntersectionObserver for scroll-triggered chart animations —
 * charts only render and animate when they enter the viewport.
 *
 * @package Accelvia_DataForge
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initAllCharts();
    });

    /**
     * Find and initialize all DataForge chart containers on the page.
     * Uses IntersectionObserver for scroll-triggered rendering.
     */
    function initAllCharts() {
        var containers = document.querySelectorAll('.accelvia-df-chart');

        if (!containers.length) {
            return;
        }

        // If IntersectionObserver is available, lazy-render on scroll
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var container = entry.target;
                        if (!container._accelvia_rendered) {
                            container._accelvia_rendered = true;
                            renderChartInContainer(container);
                            observer.unobserve(container);
                        }
                    }
                });
            }, {
                rootMargin: '100px',  // Start rendering 100px before visible
                threshold: 0.1
            });

            containers.forEach(function (container) {
                // Add entrance animation class
                var wrapper = container.closest('.accelvia-df-chart-wrapper');
                if (wrapper) {
                    wrapper.classList.add('accelvia-df-animate-ready');
                }
                observer.observe(container);
            });
        } else {
            // Fallback: render all immediately
            containers.forEach(function (container) {
                renderChartInContainer(container);
            });
        }
    }

    /**
     * Render a single chart inside its container.
     *
     * @param {Element} container The chart container element.
     */
    function renderChartInContainer(container) {
        try {
            var configStr = container.getAttribute('data-chart-config');
            if (!configStr) {
                showError(container, 'No chart configuration found.');
                return;
            }

            var config = JSON.parse(configStr);

            // Ensure the chart renders inside this specific container
            if (!config.chart) {
                config.chart = {};
            }
            config.chart.width = '100%';

            // Enhance animations if enabled
            if (!config.chart.animations || config.chart.animations.enabled !== false) {
                config.chart.animations = config.chart.animations || {};
                config.chart.animations.enabled = true;
                config.chart.animations.easing = config.chart.animations.easing || 'easeinout';
                config.chart.animations.speed = config.chart.animations.speed || 800;

                if (!config.chart.animations.animateGradually) {
                    config.chart.animations.animateGradually = {
                        enabled: true,
                        delay: 150
                    };
                }
                if (!config.chart.animations.dynamicAnimation) {
                    config.chart.animations.dynamicAnimation = {
                        enabled: true,
                        speed: 350
                    };
                }
            }

            // Remove loading skeleton
            var skeleton = container.querySelector('.accelvia-df-loading-skeleton');
            if (skeleton) {
                skeleton.remove();
            }

            // Trigger entrance animation on wrapper
            var wrapper = container.closest('.accelvia-df-chart-wrapper');
            if (wrapper && wrapper.classList.contains('accelvia-df-animate-ready')) {
                wrapper.classList.add('accelvia-df-animate-in');
            }

            // Render ApexChart
            var chart = new ApexCharts(container, config);
            chart.render();

            // Store reference for potential cleanup
            container._accelvia_chart = chart;

        } catch (e) {
            console.error('Accelvia DataForge: Chart render error', e);
            showError(container, 'Unable to render chart.');
        }
    }

    /**
     * Display a graceful error message in the chart container.
     *
     * @param {Element} container The chart container element.
     * @param {string}  message   Error message to display.
     */
    function showError(container, message) {
        container.innerHTML =
            '<div class="accelvia-df-error">' +
            '<p>' + escapeHtml(message) + '</p>' +
            '</div>';
    }

    /**
     * Escape HTML entities to prevent XSS.
     *
     * @param {string} str Input string.
     * @return {string} Escaped string.
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
