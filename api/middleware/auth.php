<?php
require_once __DIR__ . "/../config/jwt.php";

/**
 * Authenticate any logged in user
 */
function authenticate(): array {
    $headers    = getallheaders();
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
 * Only Company (super admin) can access
 */
function requireCompany(): array {
    $user = authenticate();
    if ($user["role"] !== "company") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Forbidden: Company access only"
        ]);
        exit();
    }
    return $user;
}

/**
 * Only Employer can access
 */
function requireEmployer(): array {
    $user = authenticate();
    if ($user["role"] !== "employer") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Forbidden: Employer access only"
        ]);
        exit();
    }
    return $user;
}

/**
 * Employer OR Company can access
 */
function requireEmployerOrCompany(): array {
    $user = authenticate();
    if (!in_array($user["role"], ["employer", "company"])) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Forbidden: Employer or Company access only"
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

/**
 * Keep backward compatibility — admin = company
 */
function requireAdmin(): array {
    return requireCompany();
}
?>