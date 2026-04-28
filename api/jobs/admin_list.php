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

// --- Employer or Company ---
$user = requireEmployerOrCompany();

$db = (new Database())->getConnection();

// --- Optional Filters ---
$search = $_GET["search"] ?? "";
$status = $_GET["status"] ?? "";
$type   = $_GET["type"]   ?? "";
$page   = max(1, (int)($_GET["page"] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Company sees ALL jobs
// Employer sees only their own jobs
if ($user["role"] === "company") {
    $where  = [];
    $params = [];
} else {
    $where  = ["j.posted_by = :posted_by"];
    $params = [":posted_by" => $user["user_id"]];
}

if ($search) {
    $where[]           = "(j.title LIKE :search OR j.company LIKE :search)";
    $params[":search"] = "%{$search}%";
}
if ($status) {
    $where[]          = "j.status = :status";
    $params[":status"] = $status;
}
if ($type) {
    $where[]        = "j.job_type = :type";
    $params[":type"] = $type;
}

$whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Count total ---
$countStmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM jobs j 
    {$whereSQL}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()["total"];

// --- Fetch jobs ---
$params[":limit"]  = $limit;
$params[":offset"] = $offset;

$stmt = $db->prepare("
    SELECT
        j.*,
        u.name AS posted_by_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications
    FROM jobs j
    JOIN users u ON j.posted_by = u.id
    {$whereSQL}
    ORDER BY j.created_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $value) {
    if ($key === ":limit" || $key === ":offset") {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$jobs = $stmt->fetchAll();

echo json_encode([
    "success" => true,
    "message" => "Jobs fetched successfully",
    "data"    => [
        "jobs"        => $jobs,
        "total"       => $total,
        "page"        => $page,
        "limit"       => $limit,
        "total_pages" => ceil($total / $limit)
    ]
]);
?>