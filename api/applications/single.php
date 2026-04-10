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

// --- Any logged in user ---
$user = authenticate();

$application_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($application_id <= 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Valid application ID is required. Example: ?id=1"
    ]);
    exit();
}

$db = (new Database())->getConnection();

// --- Fetch application ---
$stmt = $db->prepare("
    SELECT
        a.id              AS application_id,
        a.status,
        a.cover_letter,
        a.resume_path,
        a.admin_note,
        a.applied_at,
        a.updated_at,
        j.id              AS job_id,
        j.title           AS job_title,
        j.company,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.description     AS job_description,
        j.requirements    AS job_requirements,
        j.status          AS job_status,
        j.admin_id,
        u.id              AS candidate_id,
        u.name            AS candidate_name,
        u.email           AS candidate_email,
        u.phone           AS candidate_phone
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE a.id = :id
    LIMIT 1
");
$stmt->execute([":id" => $application_id]);
$application = $stmt->fetch();

if (!$application) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Application not found"
    ]);
    exit();
}

// --- Permission check ---
// Candidate can only view their own application
// Admin can only view applications for their jobs
if ($user["role"] === "candidate" && $application["candidate_id"] != $user["user_id"]) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Forbidden: You can only view your own applications"
    ]);
    exit();
}

if ($user["role"] === "admin" && $application["admin_id"] != $user["user_id"]) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Forbidden: You can only view applications for your own jobs"
    ]);
    exit();
}

// --- Fetch invitation if exists ---
$invStmt = $db->prepare("
    SELECT 
        id              AS invitation_id,
        interview_date,
        message,
        sent_at
    FROM invitations
    WHERE application_id = :application_id
    LIMIT 1
");
$invStmt->execute([":application_id" => $application_id]);
$invitation = $invStmt->fetch();

echo json_encode([
    "success" => true,
    "message" => "Application fetched successfully",
    "data"    => [
        "application" => [
            "id"           => (int)$application["application_id"],
            "status"       => $application["status"],
            "cover_letter" => $application["cover_letter"],
            "resume_path"  => $application["resume_path"],
            "admin_note"   => $application["admin_note"],
            "applied_at"   => $application["applied_at"],
            "updated_at"   => $application["updated_at"]
        ],
        "job" => [
            "id"           => (int)$application["job_id"],
            "title"        => $application["job_title"],
            "company"      => $application["company"],
            "location"     => $application["location"],
            "job_type"     => $application["job_type"],
            "salary_min"   => $application["salary_min"],
            "salary_max"   => $application["salary_max"],
            "description"  => $application["job_description"],
            "requirements" => $application["job_requirements"],
            "status"       => $application["job_status"]
        ],
        "candidate" => [
            "id"    => (int)$application["candidate_id"],
            "name"  => $application["candidate_name"],
            "email" => $application["candidate_email"],
            "phone" => $application["candidate_phone"]
        ],
        "invitation" => $invitation ?: null
    ]
]);
?>