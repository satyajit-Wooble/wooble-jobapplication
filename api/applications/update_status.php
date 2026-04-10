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
require_once __DIR__ . "/../helpers/send_mail.php";

// --- Admin only ---
$admin = requireAdmin();

$data           = json_decode(file_get_contents("php://input"), true);
$application_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($application_id <= 0) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Valid application ID is required. Example: ?id=1"
    ]);
    exit();
}

// --- Validate status ---
$status         = strtolower(trim($data["status"] ?? ""));
$admin_note     = trim($data["admin_note"] ?? "");
$interview_date = trim($data["interview_date"] ?? "");
$message        = trim($data["message"] ?? "");

$validStatuses = ["pending", "shortlisted", "invited", "rejected"];
if (!in_array($status, $validStatuses)) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "status must be: pending, shortlisted, invited or rejected"
    ]);
    exit();
}

$db = (new Database())->getConnection();

// --- Check application exists and belongs to admin's job ---
$stmt = $db->prepare("
    SELECT
        a.id,
        a.status        AS current_status,
        a.candidate_id,
        a.job_id,
        u.name          AS candidate_name,
        u.email         AS candidate_email,
        j.title         AS job_title,
        j.company
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.candidate_id = u.id
    WHERE a.id = :id AND j.admin_id = :admin_id
    LIMIT 1
");
$stmt->execute([
    ":id"       => $application_id,
    ":admin_id" => $admin["user_id"]
]);
$application = $stmt->fetch();

if (!$application) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Application not found or you do not have permission"
    ]);
    exit();
}

// --- Update application status ---
$stmt = $db->prepare("
    UPDATE applications
    SET status = :status, admin_note = :admin_note, updated_at = NOW()
    WHERE id = :id
");
$stmt->execute([
    ":status"     => $status,
    ":admin_note" => $admin_note ?: null,
    ":id"         => $application_id
]);

// --- If invited, save invitation record ---
if ($status === "invited") {

    if (!empty($interview_date)) {
        $dateValid = DateTime::createFromFormat("Y-m-d H:i:s", $interview_date);
        if (!$dateValid) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "interview_date format must be: YYYY-MM-DD HH:MM:SS"
            ]);
            exit();
        }
    }

    // Check if invitation already exists
    $checkStmt = $db->prepare("
        SELECT id FROM invitations
        WHERE application_id = :application_id
        LIMIT 1
    ");
    $checkStmt->execute([":application_id" => $application_id]);

    if ($checkStmt->fetch()) {
        // Update existing invitation
        $invStmt = $db->prepare("
            UPDATE invitations
            SET interview_date = :interview_date,
                message        = :message,
                sent_at        = NOW()
            WHERE application_id = :application_id
        ");
        $invStmt->execute([
            ":interview_date"  => $interview_date ?: null,
            ":message"         => $message ?: null,
            ":application_id"  => $application_id
        ]);
    } else {
        // Insert new invitation
        $invStmt = $db->prepare("
            INSERT INTO invitations
                (application_id, candidate_id, job_id, interview_date, message, sent_at)
            VALUES
                (:application_id, :candidate_id, :job_id, :interview_date, :message, NOW())
        ");
        $invStmt->execute([
            ":application_id" => $application_id,
            ":candidate_id"   => $application["candidate_id"],
            ":job_id"         => $application["job_id"],
            ":interview_date" => $interview_date ?: null,
            ":message"        => $message ?: null
        ]);
    }
}

// --- Send Email Based on Status ---
$mailResult = ["success" => false, "message" => "No email sent for pending status"];

switch ($status) {
    case "shortlisted":
        $mailResult = mailShortlisted(
            $application["candidate_email"],
            $application["candidate_name"],
            $application["job_title"],
            $application["company"],
            $admin_note
        );
        break;

    case "invited":
        $mailResult = mailInvited(
            $application["candidate_email"],
            $application["candidate_name"],
            $application["job_title"],
            $application["company"],
            $interview_date,
            $message
        );
        break;

    case "rejected":
        $mailResult = mailRejected(
            $application["candidate_email"],
            $application["candidate_name"],
            $application["job_title"],
            $application["company"],
            $admin_note
        );
        break;
}

// --- Build response messages ---
$messages = [
    "pending"     => "Application marked as pending",
    "shortlisted" => "Candidate has been shortlisted",
    "invited"     => "Interview invitation sent to candidate",
    "rejected"    => "Application has been rejected"
];

echo json_encode([
    "success" => true,
    "message" => $messages[$status],
    "data"    => [
        "application" => [
            "id"              => $application_id,
            "previous_status" => $application["current_status"],
            "new_status"      => $status,
            "admin_note"      => $admin_note ?: null,
            "candidate_name"  => $application["candidate_name"],
            "candidate_email" => $application["candidate_email"],
            "job_title"       => $application["job_title"],
            "company"         => $application["company"],
            "interview_date"  => $status === "invited" ? ($interview_date ?: null) : null,
            "message"         => $status === "invited" ? ($message ?: null) : null,
            "updated_at"      => date("Y-m-d H:i:s")
        ],
        "email_sent"    => $mailResult["success"],
        "email_message" => $mailResult["message"]
    ]
]);
?>