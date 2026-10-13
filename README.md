# Internal Blood Bank Management System (IBBMS)

The **Internal Blood Bank Management System (IBBMS)** is a modern hospital intranet web application designed to digitize blood bank inventory, donor card issuance, and clinical ward blood requisitions.

## Tech Stack & Architecture
- **Frontend (Primary — HTML5, CSS3, ES6 JavaScript):**
  - **Clean Centered Login:** Simple, distraction-free centered rectangle card with quick-fill role shortcuts.
  - **Top Horizontal Navbar:** Concise top menu across all workspaces for streamlined navigation.
  - **11 Pure HTML5 Views:**
    - Entry: `index.html`
    - Administrator: `admin/dashboard.html`, `admin/manage_users.html`, `admin/manage_requests.html`, `admin/reports.html`
    - Lab Staff: `staff/dashboard.html`, `staff/register_donor.html`, `staff/log_donation.html`, `staff/manage_requests.html`
    - Clinicians: `medical_staff/dashboard.html`, `medical_staff/request_blood.html`, `medical_staff/my_requests.html`
  - **Design System ([`assets/styles.css`](assets/styles.css)):** Modern clinical UI with crimson accents, Light & Dark themes, responsive cards, telemetry meters, and clean tables.
  - **Frontend Controller ([`assets/app.js`](assets/app.js)):** Unified API client with automatic offline demo fallback, theme toggling, custom confirmation modals, and toast notifications.
- **Backend (Lightweight PHP API):**
  - [`config/db.php`](config/db.php): Database configuration and schema auto-initialization.
  - [`api/index.php`](api/index.php): Lean JSON API endpoint handling authentication, donor registration, donation intake, and atomic stock transactions.
- **Database:** MySQL (`bbms_db`)

## Core User Roles & Credentials
| Role | Username | Password | Default Workspace |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin123` | `admin/dashboard.html` |
| **Lab Staff** | `labstaff` | `staff123` | `staff/dashboard.html` |
| **Medical Staff** | `doctor` | `doctor123` | `medical_staff/dashboard.html` |

## Quick Start
1. Place this project inside your local web server root (e.g., `htdocs` in XAMPP).
2. Start Apache and MySQL.
3. Open `http://localhost/Blood-Bank-management-system/` in your browser.
*(Note: If running directly without a PHP server, the frontend automatically falls back to local storage simulation so all features can be tested offline).*