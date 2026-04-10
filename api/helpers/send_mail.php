<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/../config/mail.php";

/**
 * Core mail sender function
 * 
 * @param string $toEmail    Recipient email
 * @param string $toName     Recipient name
 * @param string $subject    Email subject
 * @param string $body       HTML email body
 * @return array             ['success' => bool, 'message' => string]
 */
function sendMail(string $toEmail, string $toName, string $subject, string $body): array {
    $mail = new PHPMailer(true);

    try {
        // ── Server Settings ──────────────────────
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // ── Sender ───────────────────────────────
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // ── Recipient ────────────────────────────
        $mail->addAddress($toEmail, $toName);

        // ── Content ──────────────────────────────
        $mail->isHTML(true);
        $mail->CharSet = "UTF-8";
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace("<br>", "\n", $body));

        $mail->send();

        return [
            "success" => true,
            "message" => "Email sent successfully to {$toEmail}"
        ];

    } catch (Exception $e) {
        return [
            "success" => false,
            "message" => "Email failed: " . $mail->ErrorInfo
        ];
    }
}

/**
 * Base HTML email template wrapper
 */
function emailTemplate(string $title, string $content): string {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
    </head>
    <body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:40px 0;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);'>

                        <!-- Header -->
                        <tr>
                            <td style='background:#1E3A5F;padding:30px;text-align:center;'>
                                <h1 style='margin:0;color:#ffffff;font-size:24px;letter-spacing:1px;'>
                                    💼 Wooble Jobs
                                </h1>
                                <p style='margin:8px 0 0;color:#a8c4e0;font-size:13px;'>
                                    Job Board & Application Tracker
                                </p>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style='padding:40px 40px 30px;color:#333333;'>
                                {$content}
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='background:#f4f6f9;padding:20px;text-align:center;border-top:1px solid #e0e0e0;'>
                                <p style='margin:0;color:#999999;font-size:12px;'>
                                    &copy; 2025 Wooble Jobs. All rights reserved.
                                </p>
                                <p style='margin:5px 0 0;color:#999999;font-size:12px;'>
                                    This is an automated email, please do not reply.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
}

/**
 * ─────────────────────────────────────────────
 * EMAIL TEMPLATES
 * ─────────────────────────────────────────────
 */

/**
 * 1. Welcome email after registration
 */
function mailWelcome(string $toEmail, string $toName, string $role): array {
    $roleLabel = ucfirst($role);
    $content = "
        <h2 style='color:#1E3A5F;margin-top:0;'>Welcome to Wooble Jobs, {$toName}! 🎉</h2>
        <p style='color:#555;line-height:1.7;'>
            Thank you for registering as a <strong>{$roleLabel}</strong> on Wooble Jobs.
            Your account has been created successfully.
        </p>
        " . ($role === 'candidate' ? "
        <p style='color:#555;line-height:1.7;'>You can now:</p>
        <ul style='color:#555;line-height:2;'>
            <li>Browse and search active job listings</li>
            <li>Apply for jobs with your resume and cover letter</li>
            <li>Track your application status in real time</li>
        </ul>
        " : "
        <p style='color:#555;line-height:1.7;'>You can now:</p>
        <ul style='color:#555;line-height:2;'>
            <li>Post and manage job listings</li>
            <li>Review candidate applications</li>
            <li>Shortlist, invite, or reject candidates</li>
        </ul>
        ") . "
        <div style='text-align:center;margin:30px 0;'>
            <a href='" . APP_URL . "' 
               style='background:#1E3A5F;color:#ffffff;padding:14px 32px;border-radius:6px;
                      text-decoration:none;font-weight:bold;font-size:15px;display:inline-block;'>
                Go to Wooble Jobs
            </a>
        </div>
        <p style='color:#999;font-size:13px;'>
            If you did not create this account, please ignore this email.
        </p>
    ";

    return sendMail(
        $toEmail,
        $toName,
        "Welcome to Wooble Jobs — Account Created!",
        emailTemplate("Welcome to Wooble Jobs", $content)
    );
}

/**
 * 2. Application received email — sent to candidate
 */
function mailApplicationReceived(
    string $toEmail,
    string $toName,
    string $jobTitle,
    string $company,
    int    $applicationId
): array {
    $content = "
        <h2 style='color:#1E3A5F;margin-top:0;'>Application Received! ✅</h2>
        <p style='color:#555;line-height:1.7;'>
            Hi <strong>{$toName}</strong>, your application has been submitted successfully.
        </p>
        <table width='100%' cellpadding='0' cellspacing='0' 
               style='background:#f0f4f8;border-radius:6px;padding:20px;margin:20px 0;'>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Job Title:</strong> {$jobTitle}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Company:</strong> {$company}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Application ID:</strong> #{$applicationId}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Status:</strong> 
                    <span style='background:#fff3cd;color:#856404;padding:3px 10px;
                                 border-radius:20px;font-size:13px;'>Pending Review</span>
                </td>
            </tr>
        </table>
        <p style='color:#555;line-height:1.7;'>
            Our team will review your application and get back to you soon. 
            You can track your application status anytime.
        </p>
        <p style='color:#999;font-size:13px;margin-top:20px;'>
            Good luck with your application! 🍀
        </p>
    ";

    return sendMail(
        $toEmail,
        $toName,
        "Application Received — {$jobTitle} at {$company}",
        emailTemplate("Application Received", $content)
    );
}

/**
 * 3. Application shortlisted email — sent to candidate
 */
function mailShortlisted(
    string $toEmail,
    string $toName,
    string $jobTitle,
    string $company,
    string $adminNote = ""
): array {
    $noteHtml = $adminNote
        ? "<p style='color:#555;line-height:1.7;'><strong>Note from recruiter:</strong> {$adminNote}</p>"
        : "";

    $content = "
        <h2 style='color:#1E3A5F;margin-top:0;'>Congratulations! You've Been Shortlisted 🌟</h2>
        <p style='color:#555;line-height:1.7;'>
            Hi <strong>{$toName}</strong>, great news! You have been shortlisted for the following position.
        </p>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='background:#f0f4f8;border-radius:6px;padding:20px;margin:20px 0;'>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Job Title:</strong> {$jobTitle}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Company:</strong> {$company}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;'>
                    <strong style='color:#1E3A5F;'>Status:</strong>
                    <span style='background:#d4edda;color:#155724;padding:3px 10px;
                                 border-radius:20px;font-size:13px;'>Shortlisted</span>
                </td>
            </tr>
        </table>
        {$noteHtml}
        <p style='color:#555;line-height:1.7;'>
            The recruiter will be in touch with you regarding the next steps.
            Please keep an eye on your email for further updates.
        </p>
    ";

    return sendMail(
        $toEmail,
        $toName,
        "You've Been Shortlisted — {$jobTitle} at {$company}",
        emailTemplate("Shortlisted!", $content)
    );
}

/**
 * 4. Interview invitation email — sent to candidate
 */
function mailInvited(
    string $toEmail,
    string $toName,
    string $jobTitle,
    string $company,
    string $interviewDate = "",
    string $message = ""
): array {
    $dateHtml = $interviewDate
        ? "<tr>
               <td style='padding:8px 0;color:#555;'>
                   <strong style='color:#1E3A5F;'>Interview Date:</strong> 
                   <strong style='color:#2E75B6;'>{$interviewDate}</strong>
               </td>
           </tr>"
        : "";

    $messageHtml = $message
        ? "<div style='background:#e8f4fd;border-left:4px solid #2E75B6;
                       padding:15px 20px;border-radius:0 6px 6px 0;margin:20px 0;'>
               <p style='margin:0;color:#555;line-height:1.7;font-style:italic;'>
                   \"{$message}\"
               </p>
           </div>"
        : "";

    $content = "
        <h2 style='color:#1E3A5F;margin-top:0;'>Interview Invitation 🎯</h2>
        <p style='color:#555;line-height:1.7;'>
            Hi <strong>{$toName}</strong>, you have been invited for an interview!
        </p>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='background:#f0f4f8;border-radius:6px;padding:20px;margin:20px 0;'>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Job Title:</strong> {$jobTitle}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Company:</strong> {$company}
                </td>
            </tr>
            {$dateHtml}
            <tr>
                <td style='padding:8px 0;'>
                    <strong style='color:#1E3A5F;'>Status:</strong>
                    <span style='background:#cce5ff;color:#004085;padding:3px 10px;
                                 border-radius:20px;font-size:13px;'>Interview Invited</span>
                </td>
            </tr>
        </table>
        {$messageHtml}
        <p style='color:#555;line-height:1.7;'>
            Please confirm your availability and be prepared for the interview.
            If you have any questions, feel free to contact us.
        </p>
        <p style='color:#555;line-height:1.7;'>
            Best of luck! 🍀
        </p>
    ";

    return sendMail(
        $toEmail,
        $toName,
        "Interview Invitation — {$jobTitle} at {$company}",
        emailTemplate("Interview Invitation", $content)
    );
}

/**
 * 5. Application rejected email — sent to candidate
 */
function mailRejected(
    string $toEmail,
    string $toName,
    string $jobTitle,
    string $company,
    string $adminNote = ""
): array {
    $noteHtml = $adminNote
        ? "<p style='color:#555;line-height:1.7;'><strong>Feedback:</strong> {$adminNote}</p>"
        : "";

    $content = "
        <h2 style='color:#1E3A5F;margin-top:0;'>Application Status Update</h2>
        <p style='color:#555;line-height:1.7;'>
            Hi <strong>{$toName}</strong>, thank you for your interest in the position below.
        </p>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='background:#f0f4f8;border-radius:6px;padding:20px;margin:20px 0;'>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Job Title:</strong> {$jobTitle}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#555;'>
                    <strong style='color:#1E3A5F;'>Company:</strong> {$company}
                </td>
            </tr>
            <tr>
                <td style='padding:8px 0;'>
                    <strong style='color:#1E3A5F;'>Status:</strong>
                    <span style='background:#f8d7da;color:#721c24;padding:3px 10px;
                                 border-radius:20px;font-size:13px;'>Not Selected</span>
                </td>
            </tr>
        </table>
        {$noteHtml}
        <p style='color:#555;line-height:1.7;'>
            After careful consideration, we regret to inform you that we will not be 
            moving forward with your application at this time. We encourage you to apply 
            for future openings that match your skills and experience.
        </p>
        <p style='color:#555;line-height:1.7;'>
            Thank you for your time and we wish you the best in your job search. 💪
        </p>
    ";

    return sendMail(
        $toEmail,
        $toName,
        "Application Update — {$jobTitle} at {$company}",
        emailTemplate("Application Update", $content)
    );
}
?>