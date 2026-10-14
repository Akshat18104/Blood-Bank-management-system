/**
 * Core Frontend Controller — Blood Bank Management System (BBMS)
 * 
 * This file coordinates client-side functionality across the application:
 * - Theme configuration (locked to dark mode)
 * - Safe HTML escaping to prevent XSS attacks
 * - Real-time date and time formatting
 * - Toast notification popups and confirmation modals
 * - Unified API communication client with automatic offline localStorage fallback
 * - Header shell initialization (user profile, avatar, role tags)
 * - Live clock and input formatting helpers
 */

// Global list of the 8 standard blood group types
const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
if (typeof window !== 'undefined') {
    window.BLOOD_GROUPS = BLOOD_GROUPS;
}

/**
 * Escapes special HTML characters in text to prevent Cross-Site Scripting (XSS).
 * Always wrap user-provided text with esc() before injecting into innerHTML.
 */
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Formats a timestamp into a user-friendly readable date string.
 * Example output: "Oct 12, 2026 · 02:45 PM"
 */
function formatDate(dateVal) {
    if (!dateVal) return '—';
    const dateObj = new Date(String(dateVal).replace(' ', 'T'));
    if (isNaN(dateObj)) return esc(dateVal);
    return dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
        + ' · ' + dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

// -----------------------------------------------------------------------------
// Theme Setup: System is permanently styled in Dark Mode
// -----------------------------------------------------------------------------
(function() {
    document.documentElement.setAttribute('data-theme', 'dark');
    try {
        localStorage.setItem('bbms_theme', 'dark');
    } catch (e) {}
})();

// Legacy theme helpers preserved for compatibility
function toggleTheme() {
    document.documentElement.setAttribute('data-theme', 'dark');
}
function updateThemeIcons() {}

// -----------------------------------------------------------------------------
// Toast Notifications
// Displays a non-intrusive notification bubble on the top-right of the screen
// and automatically fades out after 4 seconds.
// -----------------------------------------------------------------------------
function showAlert(message, type = 'info') {
    // Find or create the toast container element on the page
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    // Determine color class: success (green), error/danger (red), info (blue)
    const toastClass = (type === 'error' || type === 'danger') ? 'error' : (type === 'success' ? 'success' : 'info');

    const toast = document.createElement('div');
    toast.className = `toast toast-${toastClass}`;
    toast.innerHTML = `
        <span style="flex:1">${esc(message)}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:1.1rem;color:var(--text-muted);cursor:pointer">&times;</button>
    `;
    container.appendChild(toast);

    // Auto-dismiss toast after 4 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .2s';
            setTimeout(() => toast.remove(), 200);
        }
    }, 4000);
}

// -----------------------------------------------------------------------------
// Reusable Confirmation Modal
// Prompts the user before executing destructive or irreversible actions
// (like approving blood requests or deleting system accounts).
// -----------------------------------------------------------------------------
function confirmModal({ title = 'Confirm', message = 'Are you sure?', confirmText = 'Confirm', confirmClass = 'btn-primary', onConfirm }) {
    let overlay = document.getElementById('globalModal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'globalModal';
        overlay.className = 'modal-overlay';
        document.body.appendChild(overlay);
    }

    overlay.innerHTML = `
        <div class="modal-box">
            <h3>${esc(title)}</h3>
            <p>${esc(message)}</p>
            <div class="modal-actions">
                <button class="btn btn-secondary btn-sm" id="mCancel">Cancel</button>
                <button class="btn ${esc(confirmClass)} btn-sm" id="mConfirm">${esc(confirmText)}</button>
            </div>
        </div>
    `;
    overlay.classList.add('active');

    const closeModal = () => overlay.classList.remove('active');
    overlay.querySelector('#mCancel').onclick = closeModal;
    overlay.querySelector('#mConfirm').onclick = async () => {
        closeModal();
        if (onConfirm) await onConfirm();
    };
}

// -----------------------------------------------------------------------------
// BBMS Main API Client & Offline Fallback Engine
// 
// When running with XAMPP/MySQL, all calls go directly to api/index.php.
// If the backend server is unreachable or offline, this client seamlessly
// falls back to a browser localStorage store so that demo presentations
// and frontend UI interactions continue working without breaking!
// -----------------------------------------------------------------------------
const BBMS = {
    /**
     * Resolves the relative path to api/index.php based on current folder depth.
     */
    apiPath() {
        const path = location.pathname;
        return (path.includes('/admin/') || path.includes('/staff/') || path.includes('/medical_staff/'))
            ? '../api/index.php' : 'api/index.php';
    },

    /**
     * Resolves relative path prefix to the project root directory.
     */
    rootPrefix() {
        const path = location.pathname;
        return (path.includes('/admin/') || path.includes('/staff/') || path.includes('/medical_staff/')) ? '../' : '';
    },

    /**
     * Seeds initial sample data into localStorage for offline demo readiness.
     */
    seedStore() {
        const defaultUsers = [
            { user_id: 1, username: 'admin', password: 'admin123', role: 'Admin', created_at: '2026-10-01 09:00:00' },
            { user_id: 2, username: 'labstaff', password: 'staff123', role: 'Staff', created_at: '2026-10-01 09:15:00' },
            { user_id: 3, username: 'doctor', password: 'doctor123', role: 'Medical Staff', created_at: '2026-10-01 09:30:00' }
        ];
        const defaultInventory = { 'A+': 14, 'A-': 4, 'B+': 18, 'B-': 3, 'AB+': 8, 'AB-': 2, 'O+': 22, 'O-': 6 };
        const defaultDonors = [
            { donor_id: 1, donor_card_no: 'DONOR-10482', name: 'Aarav Sharma', blood_group: 'O+', contact: '9876543210', created_at: '2026-10-10 10:20:00' },
            { donor_id: 2, donor_card_no: 'DONOR-20915', name: 'Priya Nair', blood_group: 'A+', contact: '9823456712', created_at: '2026-10-11 11:45:00' },
            { donor_id: 3, donor_card_no: 'DONOR-38410', name: 'Rohan Verma', blood_group: 'B+', contact: '9811223344', created_at: '2026-10-12 14:10:00' },
            { donor_id: 4, donor_card_no: 'DONOR-49201', name: 'Ananya Iyer', blood_group: 'O-', contact: '9765432109', created_at: '2026-10-12 16:30:00' },
            { donor_id: 5, donor_card_no: 'DONOR-57390', name: 'Vikram Rathore', blood_group: 'AB+', contact: '9898765432', created_at: '2026-10-13 08:15:00' }
        ];
        const defaultRequests = [
            { request_id: 101, requested_by: 3, username: 'doctor', blood_group: 'O+', units_requested: 2, status: 'Accepted', request_date: '2026-10-11 13:20:00' },
            { request_id: 102, requested_by: 3, username: 'doctor', blood_group: 'A-', units_requested: 1, status: 'Pending', request_date: '2026-10-12 15:40:00' },
            { request_id: 103, requested_by: 3, username: 'doctor', blood_group: 'B+', units_requested: 3, status: 'Accepted', request_date: '2026-10-12 18:05:00' },
            { request_id: 104, requested_by: 3, username: 'doctor', blood_group: 'O-', units_requested: 2, status: 'Pending', request_date: '2026-10-13 09:10:00' },
            { request_id: 105, requested_by: 3, username: 'doctor', blood_group: 'AB-', units_requested: 4, status: 'Rejected', request_date: '2026-10-10 11:00:00' }
        ];

        let store = null;
        const raw = localStorage.getItem('bbms_data') || localStorage.getItem('ibbms_data');
        if (raw) {
            try { store = JSON.parse(raw); } catch (e) {}
        }
        if (!store || typeof store !== 'object') {
            store = {};
        }

        // Initialize missing collections with defaults
        if (!Array.isArray(store.users) || store.users.length === 0) store.users = defaultUsers;
        if (!store.inventory || typeof store.inventory !== 'object') store.inventory = defaultInventory;
        if (!Array.isArray(store.donors)) store.donors = defaultDonors;
        if (!Array.isArray(store.requests)) store.requests = defaultRequests;
        if (!('session' in store)) store.session = null;

        localStorage.setItem('bbms_data', JSON.stringify(store));
        return store;
    },

    /**
     * Saves the modified store object back into localStorage
     */
    save(store) {
        localStorage.setItem('bbms_data', JSON.stringify(store));
    },

    /**
     * Sends an action request to the backend PHP API.
     * If the backend returns a valid response, it is used.
     * If the server is unreachable (offline/no MySQL), it falls back to local().
     */
    async call(action, payload = {}) {
        try {
            const response = await fetch(`${this.apiPath()}?action=${encodeURIComponent(action)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action, ...payload })
            });
            const result = JSON.parse(await response.text());
            if (result && typeof result.ok === 'boolean') {
                return result;
            }
        } catch (err) {
            // Server error or network unavailable: proceed to local offline fallback
        }
        return this.local(action, payload);
    },

    /**
     * Offline local fallback: emulates all backend API operations in localStorage
     */
    local(action, payload = {}) {
        const store = this.seedStore();
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

        // Check active session
        if (action === 'session') {
            return { ok: true, logged_in: !!store.session, user: store.session };
        }

        // Local user login
        if (action === 'login') {
            const username = (payload.username || '').trim();
            const password = payload.password || '';
            const users = Array.isArray(store.users) ? store.users : [];
            const user = users.find(u => u.username.toLowerCase() === username.toLowerCase() && u.password === password);

            if (!user) {
                return { ok: false, error: 'Invalid username or password.' };
            }

            store.session = { user_id: user.user_id, username: user.username, role: user.role };
            this.save(store);

            const routes = {
                'Admin': 'admin/dashboard.html',
                'Staff': 'staff/dashboard.html',
                'Medical Staff': 'medical_staff/dashboard.html'
            };
            return { ok: true, user: store.session, redirect: routes[user.role] || 'index.html' };
        }

        // Local user logout
        if (action === 'logout') {
            store.session = null;
            this.save(store);
            return { ok: true, redirect: this.rootPrefix() + 'index.html?msg=logged_out' };
        }

        // Auto-detect role for direct folder preview if no session exists
        if (!store.session) {
            const path = location.pathname;
            if (path.includes('/admin/')) store.session = { user_id: 1, username: 'admin', role: 'Admin' };
            else if (path.includes('/staff/')) store.session = { user_id: 2, username: 'labstaff', role: 'Staff' };
            else if (path.includes('/medical_staff/')) store.session = { user_id: 3, username: 'doctor', role: 'Medical Staff' };
            this.save(store);
        }

        // Local dashboard data aggregator
        if (action === 'data') {
            const role = store.session ? store.session.role : 'Admin';
            const uid = store.session ? store.session.user_id : 1;
            const inventory = store.inventory || {};
            const totalStock = Object.values(inventory).reduce((acc, val) => acc + val, 0);
            const allRequests = Array.isArray(store.requests) ? store.requests : [];
            const userRequests = (role === 'Medical Staff') ? allRequests.filter(r => r.requested_by === uid) : allRequests;

            const counts = { All: userRequests.length, Pending: 0, Accepted: 0, Rejected: 0 };
            userRequests.forEach(r => {
                if (counts[r.status] !== undefined) counts[r.status]++;
            });

            const allDonors = Array.isArray(store.donors) ? store.donors : [];
            const allUsers = Array.isArray(store.users) ? store.users : [];

            return {
                ok: true,
                user: store.session,
                stats: {
                    total_donors: allDonors.length,
                    total_stock: totalStock,
                    today_donations: 2,
                    total_users: allUsers.length,
                    counts: counts
                },
                inventory: inventory,
                requests: [...userRequests].sort((a, b) => b.request_id - a.request_id),
                donors: [...allDonors].sort((a, b) => b.donor_id - a.donor_id),
                users: allUsers
            };
        }

        // Local add user
        if (action === 'add_user') {
            if (!Array.isArray(store.users)) store.users = [];
            const u = (payload.username || '').trim();
            const p = payload.password || '';
            const r = payload.role || '';

            if (u.length < 3 || p.length < 4 || !['Admin', 'Staff', 'Medical Staff'].includes(r)) {
                return { ok: false, error: 'Provide valid username (3+), password (4+), and role.' };
            }
            if (store.users.some(x => x.username.toLowerCase() === u.toLowerCase())) {
                return { ok: false, error: 'Username already exists.' };
            }

            const newId = store.users.reduce((max, u) => Math.max(max, u.user_id), 0) + 1;
            store.users.push({ user_id: newId, username: u, password: p, role: r, created_at: now });
            this.save(store);
            return { ok: true, message: `User '${u}' (${r}) created.` };
        }

        // Local delete user
        if (action === 'delete_user') {
            if (!Array.isArray(store.users)) store.users = [];
            const userId = Number(payload.user_id);
            if (store.session && userId === store.session.user_id) {
                return { ok: false, error: 'Cannot delete your own account.' };
            }
            store.users = store.users.filter(u => u.user_id !== userId);
            this.save(store);
            return { ok: true, message: `User #${userId} deleted.` };
        }

        // Local handle request (accept or reject)
        if (action === 'handle_request') {
            const reqId = Number(payload.request_id);
            const decision = payload.decision;
            const req = store.requests.find(r => r.request_id === reqId);

            if (!req || req.status !== 'Pending') {
                return { ok: false, error: 'Request not found or already processed.' };
            }

            if (decision === 'accept') {
                const available = store.inventory[req.blood_group] || 0;
                if (available < req.units_requested) {
                    return { ok: false, error: `Insufficient ${req.blood_group} stock.` };
                }
                store.inventory[req.blood_group] -= req.units_requested;
                req.status = 'Accepted';
            } else {
                req.status = 'Rejected';
            }

            this.save(store);
            return { ok: true, message: `Request #${reqId} ${decision === 'accept' ? 'approved' : 'rejected'}.` };
        }

        // Local donor registration
        if (action === 'register_donor') {
            const name = (payload.name || '').trim();
            const bg = payload.blood_group || '';
            const contact = (payload.contact || '').trim();

            if (!name || !BLOOD_GROUPS.includes(bg) || !/^[0-9]{10}$/.test(contact)) {
                return { ok: false, error: 'Enter valid name, blood group, and 10-digit contact.' };
            }

            const cardNo = 'DONOR-' + Math.floor(10000 + Math.random() * 90000);
            const newId = store.donors.reduce((max, d) => Math.max(max, d.donor_id), 0) + 1;
            store.donors.unshift({ donor_id: newId, donor_card_no: cardNo, name, blood_group: bg, contact, created_at: now });
            this.save(store);

            return { ok: true, donor_card_no: cardNo, message: `Donor '${name}' registered! Card: ${cardNo}` };
        }

        // Local donor search by card number
        if (action === 'lookup_donor') {
            const cardNo = (payload.donor_card_no || '').trim().toUpperCase();
            const donor = store.donors.find(d => d.donor_card_no.toUpperCase() === cardNo);
            return donor ? { ok: true, donor } : { ok: false, error: `Donor '${cardNo}' not found.` };
        }

        // Local confirm donation (adds stock units)
        if (action === 'confirm_donation') {
            const cardNo = (payload.donor_card_no || '').trim().toUpperCase();
            const units = Math.max(1, Math.min(5, Number(payload.units || 1)));
            const donor = store.donors.find(d => d.donor_card_no.toUpperCase() === cardNo);

            if (!donor) return { ok: false, error: 'Donor card not found.' };

            store.inventory[donor.blood_group] = (store.inventory[donor.blood_group] || 0) + units;
            this.save(store);

            return { ok: true, blood_group: donor.blood_group, message: `Added +${units} unit(s) of ${donor.blood_group} from ${donor.name}.` };
        }

        // Local submit blood request
        if (action === 'request_blood') {
            const bg = payload.blood_group || '';
            const units = Number(payload.units_requested || 0);

            if (!BLOOD_GROUPS.includes(bg) || units < 1 || units > 10) {
                return { ok: false, error: 'Select valid blood group and 1-10 units.' };
            }

            const newId = store.requests.reduce((max, r) => Math.max(max, r.request_id), 100) + 1;
            const user = store.session || { user_id: 3, username: 'doctor' };
            store.requests.unshift({
                request_id: newId,
                requested_by: user.user_id,
                username: user.username,
                blood_group: bg,
                units_requested: units,
                status: 'Pending',
                request_date: now
            });
            this.save(store);

            return { ok: true, request_id: newId, message: `Request #${newId} for ${units} unit(s) of ${bg} submitted.` };
        }

        return { ok: false, error: 'Unknown action.' };
    },

    /**
     * Initializes the dashboard navigation header:
     * - Checks if user is authenticated
     * - Populates user username, avatar initials, and role label
     */
    async loadShell(role) {
        const res = await this.call('data');
        if (!res.ok && res.unauthorized) {
            location.href = this.rootPrefix() + 'index.html?error=unauthorized';
            return null;
        }

        if (res.user) {
            const el = id => document.getElementById(id);
            if (el('navUsername')) el('navUsername').textContent = res.user.username;
            if (el('navRole')) el('navRole').textContent = res.user.role;
            if (el('navAvatar')) el('navAvatar').textContent = (res.user.username || 'U').slice(0, 2).toUpperCase();
        }
        return res;
    }
};

// Backward-compatibility alias
const IBBMS = BBMS;
if (typeof window !== 'undefined') {
    window.BBMS = BBMS;
    window.IBBMS = BBMS;
}

/**
 * Global logout handler used by nav bar Logout buttons
 */
async function logout(event) {
    if (event) event.preventDefault();
    const res = await BBMS.call('logout');
    location.href = res.redirect || (BBMS.rootPrefix() + 'index.html?msg=logged_out');
}

/**
 * Starts the live dashboard clock, updating every 30 seconds
 */
function startClock() {
    const updateTime = () => {
        const now = new Date();
        const formatted = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit' })
            + ' · ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        document.querySelectorAll('.live-clock').forEach(el => el.textContent = formatted);
    };
    updateTime();
    setInterval(updateTime, 30000);
}

// -----------------------------------------------------------------------------
// DOM Ready Event Listener
// Initializes clock and applies numeric restriction on contact phone numbers
// -----------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    updateThemeIcons();
    startClock();

    // Restrict phone number inputs to 10 numerical digits
    document.querySelectorAll('input[type="tel"], input[name="contact"]').forEach(input => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    });
});
