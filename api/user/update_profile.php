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

// --- Any logged in user ---
$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);

$db = (new Database())->getConnection();

// --- Fetch current user ---
$stmt = $db->prepare("
    SELECT id, name, email, phone, password 
    FROM users 
    WHERE id = :id 
    LIMIT 1
");
$stmt->execute([":id" => $user["user_id"]]);
$current = $stmt->fetch();

if (!$current) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit();
}

// --- Build update fields dynamically ---
$fields = [];
$params = [":id" => $user["user_id"]];
$updated = [];

// Update name
if (!empty($data["name"])) {
    $name = trim($data["name"]);
    if (strlen($name) < 2) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "Name must be at least 2 characters"
        ]);
        exit();
    }
    $fields[]        = "name = :name";
    $params[":name"] = $name;
    $updated[]       = "name";
}

// Update phone
if (array_key_exists("phone", $data)) {
    $phone = trim($data["phone"]);
    if ($phone && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "Invalid phone number format"
        ]);
        exit();
    }
    $fields[]         = "phone = :phone";
    $params[":phone"] = $phone ?: null;
    $updated[]        = "phone";
}

// --- Change password ---
if (!empty($data["current_password"]) || !empty($data["new_password"])) {

    $currentPassword = $data["current_password"] ?? "";
    $newPassword     = $data["new_password"]     ?? "";
    $confirmPassword = $data["confirm_password"] ?? "";

    // Validate current password
    if (!password_verify($currentPassword, $current["password"])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Current password is incorrect"
        ]);
        exit();
    }

    // Validate new password
    if (strlen($newPassword) < 6) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "New password must be at least 6 characters"
        ]);
        exit();
    }

    // Validate confirm password
    if ($newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "New passwords do not match"
        ]);
        exit();
    }

    // Check new password is not same as current
    if (password_verify($newPassword, $current["password"])) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "message" => "New password cannot be same as current password"
        ]);
        exit();
    }

    $fields[]            = "password = :password";
    $params[":password"] = password_hash($newPassword, PASSWORD_BCRYPT);
    $updated[]           = "password";
}

// --- Nothing to update ---
if (empty($fields)) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "No fields provided to update"
    ]);
    exit();
}

// --- Run update ---
$fields[]  = "updated_at = NOW()";
$fieldsSQL = implode(", ", $fields);

$stmt = $db->prepare("UPDATE users SET {$fieldsSQL} WHERE id = :id");
$stmt->execute($params);

// --- Fetch updated profile ---
$stmt = $db->prepare("
    SELECT id, name, email, phone, role, created_at 
    FROM users 
    WHERE id = :id 
    LIMIT 1
");
$stmt->execute([":id" => $user["user_id"]]);
$updatedProfile = $stmt->fetch();

echo json_encode([
    "success" => true,
    "message" => "Profile updated successfully",
    "data"    => [
        "profile" => [
            "id"     => (int)$updatedProfile["id"],
            "name"   => $updatedProfile["name"],
            "email"  => $updatedProfile["email"],
            "phone"  => $updatedProfile["phone"],
            "role"   => $updatedProfile["role"],
            "joined" => $updatedProfile["created_at"],
            "avatar" => strtoupper(substr($updatedProfile["name"], 0, 1))
        ],
        "updated_fields" => $updated
    ]
]);
?>