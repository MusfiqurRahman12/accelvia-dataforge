# Accelvia DataForge – Development Roadmap

The core foundation of Accelvia DataForge (Phases 1 through 4) establishes a robust, zero-dependency, self-hosted chart and dashboard engine inside WordPress. 

The following roadmap outlines the strategic direction for future premium features, integrations, and enterprise capabilities.

---

## 🚀 Phase 5: Advanced Data Connectors

Currently, data is entered manually or imported via static CSV. Phase 5 will introduce dynamic, auto-syncing data pipelines.

- **SQL Query Builder:** Allow advanced users to write raw `SELECT` queries (with strict read-only sanitization) against the local WordPress database or external MySQL/PostgreSQL databases.
- **REST API JSON Mapper:** Connect to external endpoints, extract nested JSON arrays using dot-notation mapping, and sync the data on a schedule.
- **Google Sheets Integration:** Sync chart data directly from published Google Sheets in real-time.
- **WP-Cron Auto-Sync:** Background processes to automatically refresh data from connected external sources every hour/day.

---

## 📊 Phase 6: Exporting & Reporting

Enhancing the utility of dashboards for agencies and business administrators.

- **PDF / PNG Exporting:** Introduce a frontend button to capture the dashboard grid as a high-resolution image or PDF report using client-side canvas rendering (e.g., `html2canvas` + `jsPDF`).
- **Scheduled Email Reports:** Utilize WP-Cron to generate weekly/monthly dashboard summaries and email them directly to stakeholders.
- **White-Labeling:** Options to remove Accelvia branding, customize loading states, and inject custom CSS globally.

---

## 🔒 Phase 7: Access Control & Deep Interactivity

Making dashboards secure for different tiers of users and highly interactive.

- **Role-Based Access Control (RBAC):** Restrict access to specific dashboards based on WordPress User Roles (e.g., only "Editors" or custom "Clients" can view).
- **Drill-Down Interactivity:** Allow clicking on a specific chart element (like a pie slice or a bar) to dynamically filter another chart on the page or redirect to a detailed secondary dashboard.
- **URL Parameter Filtering:** Allow the frontend filter bar to read from URL parameters (e.g., `?category=Sales&from=2024-01-01`), enabling direct linking to specific data states.

---

## ☁️ Phase 8: SaaS & Enterprise Cloud Sync (PRO Strategy)

Transitioning from a strictly localized plugin to a hybrid SaaS model for high-scale users.

- **Edge Computing Offload:** For extremely large datasets, offer an optional connection to an Accelvia Edge API (e.g., Vercel / Cloudflare Workers) to process data arrays before sending them to the browser, reducing WordPress server load.
- **Fleet Management:** For agencies managing multiple WordPress sites, introduce a centralized SaaS dashboard to push chart configurations and updates to client sites remotely via REST API.
- **Real-Time WebSockets:** Implement live data streaming for operational dashboards (e.g., live server metrics, real-time sales tickers) using WebSockets rather than standard polling.
