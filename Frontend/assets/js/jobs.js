// ============================================
// WOOBLE JOBS — JOBS JS
// Handles: Job listing, search, filter,
//          single job detail, pagination
// ============================================

let currentPage   = 1;
let debounceTimer = null;
let currentJobId  = null;

// ── Load All Jobs (index.html) ────────────
async function loadJobs(page = 1) {
    currentPage = page;

    const search   = document.getElementById("searchInput")?.value.trim()    || "";
    const type     = document.getElementById("filterType")?.value             || "";
    const location = document.getElementById("filterLocation")?.value.trim() || "";

    let query = `?page=${page}`;
    if (search)   query += `&search=${encodeURIComponent(search)}`;
    if (type)     query += `&type=${type}`;
    if (location) query += `&location=${encodeURIComponent(location)}`;

    // Show skeleton loader
    showSkeletonLoader();

    const { data } = await apiRequest(`/jobs${query}`);

    if (!data.success) {
        document.getElementById("jobsGrid").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">😕</div>
                <h5>Failed to load jobs</h5>
                <p>${data.message}</p>
                <button class="btn-wb-primary mt-3" onclick="loadJobs()">
                    Try Again
                </button>
            </div>`;
        return;
    }

    const { jobs, total, total_pages } = data.data;

    // Update stats bar
    updateStats(jobs, total);

    // Update count label
    const countEl = document.getElementById("jobCount");
    if (countEl) {
        countEl.textContent = `${total} job${total !== 1 ? "s" : ""} found`;
    }

    // Empty state
    if (jobs.length === 0) {
        document.getElementById("jobsGrid").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">🔍</div>
                <h5>No jobs found</h5>
                <p>Try different keywords or clear your filters</p>
                <button class="btn-wb-outline mt-3" onclick="clearFilters()">
                    Clear Filters
                </button>
            </div>`;
        document.getElementById("pagination").innerHTML = "";
        return;
    }

    // Render job cards
    renderJobCards(jobs);

    // Render pagination
    renderPagination(total_pages, page);
}

// ── Render Job Cards ──────────────────────
function renderJobCards(jobs) {
    const grid = document.getElementById("jobsGrid");
    if (!grid) return;

    grid.innerHTML = `
        <div class="row g-3">
            ${jobs.map(job => `
            <div class="col-md-6">
                <div class="wb-job-card"
                     onclick="window.location='job-detail.html?id=${job.id}'">

                    <div class="d-flex gap-3 align-items-start mb-3">
                        <div class="company-logo">
                            ${job.company.charAt(0).toUpperCase()}
                        </div>
                        <div class="flex-grow-1">
                            <div class="job-title">${job.title}</div>
                            <div class="company-name">
                                <i class="bi bi-building"></i> ${job.company}
                            </div>
                        </div>
                    </div>

                    <div class="job-meta">
                        ${jobTypeBadge(job.job_type)}
                        <span class="wb-badge wb-badge-gray">
                            <i class="bi bi-geo-alt"></i> ${job.location}
                        </span>
                        ${job.salary_min ? `
                        <span class="wb-badge wb-badge-green">
                            💰 ${formatSalary(job.salary_min, job.salary_max)}
                        </span>` : ""}
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3"
                         style="border-top:1px solid var(--border);">
                        <span class="text-muted" style="font-size:12px;">
                            <i class="bi bi-clock"></i> ${formatDate(job.created_at)}
                        </span>
                        <span style="font-size:13px;font-weight:600;color:var(--primary);">
                            View Job →
                        </span>
                    </div>

                </div>
            </div>`).join("")}
        </div>`;
}

// ── Update Stats Bar ──────────────────────
function updateStats(jobs, total) {
    const statJobs      = document.getElementById("statJobs");
    const statCompanies = document.getElementById("statCompanies");
    const statRemote    = document.getElementById("statRemote");

    if (statJobs)      statJobs.textContent      = total || 0;
    if (statCompanies) statCompanies.textContent = [...new Set(jobs.map(j => j.company))].length;
    if (statRemote)    statRemote.textContent    = jobs.filter(j => j.job_type === "remote").length;
}

// ── Show Skeleton Loader ──────────────────
function showSkeletonLoader() {
    const grid = document.getElementById("jobsGrid");
    if (!grid) return;

    grid.innerHTML = `
        <div class="row g-3">
            ${Array(6).fill(`
            <div class="col-md-6">
                <div class="wb-job-card" style="pointer-events:none;">
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <div class="company-logo"
                             style="background:#f3f4f6;color:transparent;">—</div>
                        <div class="flex-grow-1">
                            <div style="height:16px;background:#f3f4f6;border-radius:4px;
                                        width:65%;margin-bottom:8px;"></div>
                            <div style="height:12px;background:#f3f4f6;border-radius:4px;
                                        width:40%;"></div>
                        </div>
                    </div>
                    <div class="job-meta">
                        <div style="height:24px;background:#f3f4f6;border-radius:50px;
                                    width:90px;"></div>
                        <div style="height:24px;background:#f3f4f6;border-radius:50px;
                                    width:70px;"></div>
                    </div>
                </div>
            </div>`).join("")}
        </div>`;
}

// ── Load Single Job (job-detail.html) ─────
async function loadSingleJob(jobId) {
    currentJobId = jobId;

    const loadingEl = document.getElementById("loadingState");
    const contentEl = document.getElementById("jobContent");

    if (loadingEl) loadingEl.style.display = "block";
    if (contentEl) contentEl.style.display = "none";

    const { data } = await apiRequest(`/jobs/single?id=${jobId}`);

    if (loadingEl) loadingEl.style.display = "none";

    if (!data.success) {
        if (contentEl) {
            contentEl.style.display = "block";
            contentEl.innerHTML = `
                <div class="wb-empty-state py-5">
                    <div class="icon">😕</div>
                    <h5>Job Not Found</h5>
                    <p>${data.message}</p>
                    <a href="index.html" class="btn-wb-primary mt-3">
                        Back to Jobs
                    </a>
                </div>`;
        }
        return null;
    }

    return data.data.job;
}

// ── Render Single Job ─────────────────────
function renderSingleJob(job) {
    document.title = `${job.title} — Wooble Jobs`;

    // Breadcrumb
    const breadcrumb = document.getElementById("breadcrumbTitle");
    if (breadcrumb) breadcrumb.textContent = job.title;

    // Header
    const logoEl = document.getElementById("companyLogo");
    if (logoEl) logoEl.textContent = job.company.charAt(0).toUpperCase();

    const titleEl = document.getElementById("jobTitle");
    if (titleEl) titleEl.textContent = job.title;

    const companyEl = document.getElementById("jobCompany");
    if (companyEl) {
        companyEl.innerHTML = `
            <i class="bi bi-building"></i> ${job.company}
            &nbsp;·&nbsp;
            <i class="bi bi-geo-alt"></i> ${job.location}`;
    }

    const badgesEl = document.getElementById("jobBadges");
    if (badgesEl) {
        badgesEl.innerHTML = `
            ${jobTypeBadge(job.job_type)}
            ${statusBadge(job.status)}
            ${job.salary_min
                ? `<span class="wb-badge wb-badge-green">
                       💰 ${formatSalary(job.salary_min, job.salary_max)}
                   </span>`
                : ""}
            <span class="wb-badge wb-badge-gray">
                <i class="bi bi-people"></i> ${job.total_applications} Applied
            </span>`;
    }

    // Description
    const descEl = document.getElementById("jobDescription");
    if (descEl) descEl.textContent = job.description;

    // Requirements
    const reqSection = document.getElementById("requirementsSection");
    const reqEl      = document.getElementById("jobRequirements");
    if (job.requirements && reqEl) {
        reqEl.textContent = job.requirements;
    } else if (reqSection) {
        reqSection.style.display = "none";
    }

    // Sidebar card
    const salaryEl = document.getElementById("cardSalary");
    if (salaryEl) {
        salaryEl.textContent = formatSalary(job.salary_min, job.salary_max);
    }

    const fields = {
        cardType:       job.job_type.replace("-", " ").toUpperCase(),
        cardLocation:   job.location,
        cardCompany:    job.company,
        cardDate:       formatDate(job.created_at),
        cardApplicants: `${job.total_applications} applicant(s)`
    };

    Object.entries(fields).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    });

    const cardStatus = document.getElementById("cardStatus");
    if (cardStatus) cardStatus.innerHTML = statusBadge(job.status);

    // Show content
    const contentEl = document.getElementById("jobContent");
    if (contentEl) contentEl.style.display = "block";
}

// ── Setup Apply Section ───────────────────
function setupApplySection(job) {
    const loginSection    = document.getElementById("loginToApply");
    const applySection    = document.getElementById("applySection");
    const quickApplyBtn   = document.getElementById("quickApplyBtn");
    const alreadyApplied  = document.getElementById("alreadyApplied");

    // Not logged in
    if (!Auth.isLoggedIn()) {
        if (loginSection) loginSection.style.display = "block";
        return;
    }

    // Admin — no apply section
    if (Auth.isAdmin()) return;

    // Job is closed
    if (job.status !== "active") {
        if (applySection) {
            applySection.style.display = "block";
            applySection.innerHTML = `
                <div class="text-center py-3">
                    <div style="font-size:48px;">🔒</div>
                    <h5 class="mt-3 text-muted">Job Closed</h5>
                    <p class="text-muted">This job is no longer accepting applications.</p>
                </div>`;
        }
        return;
    }

    // Candidate — show apply form
    if (applySection)  applySection.style.display  = "block";
    if (quickApplyBtn) quickApplyBtn.style.display = "block";
}

// ── Handle Apply ──────────────────────────
async function handleApply(e) {
    e.preventDefault();

    const coverLetter = document.getElementById("coverLetter")?.value.trim() || "";
    const resumeFile  = document.getElementById("resumeFile")?.files[0];
    const btn         = document.getElementById("applyBtn");
    const jobId       = new URLSearchParams(window.location.search).get("id");

    if (!jobId) return;

    if (btn) {
        btn.innerHTML = '<span class="wb-spinner"></span> Submitting...';
        btn.disabled  = true;
    }

    let result;

    if (resumeFile) {
        // FormData for file upload
        const formData = new FormData();
        if (coverLetter) formData.append("cover_letter", coverLetter);
        formData.append("resume", resumeFile);
        result = await apiFormRequest(`/applications/apply?id=${jobId}`, formData);
    } else {
        result = await apiRequest(
            `/applications/apply?id=${jobId}`,
            "POST",
            { cover_letter: coverLetter },
            true
        );
    }

    if (btn) {
        btn.innerHTML = '<i class="bi bi-send"></i> Submit Application';
        btn.disabled  = false;
    }

    if (!result.data.success) {
        showAlert("applyAlert", result.data.message, "danger");
        return;
    }

    // Success
    const applySection   = document.getElementById("applySection");
    const quickApplyBtn  = document.getElementById("quickApplyBtn");
    const alreadyApplied = document.getElementById("alreadyApplied");

    if (applySection)   applySection.style.display   = "none";
    if (quickApplyBtn)  quickApplyBtn.style.display  = "none";
    if (alreadyApplied) alreadyApplied.style.display = "block";

    showAlert("pageAlert", "🎉 Application submitted successfully! Good luck!", "success");
}

// ── Search Jobs ───────────────────────────
function searchJobs() {
    loadJobs(1);
}

// ── Filter By Type (quick badge) ──────────
function filterByType(type) {
    const filterEl = document.getElementById("filterType");
    if (filterEl) filterEl.value = type;
    loadJobs(1);
}

// ── Clear Filters ─────────────────────────
function clearFilters() {
    const searchEl   = document.getElementById("searchInput");
    const typeEl     = document.getElementById("filterType");
    const locationEl = document.getElementById("filterLocation");

    if (searchEl)   searchEl.value   = "";
    if (typeEl)     typeEl.value     = "";
    if (locationEl) locationEl.value = "";

    loadJobs(1);
}

// ── Debounce for location input ───────────
function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadJobs(1), 500);
}

// ── Scroll to Apply Form ──────────────────
function scrollToApply() {
    const applySection = document.getElementById("applySection");
    if (applySection) {
        applySection.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}

// ── Copy Job Link ─────────────────────────
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showAlert("pageAlert", "Job link copied to clipboard!", "success");
    });
}

// ── Render Pagination ─────────────────────
function renderPagination(totalPages, current) {
    const el = document.getElementById("pagination");
    if (!el) return;

    if (totalPages <= 1) {
        el.innerHTML = "";
        return;
    }

    let html = "";

    // Prev button
    html += `
        <button class="wb-page-btn ${current === 1 ? "disabled" : ""}"
                onclick="loadJobs(${current - 1})"
                ${current === 1 ? "disabled" : ""}>
            ‹
        </button>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (
            i === 1 ||
            i === totalPages ||
            (i >= current - 1 && i <= current + 1)
        ) {
            html += `
                <button class="wb-page-btn ${i === current ? "active" : ""}"
                        onclick="loadJobs(${i})">${i}</button>`;
        } else if (i === current - 2 || i === current + 2) {
            html += `<span style="padding:0 4px;color:var(--text-light);">...</span>`;
        }
    }

    // Next button
    html += `
        <button class="wb-page-btn ${current === totalPages ? "disabled" : ""}"
                onclick="loadJobs(${current + 1})"
                ${current === totalPages ? "disabled" : ""}>
            ›
        </button>`;

    el.innerHTML = html;
}

// ── Init on page load ─────────────────────
document.addEventListener("DOMContentLoaded", () => {

    const path = window.location.pathname;

    // index.html — load job listings
    if (path.includes("index.html") || path.endsWith("/frontend/")) {
        loadJobs();

        // Search on Enter key
        document.getElementById("searchInput")?.addEventListener("keypress", (e) => {
            if (e.key === "Enter") searchJobs();
        });
    }

    // job-detail.html — load single job
    if (path.includes("job-detail.html")) {
        const params = new URLSearchParams(window.location.search);
        const jobId  = params.get("id");

        if (!jobId) {
            window.location.href = "index.html";
            return;
        }

        // Setup dashboard link
        const dashLink = document.getElementById("dashboardLink");
        if (dashLink) {
            dashLink.href = Auth.isAdmin()
                ? "admin/dashboard.html"
                : "candidate/dashboard.html";
        }

        // Load job
        loadSingleJob(jobId).then(job => {
            if (job) {
                renderSingleJob(job);
                setupApplySection(job);
            }
        });
    }
});