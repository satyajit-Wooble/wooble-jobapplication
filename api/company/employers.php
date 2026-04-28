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

// Company only
requireCompany();

$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT
        u.id         AS user_id,
        u.name,
        u.email,
        u.phone,
        u.created_at,
        ep.id        AS profile_id,
        ep.company_name,
        ep.website,
        ep.industry,
        ep.location,
        ep.description,
        ep.logo_path,
        ep.status,
        ep.updated_at,
        (SELECT COUNT(*) FROM jobs j WHERE j.posted_by = u.id) AS total_jobs,
        (SELECT COUNT(*) FROM applications a
         JOIN jobs j ON a.job_id = j.id
         WHERE j.posted_by = u.id) AS total_applications
    FROM users u
    JOIN employer_profiles ep ON u.id = ep.user_id
    WHERE u.role = 'employer'
    ORDER BY ep.status ASC, u.created_at DESC
");
$stmt->execute();
$employers = $stmt->fetchAll();

// Summary counts
$total    = count($employers);
$pending  = count(array_filter($employers, fn($e) => $e["status"] === "pending"));
$approved = count(array_filter($employers, fn($e) => $e["status"] === "approved"));
$rejected = count(array_filter($employers, fn($e) => $e["status"] === "rejected"));

echo json_encode([
    "success" => true,
    "message" => "Employers fetched successfully",
    "data"    => [
        "employers" => $employers,
        "summary"   => [
            "total"    => $total,
            "pending"  => $pending,
            "approved" => $approved,
            "rejected" => $rejected
        ]
    ]
]);
?>