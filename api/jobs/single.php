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

// --- Validate job ID ---
$job_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($job_id <= 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Valid job ID is required. Example: ?id=1"
    ]);
    exit();
}

$db   = (new Database())->getConnection();
$stmt = $db->prepare("
    SELECT 
        j.id,
        j.title,
        j.company,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.description,
        j.requirements,
        j.status,
        j.created_at,
        u.name  AS posted_by,
        u.email AS admin_email,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications
    FROM jobs j
    JOIN users u ON j.posted_by = u.id
    WHERE j.id = :id
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

echo json_encode([
    "success" => true,
    "message" => "Job fetched successfully",
    "data"    => [
        "job" => $job
    ]
]);
?>