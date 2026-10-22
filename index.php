<?php
// Set timezone strictly to Philippine Time (PHT, GMT+8)
date_default_timezone_set('Asia/Manila');

// Connect to database
require 'db.php';
require 'mailer.php';

// Automatic Portal Closing Deadline: October 23, 2026, 02:00:00 AM PHT
$deadlineTimestamp = strtotime('2026-10-23 02:00:00');
$currentTimestamp  = time();
$isPortalClosed    = ($currentTimestamp >= $deadlineTimestamp);

$message = '';
$isSuccess = false;
$submittedData = null;

// Only process if submission was posted and portal is still OPEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($isPortalClosed) {
        $message = "The submission portal officially closed on October 23, 2026 at 2:00 AM (Philippine Time). No new submissions are accepted.";
    } else {
        $section       = $_POST['section'] ?? '';
        $projectOption = $_POST['project_option'] ?? '';
        $firstName     = trim($_POST['first_name'] ?? '');
        $lastName      = trim($_POST['last_name'] ?? '');
        $email         = strtolower(trim($_POST['email'] ?? ''));
        $projectLink   = trim($_POST['project_link'] ?? '');

        // Partner info (Client-based)
        $partnerFirstName = trim($_POST['partner_first_name'] ?? '');
        $partnerLastName  = trim($_POST['partner_last_name'] ?? '');
        $partnerEmail     = strtolower(trim($_POST['partner_email'] ?? ''));

        // Check if the student email (or partner email) has already submitted
        $checkSql = "SELECT id FROM submissions WHERE student1_email = ? OR student2_email = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$email, $email]);

        if ($checkStmt->rowCount() > 0) {
            $message = "A submission under {$email} is already permanently recorded. Resubmissions are locked.";
        } elseif ($projectOption === 'CLIENT-BASED' && !empty($partnerEmail)) {
            $checkPartner = $pdo->prepare($checkSql);
            $checkPartner->execute([$partnerEmail, $partnerEmail]);
            if ($checkPartner->rowCount() > 0) {
                $message = "The partner email {$partnerEmail} is already registered to an existing submission.";
            }
        }

        if (empty($message)) {
            // Handle Client Photo Upload
            $proofImageName = null;
            if ($projectOption === 'CLIENT-BASED' && !empty($_FILES['client_proof']['name'])) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                $proofImageName = time() . '_' . $_FILES['client_proof']['name'];
                move_uploaded_file($_FILES['client_proof']['tmp_name'], 'uploads/' . $proofImageName);
            }

            $referenceId = 'ITE1-' . date('Y') . '-' . rand(10000, 99999);
            $submittedTime = date('M d, Y - h:i A');

            // Insert into MySQL Database
            $sql = "INSERT INTO submissions (
                        reference_id, section, project_option, 
                        student1_first_name, student1_last_name, student1_email, 
                        student2_first_name, student2_last_name, student2_email, 
                        proof_image, project_link
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
            $stmt =$pdo->prepare($sql);$saved = $stmt->execute([$referenceId,
                $section,$projectOption,
                $firstName,$lastName,
                $email,$partnerFirstName ?: null,
                $partnerLastName ?: null,
                $partnerEmail ?: null,
                $proofImageName,$projectLink
            ]);

            if ($saved) {$isSuccess = true;
                
                $displayTrack = strtoupper($projectOption);
                if ($projectOption === 'PORTFOLIO' || $projectOption === 'STORYBOOK') {$displayTrack .= ' (INDIVIDUAL)';
                } elseif ($projectOption === 'CLIENT-BASED') {$displayTrack .= ' (BY PAIR)';
                }

                $submittedData = [
                    'id'        => $referenceId,
                    'section'   => $section,
                    'track'     => $displayTrack,
                    'name'      => strtoupper($firstName . ' ' .$lastName),
                    'email'     => $email,
                    'partner'   => ($projectOption === 'CLIENT-BASED' && !empty($partnerFirstName)) ? [
                        'name'  => strtoupper($partnerFirstName . ' ' .$partnerLastName),
                        'email' => $partnerEmail
                    ] : null,
                    'timestamp' => $submittedTime
                ];
            } else {
                $message = "Error submitting your project. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITE 1 Submission Portal</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f8f9fe] min-h-screen font-sans flex flex-col items-center justify-between p-4 sm:p-6 text-slate-700">

    <div class="w-full max-w-xl mx-auto">
        <!-- Logo Avatar -->
        <div class="flex justify-center mb-3">
            <div class="w-16 h-16 rounded-full border-2 border-purple-200 flex items-center justify-center bg-white shadow-sm">
                <svg class="w-9 h-9 text-[#6b3ba7]" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12c0 2.85 1.2 5.42 3.12 7.24.12-.4.32-.98.6-1.54.5-.98 1.28-2.02 2.38-2.58.4-.2.85-.32 1.34-.32h5.12c.49 0 .94.12 1.34.32 1.1.56 1.88 1.6 2.38 2.58.28.56.48 1.14.6 1.54C20.8 17.42 22 14.85 22 12c0-5.52-4.48-10-10-10zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm6 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm-3 6c-1.66 0-3-1.12-3-2.5h6c0 1.38-1.34 2.5-3 2.5z"/>
                </svg>
            </div>
        </div>

        <!-- Header Title -->
        <div class="text-center mb-6">
            <h1 class="text-3xl font-extrabold text-[#6b3ba7] tracking-tight">ITE 1 Submission Portal</h1>
            <p class="text-sm text-slate-500 font-medium mt-1">Submission Window Closes: October 23, 2026 at 2:00 AM (Philippine Time)</p>
        </div>

        <?php if ($isSuccess &&$submittedData): ?>
        <!-- ================= SUCCESS CONFIRMATION CARD ================= -->
        <div class="bg-white border border-[#ece8f5] rounded-3xl p-6 sm:p-10 shadow-sm text-center">
            
            <!-- Checkmark Icon Circle -->
            <div class="w-20 h-20 mx-auto bg-[#f1ebf9] text-[#6b3ba7] rounded-full flex items-center justify-center mb-6">
                <svg class="w-8 h-8 stroke-[#6b3ba7]" fill="none" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            
            <!-- Exact Quote Header -->
            <h2 class="text-xl sm:text-2xl font-extrabold text-[#43236e] leading-snug sm:leading-relaxed uppercase tracking-tight max-w-md mx-auto">
                “THANK YOU FOR SUBMITTING YOUR PROJECT, PLEASE WAIT FOR 2 – 3 DAYS TO RECEIVE YOUR PROJECT GRADE TO YOUR EMAIL ADDRESS.”
            </h2>

            <!-- Structured Details Box -->
            <div class="bg-[#faf9fd] border border-[#ede9f6] rounded-2xl p-5 sm:p-6 mt-8 text-left space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 text-xs sm:text-sm">
                    <span class="text-slate-500 font-medium">Section:</span>
                    <span class="font-extrabold text-[#37215c]"><?= htmlspecialchars($submittedData['section']) ?></span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 text-xs sm:text-sm">
                    <span class="text-slate-500 font-medium">Selected Track:</span>
                    <span class="font-extrabold text-[#6b3ba7]"><?= htmlspecialchars($submittedData['track']) ?></span>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200/60 pb-2.5 text-xs sm:text-sm gap-1 sm:gap-0">
                    <span class="text-slate-500 font-medium">Student:</span>
                    <span class="font-bold text-slate-800 text-right">
                        <?= htmlspecialchars($submittedData['name']) ?> <span class="text-slate-600 font-normal">(<?= htmlspecialchars($submittedData['email']) ?>)</span>
                    </span>
                </div>

                <?php if ($submittedData['partner']): ?>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200/60 pb-2.5 text-xs sm:text-sm gap-1 sm:gap-0">
                    <span class="text-slate-500 font-medium">Partner:</span>
                    <span class="font-bold text-slate-800 text-right">
                        <?= htmlspecialchars($submittedData['partner']['name']) ?> <span class="text-slate-600 font-normal">(<?= htmlspecialchars($submittedData['partner']['email']) ?>)</span>
                    </span>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between pt-0.5 text-xs sm:text-sm">
                    <span class="text-slate-500 font-medium">Submitted Timestamp:</span>
                    <span class="font-medium text-slate-600"><?= htmlspecialchars($submittedData['timestamp']) ?></span>
                </div>
            </div>

            <!-- Footer Lock Notice -->
            <p class="text-[12px] sm:text-[13px] text-slate-400 italic text-center mt-6">
                Note: Your submission is now permanently recorded. Resubmissions under the same email will be locked.
            </p>
        </div>

        <?php elseif ($isPortalClosed): ?>
        <!-- ================= PORTAL CLOSED NOTICE ================= -->
        <div class="bg-white border border-red-200 rounded-3xl p-8 sm:p-10 shadow-sm text-center">
            <div class="w-20 h-20 mx-auto bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight mb-2">Portal Officially Closed</h2>
            <p class="text-sm text-slate-500 max-w-sm mx-auto mb-6">
                The submission window expired on <strong class="text-slate-700">October 23, 2026 at 2:00 AM (Philippine Time)</strong>. The system is no longer accepting student project entries.
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-500">
                If you have questions or encountered technical issues, please contact your instructor directly.
            </div>
        </div>

        <?php else: ?>

        <!-- ================= 3-STEP NAVIGATION PILL ================= -->
        <div class="bg-white border border-[#e8e5f2] rounded-2xl p-2.5 mb-6 flex items-center justify-around text-xs font-semibold shadow-xs">
            <div id="step-nav-1" class="step-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#ede7f6] text-[#6b3ba7]">
                <span class="w-5 h-5 rounded-full bg-[#6b3ba7] text-white flex items-center justify-center text-[10px] font-bold">1</span>
                <span>Section</span>
            </div>
            <span class="text-slate-300">›</span>

            <div id="step-nav-2" class="step-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full text-slate-400">
                <span class="w-5 h-5 rounded-full bg-slate-300 text-white flex items-center justify-center text-[10px] font-bold">2</span>
                <span>Project Option</span>
            </div>
            <span class="text-slate-300">›</span>

            <div id="step-nav-3" class="step-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full text-slate-400">
                <span class="w-5 h-5 rounded-full bg-slate-300 text-white flex items-center justify-center text-[10px] font-bold">3</span>
                <span>Credentials</span>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- ================= MAIN SUBMISSION FORM ================= -->
        <form id="submissionForm" action="index.php" method="POST" enctype="multipart/form-data" class="bg-white border border-[#e8e5f2] rounded-3xl p-6 sm:p-8 shadow-sm">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="section" id="hidden_section" value="">
            <input type="hidden" name="project_option" id="hidden_project_option" value="">

            <!-- STEP 1: SECTION SELECTION -->
            <div id="step-1" class="step-container">
                <div class="text-center mb-6">
                    <span class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Step 1 of 3</span>
                    <h2 class="text-xl sm:text-2xl font-bold text-[#6b3ba7] mt-0.5">Choose What Section You Belong To</h2>
                    <p class="text-xs text-slate-500 mt-1">Click your assigned section to automatically proceed</p>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <?php
                    $sections = ['21004', '21005', '21006', '21007', '21008', '21009', '21010', '21011'];
                    foreach ($sections as$sec):
                    ?>
                        <button type="button" onclick="selectSection('<?= $sec ?>')" class="border border-[#e0daf0] hover:border-[#6b3ba7] hover:bg-[#faf8fd] rounded-2xl py-4 px-3 flex flex-col items-center justify-center transition group">
                            <span class="text-xs text-slate-500 group-hover:text-[#6b3ba7]">Section</span>
                            <span class="text-xl font-extrabold text-[#6b3ba7] tracking-tight"><?= $sec ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STEP 2: PROJECT OPTION SELECTION -->
            <div id="step-2" class="step-container hidden">
                <div class="flex items-center justify-between mb-4">
                    <button type="button" onclick="goToStep(1)" class="text-xs font-semibold text-slate-600 hover:text-[#6b3ba7] flex items-center gap-1">
                        ← Back to Sections
                    </button>
                    <span id="badge-section" class="bg-[#ede7f6] text-[#6b3ba7] text-xs font-bold px-3 py-1 rounded-full">
                        Section
                    </span>
                </div>

                <div class="text-center mb-6">
                    <span class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Step 2 of 3</span>
                    <h2 class="text-xl sm:text-2xl font-bold text-[#6b3ba7] mt-0.5">Select Your Project Option</h2>
                    <p class="text-xs text-slate-500 mt-1">Click one option below to proceed</p>
                </div>

                <div class="space-y-3.5">
                    <!-- Option 1: PORTFOLIO -->
                    <button type="button" onclick="selectOption('PORTFOLIO')" class="w-full border border-[#e0daf0] hover:border-[#6b3ba7] hover:bg-[#faf8fd] rounded-2xl p-4 flex items-center justify-between text-left transition group">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-[#ede7f6] text-[#6b3ba7] flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">1st Option</div>
                                <div class="text-base font-bold text-[#6b3ba7]">PORTFOLIO</div>
                                <div class="text-xs text-slate-500">(Individual Submission)</div>
                            </div>
                        </div>
                        <span class="text-slate-300 group-hover:text-[#6b3ba7] font-bold">›</span>
                    </button>

                    <!-- Option 2: STORYBOOK -->
                    <button type="button" onclick="selectOption('STORYBOOK')" class="w-full border border-[#e0daf0] hover:border-[#6b3ba7] hover:bg-[#faf8fd] rounded-2xl p-4 flex items-center justify-between text-left transition group">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-[#ede7f6] text-[#6b3ba7] flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">2nd Option</div>
                                <div class="text-base font-bold text-[#6b3ba7]">STORYBOOK</div>
                                <div class="text-xs text-slate-500">(Individual Submission)</div>
                            </div>
                        </div>
                        <span class="text-slate-300 group-hover:text-[#6b3ba7] font-bold">›</span>
                    </button>

                    <!-- Option 3: CLIENT-BASED -->
                    <button type="button" onclick="selectOption('CLIENT-BASED')" class="w-full border border-[#e0daf0] hover:border-[#6b3ba7] hover:bg-[#faf8fd] rounded-2xl p-4 flex items-center justify-between text-left transition group">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-xl bg-[#ede7f6] text-[#6b3ba7] flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">3rd Option</div>
                                <div class="text-base font-bold text-[#6b3ba7]">CLIENT-BASED</div>
                                <div class="text-xs text-slate-500">(By Pair + Client Photo Proof)</div>
                            </div>
                        </div>
                        <span class="text-slate-300 group-hover:text-[#6b3ba7] font-bold">›</span>
                    </button>
                </div>
            </div>

            <!-- STEP 3: CREDENTIAL DETAILS -->
            <div id="step-3" class="step-container hidden">
                <div class="flex items-center justify-between mb-4">
                    <button type="button" onclick="goToStep(2)" class="text-xs font-semibold text-slate-600 hover:text-[#6b3ba7] flex items-center gap-1">
                        ← Back to Options
                    </button>
                    <span id="badge-option" class="bg-[#ede7f6] text-[#6b3ba7] text-xs font-bold px-3 py-1 rounded-full uppercase">
                        OPTION
                    </span>
                </div>

                <div class="text-center mb-6">
                    <span class="text-[11px] font-bold tracking-wider text-slate-400 uppercase">Step 3 of 3</span>
                    <h2 class="text-xl sm:text-2xl font-bold text-[#6b3ba7] mt-0.5">Fill in Your Submission Details</h2>
                    <p class="text-xs text-slate-500 mt-1">Provide credentials, project link, and client image</p>
                </div>

                <!-- STUDENT 1 CARD -->
                <div class="border border-[#e0daf0] bg-[#fbfaff] rounded-2xl p-4 sm:p-5 mb-4">
                    <h3 id="student-header-label" class="text-xs font-extrabold text-[#6b3ba7] tracking-wider uppercase mb-3">
                        Student Information
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">First Name</label>
                            <input type="text" name="first_name" required placeholder="e.g. Juan" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Last Name</label>
                            <input type="text" name="last_name" required placeholder="e.g. Dela Cruz" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">
                            ✉ Active Gmail Address <span class="text-slate-400 font-normal">(To receive your grade)</span>
                        </label>
                        <input type="email" name="email" required placeholder="name@gmail.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                    </div>
                </div>

                <!-- PARTNER CARD (CLIENT-BASED ONLY) -->
                <div id="partner-section" class="border border-[#e0daf0] bg-[#fbfaff] rounded-2xl p-4 sm:p-5 mb-4 hidden">
                    <h3 class="text-xs font-extrabold text-[#6b3ba7] tracking-wider uppercase mb-3 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-[#6b3ba7]"></span> Partner Information (2nd Student)
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Partner First Name</label>
                            <input type="text" id="partner_first_name" name="partner_first_name" placeholder="e.g. Maria" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Partner Last Name</label>
                            <input type="text" id="partner_last_name" name="partner_last_name" placeholder="e.g. Santos" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">
                            ✉ Partner Active Gmail Address
                        </label>
                        <input type="email" id="partner_email" name="partner_email" placeholder="partner@gmail.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                    </div>
                </div>

                <!-- PHOTO PROOF UPLOAD (CLIENT-BASED ONLY) -->
                <div id="proof-container" class="border-2 border-dashed border-[#d2c9e6] rounded-2xl p-4 mb-4 text-center hidden bg-purple-50/20">
                    <div class="text-xs font-bold text-[#6b3ba7] mb-1">
                        📷 PHOTO PROOF WITH CLIENT
                    </div>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Please attach a clear photo of you and your partner together with your client (JPG, PNG, max 8MB).
                    </p>
                    <input type="file" name="client_proof" id="client_proof" accept="image/*" class="text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[#ede7f6] file:text-[#6b3ba7] hover:file:bg-[#e0daf0] cursor-pointer">
                </div>

                <!-- PROJECT LINK -->
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">
                        🔗 Project Link <span class="text-slate-400 font-normal">(Deploy Project, GitHub, Live URL)</span>
                    </label>
                    <input type="url" name="project_link" required placeholder="https://github.com/..." class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]">
                    <span class="block text-[11px] text-slate-400 mt-1">Ensure permissions are set to "Anyone with the link can view".</span>
                </div>

                <!-- SUBMIT BUTTON -->
                <button type="submit" class="w-full py-3.5 px-4 bg-[#6b3ba7] hover:bg-[#562f85] text-white font-bold rounded-2xl shadow-md transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    Submit Project
                </button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="text-center text-xs text-slate-400 mt-6">
            © 2026 ITE 1 Submission Portal.
        </footer>
    </div>

    <!-- JAVASCRIPT NAVIGATION -->
    <script>
        function goToStep(step) {
            document.querySelectorAll('.step-container').forEach(el => el.classList.add('hidden'));
            const target = document.getElementById(`step-${step}`);
            if (target) target.classList.remove('hidden');

            for (let i = 1; i <= 3; i++) {
                const nav = document.getElementById(`step-nav-${i}`);
                if (!nav) continue;
                const circle = nav.querySelector('span');

                if (i === step) {
                    nav.className = 'step-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[#ede7f6] text-[#6b3ba7]';
                    circle.className = 'w-5 h-5 rounded-full bg-[#6b3ba7] text-white flex items-center justify-center text-[10px] font-bold';
                } else {
                    nav.className = 'step-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full text-slate-400';
                    circle.className = 'w-5 h-5 rounded-full bg-slate-300 text-white flex items-center justify-center text-[10px] font-bold';
                }
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function selectSection(sec) {
            document.getElementById('hidden_section').value = sec;
            document.getElementById('badge-section').innerText = `Section ${sec}`;
            goToStep(2);
        }

        function selectOption(option) {
            document.getElementById('hidden_project_option').value = option;
            document.getElementById('badge-option').innerText = option;

            const partnerSec = document.getElementById('partner-section');
            const proofSec = document.getElementById('proof-container');
            const studentHeader = document.getElementById('student-header-label');
            const partnerName = document.getElementById('partner_first_name');
            const partnerLast = document.getElementById('partner_last_name');
            const partnerEmail = document.getElementById('partner_email');

            if (option === 'CLIENT-BASED') {
                partnerSec.classList.remove('hidden');
                proofSec.classList.remove('hidden');
                studentHeader.innerText = 'STUDENT 1 INFORMATION';
                partnerName.required = true;
                partnerLast.required = true;
                partnerEmail.required = true;
            } else {
                partnerSec.classList.add('hidden');
                proofSec.classList.add('hidden');
                studentHeader.innerText = 'STUDENT INFORMATION';
                partnerName.required = false;
                partnerLast.required = false;
                partnerEmail.required = false;
            }

            goToStep(3);
        }
    </script>
</body>
</html>