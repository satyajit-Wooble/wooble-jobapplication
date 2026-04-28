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

// --- Employer or Company only ---
$user = requireEmployerOrCompany();

$data = json_decode(file_get_contents("php://input"), true);

// --- Validation ---
$required = ["title", "company", "location", "job_type", "description"];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "Field '{$field}' is required"
        ]);
        exit();
    }
}

$title        = trim($data["title"]);
$company      = trim($data["company"]);
$location     = trim($data["location"]);
$job_type     = strtolower(trim($data["job_type"]));
$description  = trim($data["description"]);
$requirements = trim($data["requirements"] ?? "");
$salary_min   = !empty($data["salary_min"]) ? (float)$data["salary_min"] : null;
$salary_max   = !empty($data["salary_max"]) ? (float)$data["salary_max"] : null;
$status       = $data["status"] ?? "active";

$validTypes = ["full-time", "part-time", "remote", "contract"];
if (!in_array($job_type, $validTypes)) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "job_type must be: full-time, part-time, remote or contract"
    ]);
    exit();
}

if (!in_array($status, ["active", "closed"])) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "status must be 'active' or 'closed'"
    ]);
    exit();
}

// --- Insert Job ---
$db   = (new Database())->getConnection();
$stmt = $db->prepare("
    INSERT INTO jobs 
        (posted_by, title, company, location, job_type, 
         description, requirements, salary_min, salary_max, 
         status, created_at)
    VALUES 
        (:posted_by, :title, :company, :location, :job_type, 
         :description, :requirements, :salary_min, :salary_max, 
         :status, NOW())
");

$stmt->execute([
    ":posted_by"    => $user["user_id"],
    ":title"        => $title,
    ":company"      => $company,
    ":location"     => $location,
    ":job_type"     => $job_type,
    ":description"  => $description,
    ":requirements" => $requirements ?: null,
    ":salary_min"   => $salary_min,
    ":salary_max"   => $salary_max,
    ":status"       => $status
]);

$jobId = $db->lastInsertId();

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Job created successfully",
    "data"    => [
        "job" => [
            "id"           => (int)$jobId,
            "title"        => $title,
            "company"      => $company,
            "location"     => $location,
            "job_type"     => $job_type,
            "description"  => $description,
            "requirements" => $requirements ?: null,
            "salary_min"   => $salary_min,
            "salary_max"   => $salary_max,
            "status"       => $status,
            "posted_by"    => $user["name"],
            "role"         => $user["role"]
        ]
    ]
]);
?>