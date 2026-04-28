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

requireCompany();

$db     = (new Database())->getConnection();
$search = $_GET["search"] ?? "";
$page   = max(1, (int)($_GET["page"] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Check if it's a single candidate request
$candidateId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($candidateId > 0) {
    // Single candidate detail
    $stmt = $db->prepare("
        SELECT
            u.id, u.name, u.email, u.phone, u.created_at,
            COUNT(DISTINCT a.id) AS total_applications,
            SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS total_shortlisted,
            SUM(CASE WHEN a.status = 'invited'     THEN 1 ELSE 0 END) AS total_invited,
            SUM(CASE WHEN a.status = 'rejected'    THEN 1 ELSE 0 END) AS total_rejected
        FROM users u
        LEFT JOIN applications a ON u.id = a.candidate_id
        WHERE u.id = :id AND u.role = 'candidate'
        GROUP BY u.id
    ");
    $stmt->execute([":id" => $candidateId]);
    $candidate = $stmt->fetch();

    if (!$candidate) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Candidate not found"]);
        exit();
    }

    // Get application history
    $stmt = $db->prepare("
        SELECT a.status, a.applied_at, j.title AS job_title, j.company
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.candidate_id = :id
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([":id" => $candidateId]);
    $applications = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data"    => [
            "candidate"    => $candidate,
            "applications" => $applications
        ]
    ]);
    exit();
}

// Build search filter
$where  = ["u.role = 'candidate'"];
$params = [];

if ($search) {
    $where[]           = "(u.name LIKE :search OR u.email LIKE :search)";
    $params[":search"] = "%{$search}%";
}

$whereSQL = "WHERE " . implode(" AND ", $where);

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM users u {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()["total"];

// Fetch candidates
$params[":limit"]  = $limit;
$params[":offset"] = $offset;

$stmt = $db->prepare("
    SELECT
        u.id, u.name, u.email, u.phone, u.created_at,
        COUNT(DISTINCT a.id)  AS total_applications,
        SUM(CASE WHEN a.status = 'invited'     THEN 1 ELSE 0 END) AS total_invited,
        SUM(CASE WHEN a.status = 'shortlisted' THEN 1 ELSE 0 END) AS total_shortlisted
    FROM users u
    LEFT JOIN applications a ON u.id = a.candidate_id
    {$whereSQL}
    GROUP BY u.id
    ORDER BY u.created_at DESC
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
$candidates = $stmt->fetchAll();

// Stats
$statsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT u.id) AS total,
        COUNT(DISTINCT a.candidate_id) AS applied,
        SUM(CASE WHEN u.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END) AS new_this_month,
        SUM(CASE WHEN a.status = 'invited' THEN 1 ELSE 0 END) AS invited
    FROM users u
    LEFT JOIN applications a ON u.id = a.candidate_id
    WHERE u.role = 'candidate'
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

echo json_encode([
    "success" => true,
    "message" => "Candidates fetched successfully",
    "data"    => [
        "candidates"  => $candidates,
        "total"       => $total,
        "total_pages" => ceil($total / $limit),
        "page"        => $page,
        "stats"       => $stats
    ]
]);
?>