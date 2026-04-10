// ============================================
// WOOBLE JOBS — APPLICATIONS JS
// Handles: Candidate applications tracking,
//          Admin application management,
//          Status updates, Invitations
// ============================================

let currentPage    = 1;
let currentFilter  = "";
let selectedStatus = "";

// ══════════════════════════════════════════
// CANDIDATE SIDE
// ══════════════════════════════════════════

// ── Load Candidate Overview ───────────────
async function loadCandidateOverview() {
    const { data } = await apiRequest("/applications/my", "GET", null, true);

    if (!data.success) {
        showAlert("pageAlert", "Failed to load overview: " + data.message, "danger");
        return;
    }

    const { summary, applications } = data.data;

    // Stats
    const total = (summary.pending     || 0) +
                  (summary.shortlisted || 0) +
                  (summary.invited     || 0) +
                  (summary.rejected    || 0);

    setEl("statTotal",       total);
    setEl("statPending",     summary.pending     || 0);
    setEl("statShortlisted", summary.shortlisted || 0);
    setEl("statInvited",     summary.invited     || 0);

    // Recent applications (last 3)
    renderRecentApplications(applications.slice(0, 3));
}

// ── Render Recent Applications ────────────
function renderRecentApplications(apps) {
    const el = document.getElementById("recentApps");
    if (!el) return;

    if (apps.length === 0) {
        el.innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">📋</div>
                <h5>No applications yet</h5>
                <p>Browse jobs and start applying!</p>
                <a href="../index.html" class="btn-wb-primary mt-3">
                    Browse Jobs
                </a>
            </div>`;
        return;
    }

    el.innerHTML = apps.map(app => `
        <div class="d-flex align-items-center justify-content-between py-3"
             style="border-bottom:1px solid var(--border);">
            <div class="d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;background:var(--primary-light);
                            border-radius:var(--radius-sm);display:flex;
                            align-items:center;justify-content:center;
                            font-weight:700;color:var(--primary);">
                    ${app.company.charAt(0).toUpperCase()}
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;">${app.job_title}</div>
                    <div style="font-size:12px;color:var(--text-light);">
                        ${app.company} · ${formatDate(app.applied_at)}
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                ${statusBadge(app.status)}
                <button onclick="viewCandidateDetail(${app.application_id})"
                        class="btn-wb-outline"
                        style="font-size:12px;padding:5px 12px;">
                    View
                </button>
            </div>
        </div>`).join("");
}

// ── Load Candidate Applications ───────────
async function loadCandidateApplications(page = 1) {
    currentPage = page;

    let url = `/applications/my?page=${page}`;
    if (currentFilter) url += `&status=${currentFilter}`;

    const { data } = await apiRequest(url, "GET", null, true);

    if (!data.success) {
        document.getElementById("appsList").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">😕</div>
                <h5>Failed to load applications</h5>
                <p>${data.message}</p>
            </div>`;
        return;
    }

    const { applications, total, total_pages } = data.data;

    if (applications.length === 0) {
        document.getElementById("appsList").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">📋</div>
                <h5>No applications found</h5>
                <p>${currentFilter
                    ? `No ${currentFilter} applications yet`
                    : "You haven't applied to any jobs yet"}</p>
                <a href="../index.html" class="btn-wb-primary mt-3">
                    Browse Jobs
                </a>
            </div>`;
        document.getElementById("appsPagination").innerHTML = "";
        return;
    }

    document.getElementById("appsList").innerHTML = applications.map(app => `
        <div class="app-card mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

                <!-- Job Info -->
                <div class="d-flex gap-3 align-items-start">
                    <div style="width:48px;height:48px;background:var(--primary-light);
                                border-radius:var(--radius-sm);display:flex;
                                align-items:center;justify-content:center;
                                font-weight:700;color:var(--primary);
                                font-size:18px;flex-shrink:0;">
                        ${app.company.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="job-title">${app.job_title}</div>
                        <div class="company mb-2">
                            <i class="bi bi-building"></i> ${app.company}
                            &nbsp;·&nbsp;
                            <i class="bi bi-geo-alt"></i> ${app.location}
                            &nbsp;·&nbsp;
                            ${jobTypeBadge(app.job_type)}
                        </div>
                        <div style="font-size:12px;color:var(--text-light);">
                            <i class="bi bi-clock"></i> Applied ${formatDate(app.applied_at)}
                        </div>
                    </div>
                </div>

                <!-- Status & Actions -->
                <div class="d-flex flex-column align-items-end gap-2">
                    ${statusBadge(app.status)}
                    <button onclick="viewCandidateDetail(${app.application_id})"
                            class="btn-wb-outline"
                            style="font-size:13px;padding:6px 16px;">
                        <i class="bi bi-eye"></i> View Detail
                    </button>
                </div>

            </div>

            <!-- Progress Timeline -->
            <div class="mt-4 pt-3" style="border-top:1px solid var(--border);">
                <div class="d-flex align-items-center">
                    ${["pending","shortlisted","invited"].map((step, i) => {
                        const steps   = ["pending","shortlisted","invited","rejected"];
                        const current = steps.indexOf(app.status);
                        const active  = i <= current && app.status !== "rejected";
                        return `
                        <div class="d-flex align-items-center flex-grow-1">
                            <div style="display:flex;flex-direction:column;
                                        align-items:center;gap:4px;">
                                <div style="width:28px;height:28px;border-radius:50%;
                                            background:${active ? "var(--primary)" : "var(--border)"};
                                            display:flex;align-items:center;
                                            justify-content:center;
                                            color:${active ? "white" : "var(--text-light)"};
                                            font-size:12px;font-weight:700;">
                                    ${active ? "✓" : i + 1}
                                </div>
                                <span style="font-size:11px;
                                             color:${active ? "var(--primary)" : "var(--text-light)"};
                                             font-weight:${active ? "700" : "400"};">
                                    ${step.charAt(0).toUpperCase() + step.slice(1)}
                                </span>
                            </div>
                            ${i < 2 ? `
                            <div style="flex:1;height:2px;
                                        background:${i < current && app.status !== "rejected"
                                            ? "var(--primary)"
                                            : "var(--border)"};">
                            </div>` : ""}
                        </div>`;
                    }).join("")}
                </div>

                <!-- Rejected Notice -->
                ${app.status === "rejected" ? `
                <div class="mt-2">
                    <span class="wb-badge wb-badge-red">❌ Application Rejected</span>
                    ${app.admin_note
                        ? `<span class="text-muted ms-2" style="font-size:12px;">
                               Note: ${app.admin_note}
                           </span>`
                        : ""}
                </div>` : ""}

                <!-- Invited Notice -->
                ${app.status === "invited" ? `
                <div class="mt-3 p-3"
                     style="background:#dcfce7;border-radius:var(--radius-sm);">
                    <div style="font-size:13px;font-weight:700;color:var(--success);">
                        🎉 You have been invited for an interview!
                    </div>
                    ${app.admin_note
                        ? `<div style="font-size:13px;color:var(--text-mid);margin-top:4px;">
                               ${app.admin_note}
                           </div>`
                        : ""}
                </div>` : ""}

            </div>
        </div>`).join("");

    renderAppsPagination(total_pages, page);
}

// ── View Candidate Application Detail ─────
async function viewCandidateDetail(id) {
    const modal = new bootstrap.Modal(
        document.getElementById("appDetailModal")
    );

    document.getElementById("modalContent").innerHTML = `
        <div class="text-center py-4">
            <div class="wb-spinner"
                 style="border-color:var(--border);border-top-color:var(--primary);
                        width:32px;height:32px;border-width:3px;"></div>
            <p class="text-muted mt-3">Loading...</p>
        </div>`;
    modal.show();

    const { data } = await apiRequest(
        `/applications/single?id=${id}`,
        "GET", null, true
    );

    if (!data.success) {
        document.getElementById("modalContent").innerHTML = `
            <div class="wb-empty-state">
                <h5>Failed to load</h5>
                <p>${data.message}</p>
            </div>`;
        return;
    }

    const { application, job, candidate, invitation } = data.data;
    const statusIcons = {
        pending: "🕐", shortlisted: "⭐",
        invited: "🎯", rejected: "❌"
    };

    document.getElementById("modalContent").innerHTML = `

        <!-- Status Banner -->
        <div class="text-center mb-4 p-3"
             style="background:var(--primary-light);border-radius:var(--radius-sm);">
            <div style="font-size:36px;margin-bottom:8px;">
                ${statusIcons[application.status] || "📋"}
            </div>
            ${statusBadge(application.status)}
        </div>

        <!-- Job Info -->
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Job Details
            </div>
            <div style="font-size:18px;font-weight:800;">${job.title}</div>
            <div style="color:var(--text-light);font-size:14px;">
                ${job.company} · ${job.location} · ${job.job_type}
            </div>
            <div class="mt-2">
                ${job.salary_min
                    ? `<span class="wb-badge wb-badge-green">
                           💰 ${formatSalary(job.salary_min, job.salary_max)}
                       </span>`
                    : ""}
            </div>
        </div>

        <!-- Dates -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;">Applied On</div>
                <div style="font-weight:600;">${formatDate(application.applied_at)}</div>
            </div>
            <div class="col-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;">Last Updated</div>
                <div style="font-weight:600;">
                    ${formatDate(application.updated_at) || "—"}
                </div>
            </div>
        </div>

        <!-- Cover Letter -->
        ${application.cover_letter ? `
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Your Cover Letter
            </div>
            <div style="background:var(--bg-light);padding:16px;
                        border-radius:var(--radius-sm);font-size:14px;
                        color:var(--text-mid);line-height:1.7;">
                ${application.cover_letter}
            </div>
        </div>` : ""}

        <!-- Admin Note -->
        ${application.admin_note ? `
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Recruiter Note
            </div>
            <div style="background:#fef9c3;padding:16px;
                        border-radius:var(--radius-sm);font-size:14px;">
                ${application.admin_note}
            </div>
        </div>` : ""}

        <!-- Invitation -->
        ${invitation ? `
        <div style="background:#dcfce7;padding:20px;border-radius:var(--radius-sm);">
            <h6 style="font-weight:700;color:var(--success);margin-bottom:12px;">
                🎉 Interview Invitation
            </h6>
            ${invitation.interview_date ? `
            <div class="mb-2">
                <div style="font-size:12px;color:var(--text-light);font-weight:600;">
                    DATE & TIME
                </div>
                <strong>${formatDate(invitation.interview_date)}</strong>
            </div>` : ""}
            ${invitation.message ? `
            <div>
                <div style="font-size:12px;color:var(--text-light);font-weight:600;">
                    MESSAGE
                </div>
                <span style="font-size:14px;">${invitation.message}</span>
            </div>` : ""}
        </div>` : ""}
    `;
}

// ── Filter Candidate Applications ─────────
function filterCandidateApps(status, el) {
    currentFilter = status;
    document.querySelectorAll(".filter-btn").forEach(b => {
        b.classList.remove("active-filter");
        b.style.outline = "none";
    });
    if (el) {
        el.classList.add("active-filter");
        el.style.outline = "2px solid var(--primary)";
    }
    loadCandidateApplications(1);
}

// ══════════════════════════════════════════
// ADMIN SIDE
// ══════════════════════════════════════════

// ── Load Admin Summary ────────────────────
async function loadAdminSummary() {
    const { data } = await apiRequest("/applications", "GET", null, true);
    if (!data.success) return;

    const s = data.data.summary;
    setEl("sumPending",     s.pending     || 0);
    setEl("sumShortlisted", s.shortlisted || 0);
    setEl("sumInvited",     s.invited     || 0);
    setEl("sumRejected",    s.rejected    || 0);
    setEl("topbarCount",    `${s.total || 0} Total Applications`);
}

// ── Load Admin Applications ───────────────
async function loadAdminApplications(page = 1) {
    currentPage = page;

    const status = document.getElementById("filterStatus")?.value || "";
    const jobId  = document.getElementById("filterJob")?.value    || "";

    let query = `?page=${page}`;
    if (status) query += `&status=${status}`;
    if (jobId)  query += `&job_id=${jobId}`;

    const { data } = await apiRequest(
        `/applications${query}`,
        "GET", null, true
    );

    if (!data.success) {
        document.getElementById("appsList").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">😕</div>
                <h5>Failed to load applications</h5>
                <p>${data.message}</p>
            </div>`;
        return;
    }

    const { applications, total, total_pages } = data.data;

    const countEl = document.getElementById("appCount");
    if (countEl) {
        countEl.textContent = `${total} application${total !== 1 ? "s" : ""} found`;
    }

    if (applications.length === 0) {
        document.getElementById("appsList").innerHTML = `
            <div class="wb-empty-state">
                <div class="icon">📋</div>
                <h5>No applications found</h5>
                <p>No applications match your current filters</p>
            </div>`;
        document.getElementById("pagination").innerHTML = "";
        return;
    }

    document.getElementById("appsList").innerHTML = applications.map(app => `
        <div class="app-row">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

                <!-- Candidate Info -->
                <div class="d-flex gap-3 align-items-start">
                    <div class="candidate-avatar">
                        ${app.candidate_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;margin-bottom:2px;">
                            ${app.candidate_name}
                        </div>
                        <div style="font-size:13px;color:var(--text-light);margin-bottom:8px;">
                            <i class="bi bi-envelope"></i> ${app.candidate_email}
                            ${app.candidate_phone
                                ? `&nbsp;·&nbsp;<i class="bi bi-phone"></i>
                                   ${app.candidate_phone}`
                                : ""}
                        </div>
                        <div style="font-size:13px;margin-bottom:8px;">
                            <strong>Applied for:</strong> ${app.job_title}
                            <span class="text-muted">@ ${app.company}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            ${statusBadge(app.status)}
                            ${jobTypeBadge(app.job_type)}
                            <span class="wb-badge wb-badge-gray">
                                <i class="bi bi-clock"></i> ${formatDate(app.applied_at)}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-flex flex-column gap-2 align-items-end">
                    <button onclick="viewAdminDetail(${app.application_id})"
                            class="btn-wb-outline"
                            style="font-size:13px;padding:8px 16px;">
                        <i class="bi bi-eye"></i> View
                    </button>
                    <button onclick="openStatusModal(
                                ${app.application_id},
                                '${app.candidate_name.replace(/'/g, "\\'")}',
                                '${app.job_title.replace(/'/g, "\\'")}',
                                '${app.candidate_email}')"
                            class="btn-wb-primary"
                            style="font-size:13px;padding:8px 16px;">
                        <i class="bi bi-pencil-square"></i> Update Status
                    </button>
                </div>

            </div>

            <!-- Admin Note -->
            ${app.admin_note ? `
            <div class="mt-3 pt-3" style="border-top:1px solid var(--border);">
                <span style="font-size:12px;color:var(--text-light);font-weight:600;">
                    ADMIN NOTE:
                </span>
                <span style="font-size:13px;color:var(--text-mid);">
                    ${app.admin_note}
                </span>
            </div>` : ""}

        </div>`).join("");

    renderAdminPagination(total_pages, page);
}

// ── View Admin Application Detail ─────────
async function viewAdminDetail(id) {
    const modal = new bootstrap.Modal(document.getElementById("appModal"));

    document.getElementById("appModalContent").innerHTML = `
        <div class="text-center py-4">
            <div class="wb-spinner"
                 style="border-color:var(--border);border-top-color:var(--primary);
                        width:32px;height:32px;border-width:3px;"></div>
            <p class="text-muted mt-3">Loading...</p>
        </div>`;
    modal.show();

    const { data } = await apiRequest(
        `/applications/single?id=${id}`,
        "GET", null, true
    );

    if (!data.success) {
        document.getElementById("appModalContent").innerHTML = `
            <div class="wb-empty-state">
                <h5>Failed to load</h5>
                <p>${data.message}</p>
            </div>`;
        return;
    }

    const { application, job, candidate, invitation } = data.data;
    const statusIcons = {
        pending: "🕐", shortlisted: "⭐",
        invited: "🎯", rejected: "❌"
    };

    document.getElementById("appModalContent").innerHTML = `

        <!-- Status Banner -->
        <div class="p-3 mb-4 text-center"
             style="background:var(--primary-light);border-radius:var(--radius-sm);">
            <div style="font-size:32px;">
                ${statusIcons[application.status] || "📋"}
            </div>
            <div class="mt-2">${statusBadge(application.status)}</div>
        </div>

        <div class="row g-4">
            <!-- Candidate -->
            <div class="col-md-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Candidate
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="candidate-avatar">
                        ${candidate.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div style="font-weight:700;">${candidate.name}</div>
                        <div style="font-size:13px;color:var(--text-light);">
                            ${candidate.email}
                        </div>
                        ${candidate.phone ? `
                        <div style="font-size:13px;color:var(--text-light);">
                            ${candidate.phone}
                        </div>` : ""}
                    </div>
                </div>
            </div>

            <!-- Job -->
            <div class="col-md-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Job
                </div>
                <div style="font-size:16px;font-weight:700;">${job.title}</div>
                <div style="font-size:13px;color:var(--text-light);">
                    ${job.company} · ${job.location}
                </div>
                <div class="mt-2">${jobTypeBadge(job.job_type)}</div>
            </div>
        </div>

        <hr style="border-color:var(--border);margin:20px 0;">

        <!-- Dates -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;">Applied On</div>
                <div style="font-weight:600;">${formatDate(application.applied_at)}</div>
            </div>
            <div class="col-6">
                <div style="font-size:11px;color:var(--text-light);font-weight:600;
                            text-transform:uppercase;">Last Updated</div>
                <div style="font-weight:600;">
                    ${formatDate(application.updated_at) || "—"}
                </div>
            </div>
        </div>

        <!-- Cover Letter -->
        ${application.cover_letter ? `
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Cover Letter
            </div>
            <div style="background:var(--bg-light);padding:16px;
                        border-radius:var(--radius-sm);font-size:14px;
                        color:var(--text-mid);line-height:1.7;">
                ${application.cover_letter}
            </div>
        </div>` : `
        <p class="text-muted mb-4" style="font-size:13px;">
            No cover letter provided.
        </p>`}

        <!-- Resume -->
        ${application.resume_path ? `
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Resume
            </div>
            <a href="/${application.resume_path}" target="_blank"
               class="btn-wb-outline" style="font-size:13px;padding:8px 16px;">
                <i class="bi bi-file-earmark-pdf"></i> View Resume
            </a>
        </div>` : ""}

        <!-- Admin Note -->
        ${application.admin_note ? `
        <div class="mb-4">
            <div style="font-size:11px;color:var(--text-light);font-weight:600;
                        text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                Admin Note
            </div>
            <div style="background:#fef9c3;padding:16px;border-radius:var(--radius-sm);
                        font-size:14px;">
                ${application.admin_note}
            </div>
        </div>` : ""}

        <!-- Invitation -->
        ${invitation ? `
        <div style="background:#dcfce7;padding:20px;border-radius:var(--radius-sm);">
            <h6 style="font-weight:700;color:var(--success);margin-bottom:12px;">
                📅 Interview Invitation Sent
            </h6>
            ${invitation.interview_date ? `
            <div class="mb-2">
                <div style="font-size:12px;color:var(--text-light);font-weight:600;">
                    DATE & TIME
                </div>
                <strong>${formatDate(invitation.interview_date)}</strong>
            </div>` : ""}
            ${invitation.message ? `
            <div>
                <div style="font-size:12px;color:var(--text-light);font-weight:600;">
                    MESSAGE
                </div>
                <span style="font-size:14px;">${invitation.message}</span>
            </div>` : ""}
            <div class="mt-2" style="font-size:12px;color:var(--text-light);">
                Sent on: ${formatDate(invitation.sent_at)}
            </div>
        </div>` : ""}

        <!-- Update Status Button -->
        <div class="mt-4 text-end">
            <button onclick="
                bootstrap.Modal.getInstance(
                    document.getElementById('appModal')
                ).hide();
                openStatusModal(
                    ${application.id},
                    '${candidate.name.replace(/'/g, "\\'")}',
                    '${job.title.replace(/'/g, "\\'")}',
                    '${candidate.email}'
                )"
                class="btn-wb-primary px-4">
                <i class="bi bi-pencil-square"></i> Update Status
            </button>
        </div>
    `;
}

// ── Open Status Modal ─────────────────────
function openStatusModal(id, candidateName, jobTitle, email) {
    selectedStatus = "";

    setEl("statusAppId",        id,            true);
    setEl("statusCandidateName", candidateName);
    setEl("statusJobTitle",      jobTitle);
    setEl("statusAvatar",        candidateName.charAt(0).toUpperCase());
    setEl("adminNote",           "",            true);
    setEl("interviewDate",       "",            true);
    setEl("inviteMessage",       "",            true);

    const interviewFields = document.getElementById("interviewFields");
    if (interviewFields) interviewFields.style.display = "none";

    const alertEl = document.getElementById("statusAlert");
    if (alertEl) alertEl.className = "wb-alert";

    const titleEl = document.getElementById("statusModalTitle");
    if (titleEl) titleEl.textContent = `Update: ${candidateName}`;

    // Reset status option cards
    document.querySelectorAll(".status-option").forEach(o => {
        o.style.borderColor = "var(--border)";
        o.style.background  = "transparent";
    });

    new bootstrap.Modal(document.getElementById("statusModal")).show();
}

// ── Select Status Card ────────────────────
function selectStatus(status, el) {
    selectedStatus = status;

    document.querySelectorAll(".status-option").forEach(o => {
        o.style.borderColor = "var(--border)";
        o.style.background  = "transparent";
    });

    el.style.borderColor = "var(--primary)";
    el.style.background  = "var(--primary-light)";

    const interviewFields = document.getElementById("interviewFields");
    if (interviewFields) {
        interviewFields.style.display = status === "invited" ? "block" : "none";
    }
}

// ── Submit Status Update ──────────────────
async function submitStatus() {
    if (!selectedStatus) {
        showAlert("statusAlert", "Please select a status", "danger");
        return;
    }

    const id            = document.getElementById("statusAppId")?.value;
    const adminNote     = document.getElementById("adminNote")?.value.trim()     || "";
    const interviewDate = document.getElementById("interviewDate")?.value         || "";
    const inviteMessage = document.getElementById("inviteMessage")?.value.trim() || "";
    const btn           = document.getElementById("submitStatusBtn");

    const payload = { status: selectedStatus };
    if (adminNote) payload.admin_note = adminNote;

    if (selectedStatus === "invited") {
        if (interviewDate) {
            payload.interview_date = interviewDate.replace("T", " ") + ":00";
        }
        if (inviteMessage) payload.message = inviteMessage;
    }

    if (btn) {
        btn.innerHTML = '<span class="wb-spinner"></span> Updating...';
        btn.disabled  = true;
    }

    const { data } = await apiRequest(
        `/applications/status?id=${id}`,
        "PUT",
        payload,
        true
    );

    if (btn) {
        btn.innerHTML = "Update Status";
        btn.disabled  = false;
    }

    if (!data.success) {
        showAlert("statusAlert", data.message, "danger");
        return;
    }

    bootstrap.Modal.getInstance(
        document.getElementById("statusModal")
    ).hide();

    const emailNote = data.data.email_sent
        ? " Email notification sent! ✅"
        : "";

    showAlert("pageAlert", `${data.message}.${emailNote}`, "success");

    // Refresh data
    loadAdminSummary();
    loadAdminApplications(currentPage);
}

// ── Filter by Status (card click) ─────────
function filterByStatus(status) {
    const filterEl = document.getElementById("filterStatus");
    if (filterEl) filterEl.value = status;
    loadAdminApplications(1);
}

// ══════════════════════════════════════════
// SHARED HELPERS
// ══════════════════════════════════════════

// ── Set Element Content ───────────────────
function setEl(id, value, isInput = false) {
    const el = document.getElementById(id);
    if (!el) return;
    if (isInput) {
        el.value = value;
    } else {
        el.textContent = value;
    }
}

// ── Render Candidate Pagination ───────────
function renderAppsPagination(totalPages, current) {
    const el = document.getElementById("appsPagination");
    if (!el) return;

    if (totalPages <= 1) { el.innerHTML = ""; return; }

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
        html += `
            <button class="wb-page-btn ${i === current ? "active" : ""}"
                    onclick="loadCandidateApplications(${i})">
                ${i}
            </button>`;
    }
    el.innerHTML = html;
}

// ── Render Admin Pagination ───────────────
function renderAdminPagination(totalPages, current) {
    const el = document.getElementById("pagination");
    if (!el) return;

    if (totalPages <= 1) { el.innerHTML = ""; return; }

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
        html += `
            <button class="wb-page-btn ${i === current ? "active" : ""}"
                    onclick="loadAdminApplications(${i})">
                ${i}
            </button>`;
    }
    el.innerHTML = html;
}

// ── Init ──────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    const path = window.location.pathname;

    // Candidate dashboard
    if (path.includes("candidate/dashboard")) {
        if (!requireAuth("candidate")) return;
        loadCandidateOverview();
        loadCandidateApplications();
    }

    // Admin applications page
    if (path.includes("admin/applications")) {
        if (!requireAuth("admin")) return;
        loadAdminSummary();
        loadAdminApplications();
    }
});