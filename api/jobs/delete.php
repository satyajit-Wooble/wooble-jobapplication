<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit(0);
if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

// --- Admin only ---
$admin = requireAdmin();

$job_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($job_id <= 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Valid job ID is required. Example: ?id=1"
    ]);
    exit();
}

$db = (new Database())->getConnection();

// --- Check job exists and belongs to this admin ---
$stmt = $db->prepare("
    SELECT id, title FROM jobs 
    WHERE id = :id AND admin_id = :admin_id 
    LIMIT 1
");
$stmt->execute([
    ":id"       => $job_id,
    ":admin_id" => $admin["user_id"]
]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found or you do not have permission to delete it"
    ]);
    exit();
}

// --- Check if job has applications ---
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications 
    WHERE job_id = :job_id
");
$stmt->execute([":job_id" => $job_id]);
$appCount = (int)$stmt->fetch()["total"];

if ($appCount > 0) {
    // Close job instead of deleting if it has applications
    $stmt = $db->prepare("
        UPDATE jobs SET status = 'closed', updated_at = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([":id" => $job_id]);

    echo json_encode([
        "success" => true,
        "message" => "Job has {$appCount} application(s) so it has been closed instead of deleted",
        "data"    => [
            "job_id" => $job_id,
            "title"  => $job["title"],
            "action" => "closed"
        ]
    ]);
    exit();
}

// --- Delete job (no applications) ---
$stmt = $db->prepare("DELETE FROM jobs WHERE id = :id");
$stmt->execute([":id" => $job_id]);

echo json_encode([
    "success" => true,
    "message" => "Job deleted successfully",
    "data"    => [
        "job_id" => $job_id,
        "title"  => $job["title"],
        "action" => "deleted"
    ]
]);
?>