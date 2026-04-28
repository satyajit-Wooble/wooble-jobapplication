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

$email    = strtolower(trim($data["email"]    ?? ""));
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
    SELECT
        u.id,
        u.name,
        u.email,
        u.password,
        u.role,
        u.phone
    FROM users u
    WHERE u.email = :email
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

// --- Get extra info based on role ---
$extraData = [];

if ($user["role"] === "employer") {
    $stmt = $db->prepare("
        SELECT
            company_name,
            website,
            industry,
            location,
            description,
            logo_path,
            status
        FROM employer_profiles
        WHERE user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([":user_id" => $user["id"]]);
    $profile = $stmt->fetch();

    if ($profile) {
        $extraData["employer_profile"] = $profile;

        // Check if employer is approved
        if ($profile["status"] === "pending") {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Your employer account is pending approval by Wooble. Please wait for approval.",
                "status"  => "pending"
            ]);
            exit();
        }

        if ($profile["status"] === "rejected") {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Your employer account has been rejected. Please contact Wooble for more information.",
                "status"  => "rejected"
            ]);
            exit();
        }
    }
}

// --- Generate JWT ---
$token = JWT::generate([
    "user_id" => $user["id"],
    "name"    => $user["name"],
    "email"   => $user["email"],
    "role"    => $user["role"]
]);

// --- Build response ---
$responseData = [
    "user" => [
        "id"    => (int)$user["id"],
        "name"  => $user["name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "role"  => $user["role"]
    ],
    "token" => $token
];

// Add employer profile to response
if (!empty($extraData)) {
    $responseData = array_merge($responseData, $extraData);
}

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "data"    => $responseData
]);
?>