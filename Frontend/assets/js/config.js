// ============================================
// WOOBLE JOBS — GLOBAL CONFIG & HELPERS
// ============================================

const API_BASE = "http://localhost/wooble-jobapplication/api";
const FRONTEND_BASE = "http://localhost/wooble-jobapplication"; 

// ── Token Helpers ─────────────────────────
const Auth = {
    setToken: (token) => localStorage.setItem("wooble_token", token),
    getToken: () => localStorage.getItem("wooble_token"),
    removeToken: () => localStorage.removeItem("wooble_token"),

    setUser: (user) => localStorage.setItem("wooble_user", JSON.stringify(user)),
    getUser: () => JSON.parse(localStorage.getItem("wooble_user") || "null"),
    removeUser: () => localStorage.removeItem("wooble_user"),

    isLoggedIn: () => !!localStorage.getItem("wooble_token"),

    isAdmin: () => {
        const user = Auth.getUser();
        return user && user.role === "admin";
    },

    isCandidate: () => {
        const user = Auth.getUser();
        return user && user.role === "candidate";
    },

    logout: () => {
        Auth.removeToken();
        Auth.removeUser();
        window.location.href = "/wooble-jobapplication/frontend/login.html";
    }
};

// ── API Request Helper ────────────────────
async function apiRequest(endpoint, method = "GET", body = null, auth = false) {
    const headers = { "Content-Type": "application/json" };
    if (auth) headers["Authorization"] = `Bearer ${Auth.getToken()}`;

    const options = { method, headers };
    if (body) options.body = JSON.stringify(body);

    try {
        const res  = await fetch(`${API_BASE}${endpoint}`, options);
        const data = await res.json();
        return { status: res.status, data };
    } catch (err) {
        return { status: 500, data: { success: false, message: "Network error. Please try again." } };
    }
}

// ── Form Data API Request (for file uploads) ──
async function apiFormRequest(endpoint, formData) {
    const headers = { "Authorization": `Bearer ${Auth.getToken()}` };
    try {
        const res  = await fetch(`${API_BASE}${endpoint}`, { method: "POST", headers, body: formData });
        const data = await res.json();
        return { status: res.status, data };
    } catch (err) {
        return { status: 500, data: { success: false, message: "Network error. Please try again." } };
    }
}

// ── Alert Helper ──────────────────────────
function showAlert(id, message, type = "danger") {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = `wb-alert wb-alert-${type} show`;
    el.textContent = message;
    setTimeout(() => el.classList.remove("show"), 5000);
}

// ── Format Date ───────────────────────────
function formatDate(dateStr) {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString("en-IN", {
        day: "numeric", month: "short", year: "numeric"
    });
}

// ── Format Salary ─────────────────────────
function formatSalary(min, max) {
    if (!min && !max) return "Not disclosed";
    const fmt = (n) => "₹" + Number(n).toLocaleString("en-IN");
    if (min && max) return `${fmt(min)} – ${fmt(max)}`;
    if (min) return `From ${fmt(min)}`;
    return `Up to ${fmt(max)}`;
}

// ── Status Badge HTML ─────────────────────
function statusBadge(status) {
    const map = {
        pending:     { cls: "wb-badge-yellow", icon: "🕐", label: "Pending" },
        shortlisted: { cls: "wb-badge-blue",   icon: "⭐", label: "Shortlisted" },
        invited:     { cls: "wb-badge-green",   icon: "📅", label: "Invited" },
        rejected:    { cls: "wb-badge-red",     icon: "❌", label: "Rejected" },
        active:      { cls: "wb-badge-green",   icon: "✅", label: "Active" },
        closed:      { cls: "wb-badge-gray",    icon: "🔒", label: "Closed" }
    };
    const s = map[status] || { cls: "wb-badge-gray", icon: "•", label: status };
    return `<span class="wb-badge ${s.cls}">${s.icon} ${s.label}</span>`;
}

// ── Job Type Badge HTML ───────────────────
function jobTypeBadge(type) {
    const map = {
        "full-time": { cls: "wb-badge-blue",   label: "Full Time" },
        "part-time": { cls: "wb-badge-yellow",  label: "Part Time" },
        "remote":    { cls: "wb-badge-green",   label: "Remote" },
        "contract":  { cls: "wb-badge-purple",  label: "Contract" }
    };
    const t = map[type] || { cls: "wb-badge-gray", label: type };
    return `<span class="wb-badge ${t.cls}">${t.label}</span>`;
}

// ── Redirect if not logged in ─────────────
function requireAuth(role = null) {
    if (!Auth.isLoggedIn()) {
        window.location.href = "/wooble-jobapplication/frontend/login.html";
        return false;
    }
    if (role === "admin" && !Auth.isAdmin()) {
        window.location.href = "/wooble-jobapplication/frontend/login.html";
        return false;
    }
    if (role === "candidate" && !Auth.isCandidate()) {
        window.location.href = "/wooble-jobapplication/frontend/login.html";
        return false;
    }
    return true;
}

// ── Navbar user state ─────────────────────
function updateNavbar() {
    const user        = Auth.getUser();
    const guestNav    = document.getElementById("guestNav");
    const userNav     = document.getElementById("userNav");
    const userName    = document.getElementById("navUserName");
    const profileLink = document.getElementById("profileLink");
    const dashLink    = document.getElementById("dashboardLink");

    if (user && Auth.isLoggedIn()) {
        // Hide guest nav
        if (guestNav) guestNav.style.cssText = "display:none!important;";

        // Show user nav
        if (userNav)  userNav.style.cssText  = "display:flex!important;";

        // Set username
        if (userName) userName.textContent = user.name;

        // Set profile link
       if (userName) {
    userName.textContent = user.name;
    userName.style.cursor = "pointer";
    userName.onclick = () => {
        window.location.href = Auth.isAdmin()
            ? "/wooble-jobapplication/frontend/admin/dashboard.html"
            : "/wooble-jobapplication/frontend/candidate/profile.html";
    };
}

        // Set dashboard link
        if (dashLink) {
            dashLink.href = Auth.isAdmin()
                ? "admin/dashboard.html"
                : "candidate/dashboard.html";
        }

    } else {
        // Show guest nav
        if (guestNav) guestNav.style.cssText = "display:flex!important;";

        // Hide user nav
        if (userNav)  userNav.style.cssText  = "display:flex!important; margin-right:16px;";
    }
}

// Run on every page
document.addEventListener("DOMContentLoaded", updateNavbar);