<?php
// mailer.php - Automated Grade Email Dispatcher

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load the 3 PHPMailer files directly from your PHPMailer folder
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

function sendGradeEmail($recipientEmail, $recipientName, $projectTrack, $section, $grade, $feedback) {
    $mail = new PHPMailer(true);

    try {
        // --- SMTP SERVER CONFIGURATION ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // Put your Gmail and 16-character Google App Password here:
        $mail->Username   = 'marifelpsacc@gmail.com';
        $mail->Password   = 'praa orsh bpvk mfzb'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- SENDER & RECIPIENT ---
        $mail->setFrom('marifelpsacc@gmail.com', 'ITE 1 Project Grade');
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->isHTML(true);

        // --- EMAIL SUBJECT & BODY ---
        $mail->Subject = "Project Result: ITE 1 - Section " . $section;

        $feedbackText = !empty($feedback) 
            ? nl2br(htmlspecialchars($feedback)) 
            : 'No additional remarks provided. Great effort completing your project deliverables!';

        $mail->Body = "
        <div style='background-color: #f8f9fe; padding: 30px; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; color: #334155;'>
            <div style='max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 1px solid #ede9f6; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                
                <div style='background: #6b3ba7; padding: 25px; text-align: center; color: #ffffff;'>
                    <h2 style='margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 0.5px;'>ITE 1 PROJECT  RESULT</h2>
                    <p style='margin: 5px 0 0; font-size: 13px; opacity: 0.9;'>Academic Year 2026 – 2027</p>
                </div>

                <div style='padding: 30px;'>
                    <p style='margin-top: 0; font-size: 15px;'>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                    <p style='font-size: 14px; line-height: 1.6; color: #64748b;'>
                        Your submitted project for <strong>Section " . htmlspecialchars($section) . "</strong> under the <strong>" . htmlspecialchars($projectTrack) . "</strong> track has been evaluated. Below are your official grading details:
                    </p>

                    <!-- GRADE DISPLAY CARD -->
                    <div style='background: #faf8fd; border: 2px dashed #6b3ba7; border-radius: 16px; padding: 20px; text-align: center; margin: 25px 0;'>
                        <span style='font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 1px;'>Final Project Grade</span>
                        <div style='font-size: 38px; font-weight: 900; color: #43236e; margin-top: 5px;'>" . htmlspecialchars($grade) . "</div>
                    </div>

                    <!-- REMARKS & FEEDBACK -->
                    <div style='background: #f8fafc; border-radius: 12px; padding: 16px; font-size: 13px; line-height: 1.6; border: 1px solid #e2e8f0; margin-bottom: 25px;'>
                        <strong style='color: #334155; display: block; margin-bottom: 5px;'>Evaluator Remarks & Feedback:</strong>
                        <span style='color: #475569;'>{$feedbackText}</span>
                    </div>

                    <p style='font-size: 12px; color: #94a3b8; line-height: 1.5; margin-bottom: 0;'>
                        * This is an automated academic confirmation. Please retain this email for your grading portfolio.
                    </p>
                </div>

                <div style='background: #fdfcff; border-top: 1px solid #ede9f6; padding: 15px; text-align: center; font-size: 11px; color: #94a3b8;'>
                    © 2026 ITE 1 Project. All rights reserved.
                </div>
            </div>
        </div>";

        $mail->AltBody = "Hello {$recipientName},\n\nYour ITE 1 Project has been graded.\nSection: {$section}\nTrack: {$projectTrack}\nGrade: {$grade}\nFeedback: {$feedback}\n\n© 2026 ITE 1 Academic Department";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Grade email dispatch failed to {$recipientEmail}: " . $mail->ErrorInfo);
        return false;
    }
}