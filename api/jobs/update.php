<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit(0);
if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method Not Allowed"]);
    exit();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../middleware/auth.php";

// --- Admin only ---
$admin = requireAdmin();

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

$db = (new Database())->getConnection();

// --- Check job exists and belongs to this admin ---
$stmt = $db->prepare("SELECT id FROM jobs WHERE id = :id AND admin_id = :admin_id LIMIT 1");
$stmt->execute([
    ":id"       => $job_id,
    ":admin_id" => $admin["user_id"]
]);

if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Job not found or you do not have permission to update it"
    ]);
    exit();
}

// --- Build dynamic update fields ---
$fields = [];
$params = [":id" => $job_id];

if (!empty($data["title"])) {
    $fields[]         = "title = :title";
    $params[":title"] = trim($data["title"]);
}
if (!empty($data["company"])) {
    $fields[]           = "company = :company";
    $params[":company"] = trim($data["company"]);
}
if (!empty($data["location"])) {
    $fields[]            = "location = :location";
    $params[":location"] = trim($data["location"]);
}
if (!empty($data["job_type"])) {
    $validTypes = ["full-time", "part-time", "remote", "contract"];
    if (!in_array($data["job_type"], $validTypes)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "job_type must be: full-time, part-time, remote or contract"
        ]);
        exit();
    }
    $fields[]             = "job_type = :job_type";
    $params[":job_type"]  = $data["job_type"];
}
if (!empty($data["description"])) {
    $fields[]               = "description = :description";
    $params[":description"] = trim($data["description"]);
}
if (isset($data["requirements"])) {
    $fields[]                = "requirements = :requirements";
    $params[":requirements"] = trim($data["requirements"]);
}
if (isset($data["salary_min"])) {
    $fields[]              = "salary_min = :salary_min";
    $params[":salary_min"] = (float)$data["salary_min"];
}
if (isset($data["salary_max"])) {
    $fields[]              = "salary_max = :salary_max";
    $params[":salary_max"] = (float)$data["salary_max"];
}
if (!empty($data["status"])) {
    if (!in_array($data["status"], ["active", "closed"])) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "status must be 'active' or 'closed'"
        ]);
        exit();
    }
    $fields[]          = "status = :status";
    $params[":status"] = $data["status"];
}

if (empty($fields)) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "No fields provided to update"
    ]);
    exit();
}

$fields[]  = "updated_at = NOW()";
$fieldsSQL = implode(", ", $fields);

$stmt = $db->prepare("UPDATE jobs SET {$fieldsSQL} WHERE id = :id");
$stmt->execute($params);

// --- Return updated job ---
$stmt = $db->prepare("
    SELECT j.*, u.name AS posted_by 
    FROM jobs j 
    JOIN users u ON j.admin_id = u.id 
    WHERE j.id = :id
");
$stmt->execute([":id" => $job_id]);
$updatedJob = $stmt->fetch();

echo json_encode([
    "success" => true,
    "message" => "Job updated successfully",
    "data"    => [
        "job" => $updatedJob
    ]
]);
?>