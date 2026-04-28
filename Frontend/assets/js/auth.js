// ============================================
// WOOBLE JOBS — AUTH JS
// Handles: Login, Register, Logout
// ============================================

// ── Handle Login ──────────────────────────
async function handleLogin(e) {
    e.preventDefault();

    const email    = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const btn      = document.getElementById("loginBtn");

    if (!email || !password) {
        showAlert("authAlert", "Email and password are required", "danger");
        return;
    }

    btn.innerHTML = '<span class="wb-spinner"></span> Signing in...';
    btn.disabled  = true;

    const { status, data } = await apiRequest("/auth/login", "POST", {
        email, password
    });

    btn.innerHTML = "Sign In";
    btn.disabled  = false;

    if (!data.success) {
        showAlert("authAlert", data.message, "danger");
        return;
    }

    // Save token & user
    Auth.setToken(data.data.token);
    Auth.setUser(data.data.user);

    showAlert("authAlert", "Login successful! Redirecting...", "success");

    setTimeout(() => {
    const user = Auth.getUser();
    if (user.role === "company") {
        window.location.href = "/frontend/company/dashboard.html";
    } else if (user.role === "employer") {
        window.location.href = "/frontend/employer/dashboard.html";
    } else {
        window.location.href = "/frontend/candidate/dashboard.html";
    }
}, 1000);
}

// ── Handle Register ────────────────────────
async function handleRegister(e) {
    e.preventDefault();

    const name            = document.getElementById("name").value.trim();
    const email           = document.getElementById("email").value.trim();
    const phone           = document.getElementById("phone").value.trim();
    const password        = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;
    const role            = document.getElementById("selectedRole").value;
    const btn             = document.getElementById("registerBtn");

    // Validate
    if (!name || !email || !password) {
        showAlert("authAlert", "Name, email and password are required", "danger");
        return;
    }

    if (password.length < 6) {
        showAlert("authAlert", "Password must be at least 6 characters", "danger");
        return;
    }

    if (password !== confirmPassword) {
        showAlert("authAlert", "Passwords do not match", "danger");
        return;
    }

    btn.innerHTML = '<span class="wb-spinner"></span> Creating account...';
    btn.disabled  = true;

    const { status, data } = await apiRequest("/auth/register", "POST", {
        name, email, password, role, phone
    });

    btn.innerHTML = "Create Account";
    btn.disabled  = false;

    if (!data.success) {
        showAlert("authAlert", data.message, "danger");
        return;
    }

    // Save token & user
    Auth.setToken(data.data.token);
    Auth.setUser(data.data.user);

    showAlert("authAlert", "Account created! Redirecting...", "success");

    setTimeout(() => {
    const user = Auth.getUser();
    if (user.role === "company") {
        window.location.href = "/frontend/company/dashboard.html";
    } else if (user.role === "employer") {
        window.location.href = "/frontend/employer/dashboard.html";
    } else {
        window.location.href = "/frontend/candidate/dashboard.html";
    }
}, 1200);
}

// ── Toggle Password Visibility ─────────────
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type    = "text";
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        input.type    = "password";
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}

// ── Password Strength Checker ──────────────
function checkPasswordStrength(inputId) {
    const val  = document.getElementById(inputId).value;
    const bar  = document.getElementById("strengthBar");
    const lbl  = document.getElementById("strengthLabel");
    const wrap = document.getElementById("passwordStrength");

    if (!val) {
        wrap.style.display = "none";
        return;
    }

    wrap.style.display = "block";

    let score = 0;
    if (val.length >= 6)             score++;
    if (val.length >= 10)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;

    const levels = [
        { w: "20%",  color: "#dc2626", label: "Very Weak" },
        { w: "40%",  color: "#f97316", label: "Weak" },
        { w: "60%",  color: "#eab308", label: "Fair" },
        { w: "80%",  color: "#22c55e", label: "Strong" },
        { w: "100%", color: "#16a34a", label: "Very Strong" }
    ];

    const level          = levels[Math.min(score, 4)];
    bar.style.width      = level.w;
    bar.style.background = level.color;
    lbl.textContent      = level.label;
    lbl.style.color      = level.color;
}

// ── Role Selector ──────────────────────────
function selectRole(role, el) {
    document.getElementById("selectedRole").value = role;
    document.querySelectorAll(".role-option").forEach(o => {
        o.classList.remove("selected");
    });
    el.classList.add("selected");
}


// ── Redirect if Already Logged In ─────────
// ── Redirect if Already Logged In ─────────
document.addEventListener("DOMContentLoaded", () => {
    const path = window.location.pathname;
    const isAuthPage = path.includes("login.html") || path.includes("register.html");

    if (isAuthPage && Auth.isLoggedIn()) {
        const user = Auth.getUser();
        if (user.role === "company") {
            window.location.href = "/frontend/company/dashboard.html";
        } else if (user.role === "employer") {
            window.location.href = "/frontend/employer/dashboard.html";
        } else {
            window.location.href = "/frontend/candidate/dashboard.html";
        }
    }
});