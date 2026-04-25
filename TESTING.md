# Accelvia DataForge – Manual Test Plan

This document outlines the comprehensive manual test cases to verify the integrity, security, and functionality of the Accelvia DataForge plugin. 

## 1. Installation & Initialization
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `INST-01` | Install and activate the plugin on a fresh WordPress install. | Plugin activates without PHP warnings/errors. The database tables `wp_accelvia_df_charts` and `wp_accelvia_df_dashboards` are created. | |
| `INST-02` | Check the WordPress admin menu. | "DataForge" menu appears with submenus: "Charts", "Dashboards", and "Settings". | |
| `INST-03` | Deactivate and uninstall the plugin. | All custom database tables are completely removed (via `uninstall.php`). | |

## 2. Chart Builder
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `CHRT-01` | Create a new basic Bar chart with manual data entry. | Chart saves successfully. Live preview updates dynamically. Shortcode is generated. | |
| `CHRT-02` | Add multiple data series to a Line chart. | The chart successfully renders multiple intersecting lines. Legend displays properly. | |
| `CHRT-03` | Upload a valid CSV file for a chart. | Columns are successfully mapped to X-axis and Series. Data is populated and rendered in the live preview. | |
| `CHRT-04` | Attempt to upload an invalid/malformed CSV file. | The plugin rejects the file and displays a graceful error message in the UI. | |
| `CHRT-05` | Switch between color palettes (Ocean, Sunset, etc.). | The live preview instantly reflects the selected color palette. | |
| `CHRT-06` | Edit an existing chart and save changes. | Changes overwrite the existing database entry. Timestamp updates. | |

## 3. Dashboard Builder (Drag & Drop)
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `DASH-01` | Create a new Dashboard and drag charts from the sidebar. | Charts snap into the 12-column grid. A live preview of the chart loads within the widget. | |
| `DASH-02` | Resize a chart widget (e.g., from Full Width to Half Width). | The chart is dynamically destroyed and re-rendered to fit the new width accurately. | |
| `DASH-03` | Reorder widgets using the drag handle. | jQuery UI Sortable allows smooth reordering with a dashed placeholder. | |
| `DASH-04` | Save the dashboard layout. | Layout JSON is saved to the database. Upon page reload, the exact grid layout and order are restored. | |

## 4. Frontend Rendering & Interactivity
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `FRONT-01` | Embed a Chart shortcode on a standard page. | Chart renders correctly on the frontend with staggered entrance animations. | |
| `FRONT-02` | Embed a Dashboard shortcode on a page. | The 12-column CSS grid displays correctly. Widgets load with staggered cascade animations. | |
| `FRONT-03` | Test IntersectionObserver scroll animations. | Charts positioned lower on the page do not render or animate until scrolled into view. | |
| `FRONT-04` | Use the Dashboard Date Filter (From / To). | Charts dynamically update (without page reload) to show only data falling within the selected dates. | |
| `FRONT-05` | Use the Dashboard Category Filter dropdown. | Pie/Donut charts isolate the selected slice. Bar/Line charts isolate the selected X-axis category. | |
| `FRONT-06` | Test the Filter "Reset" button. | All charts gracefully animate back to their original, unfiltered data states. | |

## 5. Responsiveness & Accessibility
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `RESP-01` | View the frontend dashboard on a mobile device (or resize browser to < 768px). | The 12-column grid collapses into a single column. All charts remain fully readable. | |
| `RESP-02` | Enable `prefers-reduced-motion` in OS settings. | All scroll animations, hover lifts, and chart entrance transitions are disabled immediately. | |

## 6. Security & Hardening
| Test ID | Scenario | Expected Result | Pass/Fail |
|---------|----------|-----------------|-----------|
| `SEC-01` | Attempt to inject `<script>` tags into a chart title or series name. | The input is sanitized and escaped on the frontend (`esc_html`), preventing XSS execution. | |
| `SEC-02` | Submit AJAX requests without a valid nonce. | The server returns a 403 Forbidden or generic failure; no data is modified. | |
