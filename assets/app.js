/**
 * BBMS — Core Frontend Controller
 * Theme, API client with localStorage fallback, toasts, modals, clock.
 */

const BLOOD_GROUPS = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(String(d).replace(' ','T'));
    if (isNaN(dt)) return esc(d);
    return dt.toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'})
        + ' · ' + dt.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
}

/* ── Theme ──────────────────────────────────────────────────── */
(function(){
    const t = localStorage.getItem('bbms_theme') || localStorage.getItem('ibbms_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
})();

function toggleTheme() {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('bbms_theme', next);
    updateThemeIcons();
}

function updateThemeIcons() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('.theme-btn').forEach(b => {
        b.title = dark ? 'Light mode' : 'Dark mode';
        b.innerHTML = dark
            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>'
            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
    });
}

/* ── Toasts ─────────────────────────────────────────────────── */
function showAlert(msg, type = 'info') {
    let c = document.querySelector('.toast-container');
    if (!c) { c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); }
    const t = document.createElement('div');
    const cls = type === 'error' || type === 'danger' ? 'error' : type === 'success' ? 'success' : 'info';
    t.className = `toast toast-${cls}`;
    t.innerHTML = `<span style="flex:1">${esc(msg)}</span><button onclick="this.parentElement.remove()" style="background:none;border:none;font-size:1.1rem;color:var(--text-muted);cursor:pointer">&times;</button>`;
    c.appendChild(t);
    setTimeout(() => { if (t.parentElement) { t.style.opacity='0'; t.style.transition='opacity .2s'; setTimeout(()=>t.remove(),200); } }, 4000);
}

/* ── Confirm Modal ──────────────────────────────────────────── */
function confirmModal({ title='Confirm', message='Are you sure?', confirmText='Confirm', confirmClass='btn-primary', onConfirm }) {
    let ov = document.getElementById('globalModal');
    if (!ov) { ov = document.createElement('div'); ov.id = 'globalModal'; ov.className = 'modal-overlay'; document.body.appendChild(ov); }
    ov.innerHTML = `
        <div class="modal-box">
            <h3>${esc(title)}</h3>
            <p>${esc(message)}</p>
            <div class="modal-actions">
                <button class="btn btn-secondary btn-sm" id="mCancel">Cancel</button>
                <button class="btn ${esc(confirmClass)} btn-sm" id="mConfirm">${esc(confirmText)}</button>
            </div>
        </div>`;
    ov.classList.add('active');
    const close = () => ov.classList.remove('active');
    ov.querySelector('#mCancel').onclick = close;
    ov.querySelector('#mConfirm').onclick = async () => { close(); if (onConfirm) await onConfirm(); };
}

/* ── API Client with localStorage fallback ──────────────────── */
const BBMS = {
    apiPath() {
        const p = location.pathname;
        return (p.includes('/admin/') || p.includes('/staff/') || p.includes('/medical_staff/'))
            ? '../api/index.php' : 'api/index.php';
    },
    rootPrefix() {
        const p = location.pathname;
        return (p.includes('/admin/') || p.includes('/staff/') || p.includes('/medical_staff/')) ? '../' : '';
    },

    seedStore() {
        const defaultUsers = [
            {user_id:1,username:'admin',password:'admin123',role:'Admin',created_at:'2026-10-01 09:00:00'},
            {user_id:2,username:'labstaff',password:'staff123',role:'Staff',created_at:'2026-10-01 09:15:00'},
            {user_id:3,username:'doctor',password:'doctor123',role:'Medical Staff',created_at:'2026-10-01 09:30:00'}
        ];
        const defaultInventory = {'A+':14,'A-':4,'B+':18,'B-':3,'AB+':8,'AB-':2,'O+':22,'O-':6};
        const defaultDonors = [
            {donor_id:1,donor_card_no:'DONOR-10482',name:'Aarav Sharma',blood_group:'O+',contact:'9876543210',created_at:'2026-10-10 10:20:00'},
            {donor_id:2,donor_card_no:'DONOR-20915',name:'Priya Nair',blood_group:'A+',contact:'9823456712',created_at:'2026-10-11 11:45:00'},
            {donor_id:3,donor_card_no:'DONOR-38410',name:'Rohan Verma',blood_group:'B+',contact:'9811223344',created_at:'2026-10-12 14:10:00'},
            {donor_id:4,donor_card_no:'DONOR-49201',name:'Ananya Iyer',blood_group:'O-',contact:'9765432109',created_at:'2026-10-12 16:30:00'},
            {donor_id:5,donor_card_no:'DONOR-57390',name:'Vikram Rathore',blood_group:'AB+',contact:'9898765432',created_at:'2026-10-13 08:15:00'}
        ];
        const defaultRequests = [
            {request_id:101,requested_by:3,username:'doctor',blood_group:'O+',units_requested:2,status:'Accepted',request_date:'2026-10-11 13:20:00'},
            {request_id:102,requested_by:3,username:'doctor',blood_group:'A-',units_requested:1,status:'Pending',request_date:'2026-10-12 15:40:00'},
            {request_id:103,requested_by:3,username:'doctor',blood_group:'B+',units_requested:3,status:'Accepted',request_date:'2026-10-12 18:05:00'},
            {request_id:104,requested_by:3,username:'doctor',blood_group:'O-',units_requested:2,status:'Pending',request_date:'2026-10-13 09:10:00'},
            {request_id:105,requested_by:3,username:'doctor',blood_group:'AB-',units_requested:4,status:'Rejected',request_date:'2026-10-10 11:00:00'}
        ];

        let s = null;
        const raw = localStorage.getItem('bbms_data') || localStorage.getItem('ibbms_data');
        if (raw) {
            try { s = JSON.parse(raw); } catch(e){}
        }
        if (!s || typeof s !== 'object') {
            s = {};
        }
        if (!Array.isArray(s.users) || s.users.length === 0) s.users = defaultUsers;
        if (!s.inventory || typeof s.inventory !== 'object') s.inventory = defaultInventory;
        if (!Array.isArray(s.donors)) s.donors = defaultDonors;
        if (!Array.isArray(s.requests)) s.requests = defaultRequests;
        if (!('session' in s)) s.session = null;

        localStorage.setItem('bbms_data', JSON.stringify(s));
        return s;
    },
    save(s) { localStorage.setItem('bbms_data', JSON.stringify(s)); },

    async call(action, payload={}) {
        try {
            const r = await fetch(`${this.apiPath()}?action=${encodeURIComponent(action)}`,{
                method:'POST', headers:{'Content-Type':'application/json'}, credentials:'same-origin',
                body: JSON.stringify({action,...payload})
            });
            const j = JSON.parse(await r.text());
            if (j && typeof j.ok === 'boolean') return j;
        } catch(e){}
        return this.local(action, payload);
    },

    local(action, payload={}) {
        const s = this.seedStore();
        const now = new Date().toISOString().slice(0,19).replace('T',' ');

        if (action === 'session')
            return {ok:true, logged_in:!!s.session, user:s.session};

        if (action === 'login') {
            const u = (payload.username||'').trim(), p = payload.password||'';
            const users = Array.isArray(s.users) ? s.users : [];
            const f = users.find(x => x.username.toLowerCase()===u.toLowerCase() && x.password===p);
            if (!f) return {ok:false, error:'Invalid username or password.'};
            s.session = {user_id:f.user_id, username:f.username, role:f.role};
            this.save(s);
            const routes = {'Admin':'admin/dashboard.html','Staff':'staff/dashboard.html','Medical Staff':'medical_staff/dashboard.html'};
            return {ok:true, user:s.session, redirect:routes[f.role]||'index.html'};
        }
        if (action === 'logout') {
            s.session = null; this.save(s);
            return {ok:true, redirect:this.rootPrefix()+'index.html?msg=logged_out'};
        }

        // Auto-infer session from URL folder for static preview
        if (!s.session) {
            const p = location.pathname;
            if (p.includes('/admin/')) s.session = {user_id:1,username:'admin',role:'Admin'};
            else if (p.includes('/staff/')) s.session = {user_id:2,username:'labstaff',role:'Staff'};
            else if (p.includes('/medical_staff/')) s.session = {user_id:3,username:'doctor',role:'Medical Staff'};
            this.save(s);
        }

        if (action === 'data') {
            const role = s.session ? s.session.role : 'Admin';
            const uid = s.session ? s.session.user_id : 1;
            const inventory = s.inventory || {};
            const totalStock = Object.values(inventory).reduce((a,b)=>a+b,0);
            const allReqs = Array.isArray(s.requests) ? s.requests : [];
            const reqs = role==='Medical Staff' ? allReqs.filter(r=>r.requested_by===uid) : allReqs;
            const counts = {All:reqs.length,Pending:0,Accepted:0,Rejected:0,Cancelled:0};
            reqs.forEach(r => { if (counts[r.status]!==undefined) counts[r.status]++; });
            const allDonors = Array.isArray(s.donors) ? s.donors : [];
            const allUsers = Array.isArray(s.users) ? s.users : [];
            return {ok:true, user:s.session,
                stats:{total_donors:allDonors.length,total_stock:totalStock,today_donations:2,total_users:allUsers.length,counts},
                inventory:inventory,
                requests:[...reqs].sort((a,b)=>b.request_id-a.request_id),
                donors:[...allDonors].sort((a,b)=>b.donor_id-a.donor_id),
                users:allUsers
            };
        }
        if (action === 'add_user') {
            if (!Array.isArray(s.users)) s.users = [];
            const u=(payload.username||'').trim(), p=payload.password||'', r=payload.role||'';
            if (u.length<3||p.length<4||!['Admin','Staff','Medical Staff'].includes(r))
                return {ok:false,error:'Provide valid username (3+), password (4+), and role.'};
            if (s.users.some(x=>x.username.toLowerCase()===u.toLowerCase()))
                return {ok:false,error:'Username already exists.'};
            const id = s.users.reduce((m,x)=>Math.max(m,x.user_id),0)+1;
            s.users.push({user_id:id,username:u,password:p,role:r,created_at:now});
            this.save(s);
            return {ok:true,message:`User '${u}' (${r}) created.`};
        }
        if (action === 'delete_user') {
            if (!Array.isArray(s.users)) s.users = [];
            const id = Number(payload.user_id);
            if (s.session && id===s.session.user_id) return {ok:false,error:'Cannot delete your own account.'};
            s.users = s.users.filter(u=>u.user_id!==id);
            this.save(s);
            return {ok:true,message:`User #${id} deleted.`};
        }
        if (action === 'handle_request') {
            const id=Number(payload.request_id), d=payload.decision;
            const req = s.requests.find(r=>r.request_id===id);
            if (!req||req.status!=='Pending') return {ok:false,error:'Request not found or already processed.'};
            if (d==='accept') {
                const avail = s.inventory[req.blood_group]||0;
                if (avail<req.units_requested) return {ok:false,error:`Insufficient ${req.blood_group} stock.`};
                s.inventory[req.blood_group] -= req.units_requested;
                req.status = 'Accepted';
            } else { req.status = 'Rejected'; }
            this.save(s);
            return {ok:true,message:`Request #${id} ${d==='accept'?'approved':'rejected'}.`};
        }
        if (action === 'register_donor') {
            const name=(payload.name||'').trim(), bg=payload.blood_group||'', contact=(payload.contact||'').trim();
            if (!name||!BLOOD_GROUPS.includes(bg)||!/^[0-9]{10}$/.test(contact))
                return {ok:false,error:'Enter valid name, blood group, and 10-digit contact.'};
            const card = 'DONOR-'+Math.floor(10000+Math.random()*90000);
            const id = s.donors.reduce((m,d)=>Math.max(m,d.donor_id),0)+1;
            s.donors.unshift({donor_id:id,donor_card_no:card,name,blood_group:bg,contact,created_at:now});
            this.save(s);
            return {ok:true,donor_card_no:card,message:`Donor '${name}' registered! Card: ${card}`};
        }
        if (action === 'lookup_donor') {
            const card = (payload.donor_card_no||'').trim().toUpperCase();
            const d = s.donors.find(x=>x.donor_card_no.toUpperCase()===card);
            return d ? {ok:true,donor:d} : {ok:false,error:`Donor '${card}' not found.`};
        }
        if (action === 'confirm_donation') {
            const card = (payload.donor_card_no||'').trim().toUpperCase();
            const units = Math.max(1,Math.min(5,Number(payload.units||1)));
            const d = s.donors.find(x=>x.donor_card_no.toUpperCase()===card);
            if (!d) return {ok:false,error:'Donor card not found.'};
            s.inventory[d.blood_group] = (s.inventory[d.blood_group]||0)+units;
            this.save(s);
            return {ok:true,blood_group:d.blood_group,message:`Added +${units} unit(s) of ${d.blood_group} from ${d.name}.`};
        }
        if (action === 'request_blood') {
            const bg=payload.blood_group||'', units=Number(payload.units_requested||0);
            if (!BLOOD_GROUPS.includes(bg)||units<1||units>10)
                return {ok:false,error:'Select valid blood group and 1-10 units.'};
            const id = s.requests.reduce((m,r)=>Math.max(m,r.request_id),100)+1;
            const user = s.session||{user_id:3,username:'doctor'};
            s.requests.unshift({request_id:id,requested_by:user.user_id,username:user.username,blood_group:bg,units_requested:units,status:'Pending',request_date:now});
            this.save(s);
            return {ok:true,request_id:id,message:`Request #${id} for ${units} unit(s) of ${bg} submitted.`};
        }
        if (action === 'cancel_request') {
            const id = Number(payload.request_id);
            const req = s.requests.find(r => Number(r.request_id) === id);
            if (!req) return { ok: false, error: 'Request not found.' };
            const role = s.session ? s.session.role : 'Medical Staff';
            const uid = s.session ? s.session.user_id : 3;
            if (role === 'Medical Staff' && req.requested_by && req.requested_by !== uid) {
                return { ok: false, error: 'You can only cancel your own requests.' };
            }
            if (req.status !== 'Pending') {
                return { ok: false, error: `Only Pending requests can be cancelled. Current status is ${req.status}.` };
            }
            req.status = 'Cancelled';
            this.save(s);
            return { ok: true, message: `Request #${id} for ${req.units_requested} unit(s) of ${req.blood_group} has been cancelled.` };
        }
        return {ok:false,error:'Unknown action.'};
    },

    async loadShell(role) {
        const res = await this.call('data');
        if (!res.ok && res.unauthorized) {
            location.href = this.rootPrefix()+'index.html?error=unauthorized'; return null;
        }
        if (res.user) {
            const el = id => document.getElementById(id);
            if (el('navUsername')) el('navUsername').textContent = res.user.username;
            if (el('navRole'))    el('navRole').textContent = res.user.role;
            if (el('navAvatar'))  el('navAvatar').textContent = (res.user.username||'U').slice(0,2).toUpperCase();
        }
        return res;
    }
};

// Backward compatibility alias
const IBBMS = BBMS;
if (typeof window !== 'undefined') {
    window.BBMS = BBMS;
    window.IBBMS = BBMS;
}

async function logout(e) {
    if (e) e.preventDefault();
    const r = await BBMS.call('logout');
    location.href = r.redirect || (BBMS.rootPrefix()+'index.html?msg=logged_out');
}

/* ── Clock ──────────────────────────────────────────────────── */
function startClock() {
    const tick = () => {
        const now = new Date();
        const txt = now.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'2-digit'})
            + ' · ' + now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
        document.querySelectorAll('.live-clock').forEach(el => el.textContent = txt);
    };
    tick(); setInterval(tick, 30000);
}

document.addEventListener('DOMContentLoaded', () => {
    updateThemeIcons();
    startClock();
    document.querySelectorAll('input[type="tel"], input[name="contact"]').forEach(inp => {
        inp.addEventListener('input', () => { inp.value = inp.value.replace(/[^0-9]/g,'').slice(0,10); });
    });
});
