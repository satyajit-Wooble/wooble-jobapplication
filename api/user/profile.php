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

$db   = (new Database())->getConnection();
$stmt = $db->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        role,
        created_at
    FROM users
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([":id" => $user["user_id"]]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit();
}

// --- Get application stats for candidates ---
$stats = null;
if ($profile["role"] === "candidate") {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending'     THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) AS shortlisted,
            SUM(CASE WHEN status = 'invited'     THEN 1 ELSE 0 END) AS invited,
            SUM(CASE WHEN status = 'rejected'    THEN 1 ELSE 0 END) AS rejected
        FROM applications
        WHERE candidate_id = :id
    ");
    $stmt->execute([":id" => $profile["id"]]);
    $stats = $stmt->fetch();
}

// --- Get job stats for admins ---
if ($profile["role"] === "admin") {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_jobs,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_jobs,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_jobs
        FROM jobs
        WHERE posted_by = :id
    ");
    $stmt->execute([":id" => $profile["id"]]);
    $stats = $stmt->fetch();
}

echo json_encode([
    "success" => true,
    "message" => "Profile fetched successfully",
    "data"    => [
        "profile" => [
            "id"         => (int)$profile["id"],
            "name"       => $profile["name"],
            "email"      => $profile["email"],
            "phone"      => $profile["phone"],
            "role"       => $profile["role"],
            "joined"     => $profile["created_at"],
            "avatar"     => strtoupper(substr($profile["name"], 0, 1))
        ],
        "stats" => $stats
    ]
]);
?>