<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit(0);
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";
require_once __DIR__ . "/../helpers/send_mail.php";

// --- Candidate only ---
$candidate = requireCandidate();

$data   = json_decode(file_get_contents("php://input"), true);
$job_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($job_id <= 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Valid job ID is required. Example: ?id=1"
    ]);
    exit();
}

$cover_letter = trim($data["cover_letter"] ?? "");

$db = (new Database())->getConnection();

// --- Check job exists and is active ---
$stmt = $db->prepare("
    SELECT id, title, company, status
    FROM jobs
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([":id" => $job_id]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found"
    ]);
    exit();
}

if ($job["status"] !== "active") {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "This job is no longer accepting applications"
    ]);
    exit();
}

// --- Check already applied ---
$stmt = $db->prepare("
    SELECT id FROM applications
    WHERE job_id = :job_id AND candidate_id = :candidate_id
    LIMIT 1
");
$stmt->execute([
    ":job_id"       => $job_id,
    ":candidate_id" => $candidate["user_id"]
]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "You have already applied for this job"
    ]);
    exit();
}

// --- Handle resume upload ---
$resume_path = null;
if (!empty($_FILES["resume"])) {
    $file         = $_FILES["resume"];
    $allowedTypes = [
        "application/pdf",
        "application/msword",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    ];

    if (!in_array($file["type"], $allowedTypes)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "Resume must be PDF or DOC/DOCX format"
        ]);
        exit();
    }

    if ($file["size"] > 2 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "Resume file size must be under 2MB"
        ]);
        exit();
    }

    $uploadDir = __DIR__ . "/../../uploads/resumes/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext         = pathinfo($file["name"], PATHINFO_EXTENSION);
    $filename    = "resume_" . $candidate["user_id"] . "_" . time() . "." . $ext;
    $resume_path = "uploads/resumes/" . $filename;

    if (!move_uploaded_file($file["tmp_name"], $uploadDir . $filename)) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to upload resume"
        ]);
        exit();
    }
}

// --- Insert application ---
$stmt = $db->prepare("
    INSERT INTO applications
        (job_id, candidate_id, cover_letter, resume_path, status, applied_at)
    VALUES
        (:job_id, :candidate_id, :cover_letter, :resume_path, 'pending', NOW())
");
$stmt->execute([
    ":job_id"       => $job_id,
    ":candidate_id" => $candidate["user_id"],
    ":cover_letter" => $cover_letter ?: null,
    ":resume_path"  => $resume_path
]);

$application_id = $db->lastInsertId();

// --- Get candidate email from DB ---
$stmt = $db->prepare("
    SELECT email FROM users WHERE id = :id LIMIT 1
");
$stmt->execute([":id" => $candidate["user_id"]]);
$candidateData = $stmt->fetch();

// --- Send Application Received Email ---
$mailResult = mailApplicationReceived(
    $candidateData["email"],
    $candidate["name"],
    $job["title"],
    $job["company"],
    (int)$application_id
);

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Application submitted successfully",
    "data"    => [
        "application" => [
            "id"           => (int)$application_id,
            "job_id"       => $job_id,
            "job_title"    => $job["title"],
            "company"      => $job["company"],
            "candidate_id" => $candidate["user_id"],
            "candidate"    => $candidate["name"],
            "cover_letter" => $cover_letter ?: null,
            "resume_path"  => $resume_path,
            "status"       => "pending",
            "applied_at"   => date("Y-m-d H:i:s")
        ],
        "email_sent"    => $mailResult["success"],
        "email_message" => $mailResult["message"]
    ]
]);
?>