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

// Company only
requireCompany();

$data    = json_decode(file_get_contents("php://input"), true);
$user_id = (int)($data["user_id"] ?? 0);
$action  = strtolower(trim($data["action"] ?? ""));
$note    = trim($data["note"] ?? "");

if (!$user_id || !in_array($action, ["approve", "reject"])) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "user_id and action (approve/reject) are required"
    ]);
    exit();
}

$db     = (new Database())->getConnection();
$status = $action === "approve" ? "approved" : "rejected";

// Update employer profile status
$stmt = $db->prepare("
    UPDATE employer_profiles
    SET status = :status, updated_at = NOW()
    WHERE user_id = :user_id
");
$stmt->execute([
    ":status"  => $status,
    ":user_id" => $user_id
]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Employer not found"
    ]);
    exit();
}

// Get employer details for response
$stmt = $db->prepare("
    SELECT u.name, u.email, ep.company_name
    FROM users u
    JOIN employer_profiles ep ON u.id = ep.user_id
    WHERE u.id = :user_id
");
$stmt->execute([":user_id" => $user_id]);
$employer = $stmt->fetch();

$message = $action === "approve"
    ? "Employer '{$employer['company_name']}' approved successfully"
    : "Employer '{$employer['company_name']}' rejected";

echo json_encode([
    "success" => true,
    "message" => $message,
    "data"    => [
        "user_id"      => $user_id,
        "company_name" => $employer["company_name"],
        "name"         => $employer["name"],
        "email"        => $employer["email"],
        "status"       => $status,
        "action"       => $action,
        "note"         => $note ?: null
    ]
]);
?>