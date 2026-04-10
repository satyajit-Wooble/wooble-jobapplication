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
require_once __DIR__ . "/../config/jwt.php";
require_once __DIR__ . "/../helpers/send_mail.php";

$data = json_decode(file_get_contents("php://input"), true);

// --- Validation ---
$required = ["name", "email", "password", "role"];
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

$name     = trim($data["name"]);
$email    = strtolower(trim($data["email"]));
$password = $data["password"];
$role     = strtolower(trim($data["role"]));
$phone    = trim($data["phone"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid email address"]);
    exit();
}

if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters"
    ]);
    exit();
}

if (!in_array($role, ["candidate", "admin"])) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Role must be 'candidate' or 'admin'"
    ]);
    exit();
}

// --- Check duplicate email ---
$db   = (new Database())->getConnection();
$stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$stmt->execute([":email" => $email]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Email already registered"
    ]);
    exit();
}

// --- Insert user ---
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt   = $db->prepare("
    INSERT INTO users (name, email, password, role, phone, created_at)
    VALUES (:name, :email, :password, :role, :phone, NOW())
");
$stmt->execute([
    ":name"     => $name,
    ":email"    => $email,
    ":password" => $hashed,
    ":role"     => $role,
    ":phone"    => $phone ?: null
]);
$userId = $db->lastInsertId();

// --- Generate JWT ---
$token = JWT::generate([
    "user_id" => $userId,
    "name"    => $name,
    "email"   => $email,
    "role"    => $role
]);

// --- Send Welcome Email ---
$mailResult = mailWelcome($email, $name, $role);

http_response_code(201);
echo json_encode([
    "success" => true,
    "message" => "Registration successful",
    "data"    => [
        "user" => [
            "id"    => (int)$userId,
            "name"  => $name,
            "email" => $email,
            "phone" => $phone ?: null,
            "role"  => $role
        ],
        "token"         => $token,
        "email_sent"    => $mailResult["success"],
        "email_message" => $mailResult["message"]
    ]
]);
?>