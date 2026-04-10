<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit(0);
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

// --- Admin only ---
$admin = requireAdmin();

$db = (new Database())->getConnection();

// --- Total Jobs ---
$stmt = $db->prepare("
    SELECT 
        COUNT(*) AS total_jobs,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_jobs,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_jobs
    FROM jobs
    WHERE admin_id = :admin_id
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$jobStats = $stmt->fetch();

// --- Total Applications ---
$stmt = $db->prepare("
    SELECT
        COUNT(*)  AS total_applications,
        SUM(CASE WHEN a.status = 'pending'     THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted,
        SUM(CASE WHEN a.status = 'invited'     THEN 1 ELSE 0 END) AS invited,
        SUM(CASE WHEN a.status = 'rejected'    THEN 1 ELSE 0 END) AS rejected
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.admin_id = :admin_id
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$appStats = $stmt->fetch();

// --- Total Candidates ---
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT a.candidate_id) AS total_candidates
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.admin_id = :admin_id
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$candidateStats = $stmt->fetch();

// --- Total Invitations ---
$stmt = $db->prepare("
    SELECT COUNT(*) AS total_invitations
    FROM invitations i
    JOIN jobs j ON i.job_id = j.id
    WHERE j.admin_id = :admin_id
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$invitationStats = $stmt->fetch();

// --- Recent Applications (last 5) ---
$stmt = $db->prepare("
    SELECT
        a.id            AS application_id,
        a.status,
        a.applied_at,
        u.name          AS candidate_name,
        u.email         AS candidate_email,
        j.title         AS job_title
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE j.admin_id = :admin_id
    ORDER BY a.applied_at DESC
    LIMIT 5
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$recentApplications = $stmt->fetchAll();

// --- Top Jobs by Applications ---
$stmt = $db->prepare("
    SELECT
        j.id,
        j.title,
        j.job_type,
        j.status,
        COUNT(a.id) AS total_applications
    FROM jobs j
    LEFT JOIN applications a ON j.id = a.job_id
    WHERE j.admin_id = :admin_id
    GROUP BY j.id
    ORDER BY total_applications DESC
    LIMIT 5
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$topJobs = $stmt->fetchAll();

// --- Applications per day (last 7 days) ---
$stmt = $db->prepare("
    SELECT
        DATE(a.applied_at) AS date,
        COUNT(*)           AS count
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE j.admin_id = :admin_id
    AND a.applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(a.applied_at)
    ORDER BY date ASC
");
$stmt->execute([":admin_id" => $admin["user_id"]]);
$dailyStats = $stmt->fetchAll();

echo json_encode([
    "success" => true,
    "message" => "Dashboard data fetched successfully",
    "data"    => [
        "admin" => [
            "id"   => $admin["user_id"],
            "name" => $admin["name"]
        ],
        "overview" => [
            "total_jobs"         => (int)$jobStats["total_jobs"],
            "active_jobs"        => (int)$jobStats["active_jobs"],
            "closed_jobs"        => (int)$jobStats["closed_jobs"],
            "total_applications" => (int)$appStats["total_applications"],
            "total_candidates"   => (int)$candidateStats["total_candidates"],
            "total_invitations"  => (int)$invitationStats["total_invitations"]
        ],
        "application_stats" => [
            "pending"     => (int)$appStats["pending"],
            "shortlisted" => (int)$appStats["shortlisted"],
            "invited"     => (int)$appStats["invited"],
            "rejected"    => (int)$appStats["rejected"]
        ],
        "recent_applications" => $recentApplications,
        "top_jobs"            => $topJobs,
        "daily_stats"         => $dailyStats
    ]
]);
?>