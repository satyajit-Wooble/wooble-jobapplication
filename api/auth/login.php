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

$data = json_decode(file_get_contents("php://input"), true);

$email    = strtolower(trim($data["email"] ?? ""));
$password = $data["password"] ?? "";

// --- Validation ---
if (!$email || !$password) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);
    exit();
}

// --- Find user ---
$db   = (new Database())->getConnection();
$stmt = $db->prepare("
    SELECT id, name, email, password, role, phone 
    FROM users 
    WHERE email = :email 
    LIMIT 1
");
$stmt->execute([":email" => $email]);
$user = $stmt->fetch();

// --- Verify password ---
if (!$user || !password_verify($password, $user["password"])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);
    exit();
}

// --- Generate JWT ---
$token = JWT::generate([
    "user_id" => $user["id"],
    "name"    => $user["name"],
    "email"   => $user["email"],
    "role"    => $user["role"]
]);

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "data"    => [
        "user" => [
            "id"    => (int)$user["id"],
            "name"  => $user["name"],
            "email" => $user["email"],
            "phone" => $user["phone"],
            "role"  => $user["role"]
        ],
        "token" => $token
    ]
]);
?>