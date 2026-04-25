# Accelvia DataForge – Charts & Dashboards for WordPress

**Create beautiful, interactive charts and dashboards directly in WordPress. Powered by ApexCharts. No external server required.**

---

## 🎯 Core Strategy

- ✅ 100% self-hosted (NO external server required)
- ✅ Fully functional in the free version
- ✅ Compatible with WordPress.org guidelines
- ✅ Lightweight, fast, and scalable

---

## Architecture Overview

```
accelvia-dataforge/
├── accelvia-dataforge.php          # Main plugin bootstrap
├── uninstall.php                   # Clean uninstall handler
├── admin/
│   ├── class-accelvia-df-admin.php # Admin controller (menus, AJAX, enqueue)
│   ├── views/
│   │   ├── chart-list.php          # Chart list view
│   │   ├── chart-builder.php       # Chart builder (split-panel + live preview)
│   │   ├── dashboard-list.php      # Dashboard list view
│   │   ├── dashboard-builder.php   # Dashboard builder (drag-and-drop grid)
│   │   └── settings.php           # Global settings
│   └── assets/
│       ├── accelvia-df-admin.css   # Premium dark mode + glassmorphism
│       └── accelvia-df-admin.js    # Chart builder + dashboard builder logic
├── public/
│   ├── class-accelvia-df-public.php # Shortcodes, Gutenberg blocks, rendering
│   └── assets/
│       ├── accelvia-df-public.css   # Frontend themes + scroll animations
│       ├── accelvia-df-public.js    # Chart renderer with IntersectionObserver
│       ├── accelvia-df-dashboard.css # Dashboard grid + filter bar styles
│       └── accelvia-df-dashboard.js  # Dashboard renderer + local filtering
├── includes/
│   ├── class-accelvia-df-db.php         # Database layer (CRUD for charts, dashboards, cache)
│   ├── class-accelvia-df-chart-model.php # Chart config validation, defaults, animation settings
│   ├── class-accelvia-df-csv-parser.php  # CSV import engine
│   └── class-accelvia-df-data-normalizer.php # Data normalization utilities
├── api/
│   └── class-accelvia-df-rest.php   # REST API endpoints
├── modules/
│   └── class-accelvia-df-dashboard-widget.php # WP Dashboard widget
└── assets/
    └── js/
        └── apexcharts.min.js        # Bundled ApexCharts v5.10.6 (522KB)
```

---

## Features

### Chart Builder
- **7 chart types**: Bar, Line, Area, Pie, Donut, Radar, RadialBar
- **Multi-series support** with independent data entry per series
- **CSV import** with drag-and-drop upload + column mapping
- **5 color palettes**: Default, Ocean, Sunset, Midnight, Forest
- **Live preview** with debounced rendering
- **Shortcode output**: `[accelvia_chart id="5"]`
- **Gutenberg block**: `accelvia/dataforge-chart`

### Dashboard Builder (Phase 4)
- **12-column CSS grid** with drag-and-drop layout
- **jQuery UI Sortable** for widget reordering (WP core, zero external deps)
- **Live ApexCharts previews** inside each grid widget
- **Column span controls**: Full (12), 2/3 (8), Half (6), 1/3 (4), 1/4 (3)
- **Shortcode output**: `[accelvia_dashboard id="1"]`
- **Gutenberg block**: `accelvia/dataforge-dashboard`

### Frontend Rendering
- **Scroll-triggered animations** via IntersectionObserver
- **Staggered dashboard widget cascade** (0.1s delay per widget)
- **Local filtering system**:
  - Date range picker (From / To)
  - Category dropdown (auto-populated from chart labels)
  - Reset button
  - Zero page-reload — uses `updateSeries()` / `updateOptions()`
- **Light & Dark themes** with full filter bar theming
- **Responsive**: 12-col → 6-col (tablet) → 1-col (mobile)
- **`prefers-reduced-motion`** accessibility support

### Interactive Animations
- **ApexCharts engine**: Custom easing (`easeinout`), staggered gradual animation (150ms delay), dynamic animation on data updates (350ms)
- **Hover markers**: Line/area charts show expanding dots on hover
- **Expand on click**: Pie/donut slices expand outward when clicked
- **Hover states**: Bars lighten on hover, darken on active click
- **Chart entrance**: Slide-up + fade-in (0.7s cubic-bezier) on scroll
- **Filter bar entrance**: Slides up with 0.2s delay after title
- **Reset button ripple**: Expanding radial background on hover

### Security
- All database queries via `$wpdb->prepare()`
- Recursive config sanitization via `Accelvia_DF_Chart_Model::sanitize_config()`
- All output escaped with `esc_html()`, `esc_attr()`, `esc_html_e()`
- Nonce verification on all AJAX handlers
- `manage_options` capability checks

---

## Shortcode Reference

### Chart
```
[accelvia_chart id="5"]
[accelvia_chart id="5" height="400" theme="dark" colors="#ff6384,#36a2eb" title="show" align="center"]
```

### Dashboard
```
[accelvia_dashboard id="1"]
[accelvia_dashboard id="1" theme="dark"]
```

---

## Build Phases

| Phase | Status | Description |
|-------|--------|-------------|
| Phase 1 | ✅ | Core plugin bootstrap, DB schema, chart CRUD, admin UI, frontend rendering |
| Phase 2 | ✅ | Multi-series, CSV import, 7 chart types, color palettes |
| Phase 3 | ✅ | Dashboard CRUD, dashboard builder, advanced shortcode params |
| Phase 4 | ✅ | jQuery UI Sortable, live chart previews, local filtering, interactive animations |

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- No external server, API, or subscription required

---

## License

GPL-2.0-or-later
