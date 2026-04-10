<?php
require_once __DIR__ . "/../config/jwt.php";

/**
 * Authenticate any logged in user (admin or candidate)
 */
function authenticate(): array {
    $headers = getallheaders();
    $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    if (!$authHeader || !str_starts_with($authHeader, "Bearer ")) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: No token provided"
        ]);
        exit();
    }

    $token   = substr($authHeader, 7);
    $decoded = JWT::validate($token);

    if (!$decoded) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Invalid or expired token"
        ]);
        exit();
    }

    return $decoded;
}

/**
 * Only Admin can access
 */
function requireAdmin(): array {
    $user = authenticate();
    if ($user["role"] !== "admin") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Forbidden: Admin access only"
        ]);
        exit();
    }
    return $user;
}

/**
 * Only Candidate can access
 */
function requireCandidate(): array {
    $user = authenticate();
    if ($user["role"] !== "candidate") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Forbidden: Candidate access only"
        ]);
        exit();
    }
    return $user;
}
?>