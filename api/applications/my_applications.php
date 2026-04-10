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

// --- Candidate only ---
$candidate = requireCandidate();

$db = (new Database())->getConnection();

// --- Optional filters ---
$status = $_GET["status"] ?? "";
$page   = max(1, (int)($_GET["page"] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = ["a.candidate_id = :candidate_id"];
$params = [":candidate_id" => $candidate["user_id"]];

if ($status) {
    $validStatuses = ["pending", "shortlisted", "invited", "rejected"];
    if (!in_array($status, $validStatuses)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "status must be: pending, shortlisted, invited or rejected"
        ]);
        exit();
    }
    $where[]          = "a.status = :status";
    $params[":status"] = $status;
}

$whereSQL = implode(" AND ", $where);

// --- Count total ---
$countStmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM applications a 
    WHERE {$whereSQL}
");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()["total"];

// --- Fetch applications ---
$params[":limit"]  = $limit;
$params[":offset"] = $offset;

$stmt = $db->prepare("
    SELECT
        a.id            AS application_id,
        a.status,
        a.cover_letter,
        a.resume_path,
        a.admin_note,
        a.applied_at,
        a.updated_at,
        j.id            AS job_id,
        j.title         AS job_title,
        j.company,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.status        AS job_status
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE {$whereSQL}
    ORDER BY a.applied_at DESC
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
$applications = $stmt->fetchAll();

// --- Status summary count ---
$summaryStmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM applications
    WHERE candidate_id = :candidate_id
    GROUP BY status
");
$summaryStmt->execute([":candidate_id" => $candidate["user_id"]]);
$summaryRows = $summaryStmt->fetchAll();

$summary = [
    "pending"     => 0,
    "shortlisted" => 0,
    "invited"     => 0,
    "rejected"    => 0
];
foreach ($summaryRows as $row) {
    $summary[$row["status"]] = (int)$row["count"];
}

echo json_encode([
    "success" => true,
    "message" => "Applications fetched successfully",
    "data"    => [
        "summary"      => $summary,
        "applications" => $applications,
        "total"        => $total,
        "page"         => $page,
        "limit"        => $limit,
        "total_pages"  => ceil($total / $limit)
    ]
]);
?>