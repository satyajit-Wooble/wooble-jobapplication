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

$db = (new Database())->getConnection();

// --- Optional Filters ---
$search   = $_GET["search"]   ?? "";
$type     = $_GET["type"]     ?? "";
$location = $_GET["location"] ?? "";
$page     = max(1, (int)($_GET["page"] ?? 1));
$limit    = 10;
$offset   = ($page - 1) * $limit;

$where  = ["j.status = 'active'"];
$params = [];

if ($search) {
    $where[]           = "(j.title LIKE :search OR j.company LIKE :search OR j.description LIKE :search)";
    $params[":search"] = "%{$search}%";
}
if ($type) {
    $where[]         = "j.job_type = :type";
    $params[":type"] = $type;
}
if ($location) {
    $where[]             = "j.location LIKE :location";
    $params[":location"] = "%{$location}%";
}

$whereSQL = implode(" AND ", $where);

// --- Count total ---
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM jobs j WHERE {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()["total"];

// --- Fetch jobs ---
$params[":limit"]  = $limit;
$params[":offset"] = $offset;

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
        u.name AS posted_by
    FROM jobs j
    JOIN users u ON j.admin_id = u.id
    WHERE {$whereSQL}
    ORDER BY j.created_at DESC
    LIMIT :limit OFFSET :offset
");

// Bind integer params separately for LIMIT/OFFSET
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