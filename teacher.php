<?php
// teacher.php - Teacher Evaluation Portal
date_default_timezone_set('Asia/Manila');
require 'db.php';
require 'mailer.php';

// Handle AJAX Requests (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. Action: Save Grade
    if ($_POST['action'] === 'save_grade') {
        $submissionId = $_POST['submission_id'] ?? '';
        $gradeScore   = trim($_POST['grade'] ?? '');
        $feedback     = trim($_POST['feedback'] ?? '');

        if (!empty($submissionId) && $gradeScore !== '') {
            $fetchStmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ? LIMIT 1");
            $fetchStmt->execute([$submissionId]);
            $sub = $fetchStmt->fetch();

            if ($sub) {
                $updateSql = "UPDATE submissions SET grade = ?, feedback = ?, status = 'Graded' WHERE id = ?";
                $stmt = $pdo->prepare($updateSql);
                $success = $stmt->execute([$gradeScore, $feedback, $submissionId]);

                // Send email to Student 1
                $s1Name  = $sub['student1_first_name'] . ' ' . $sub['student1_last_name'];
                $s1Email = $sub['student1_email'];
                sendGradeEmail($s1Email, $s1Name, $sub['project_option'], $sub['section'], $gradeScore, $feedback);

                // Send email to Partner (if Client-Based)
                if (!empty($sub['student2_email'])) {
                    $s2Name  = $sub['student2_first_name'] . ' ' . $sub['student2_last_name'];
                    $s2Email = $sub['student2_email'];
                    sendGradeEmail($s2Email, $s2Name, $sub['project_option'], $sub['section'], $gradeScore, $feedback);
                }

                // Recompute counters
                $totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
                $pendingGrading   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Pending' OR status IS NULL")->fetchColumn();
                $gradedProjects   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Graded'")->fetchColumn();

                echo json_encode([
                    'success'  => (bool)$success,
                    'id'       => (int)$submissionId,
                    'grade'    => htmlspecialchars($gradeScore, ENT_QUOTES, 'UTF-8'),
                    'feedback' => htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'),
                    'metrics'  => [
                        'total'   => (int)$totalSubmissions,
                        'pending' => (int)$pendingGrading,
                        'graded'  => (int)$gradedProjects
                    ]
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Submission record not found']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // 2. Action: Delete Submission
    if ($_POST['action'] === 'delete_submission') {
        $submissionId = $_POST['submission_id'] ?? '';

        if (!empty($submissionId)) {
            // Fetch proof image to remove the local file
            $fetchStmt = $pdo->prepare("SELECT proof_image FROM submissions WHERE id = ? LIMIT 1");
            $fetchStmt->execute([$submissionId]);
            $sub = $fetchStmt->fetch();

            if ($sub) {
                if (!empty($sub['proof_image'])) {
                    $filePath = __DIR__ . '/uploads/' . $sub['proof_image'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                // Delete the record from database
                $delStmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
                $success = $delStmt->execute([$submissionId]);

                // Recompute counters
                $totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
                $pendingGrading   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Pending' OR status IS NULL")->fetchColumn();
                $gradedProjects   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Graded'")->fetchColumn();

                echo json_encode([
                    'success' => (bool)$success,
                    'id'      => (int)$submissionId,
                    'metrics' => [
                        'total'   => (int)$totalSubmissions,
                        'pending' => (int)$pendingGrading,
                        'graded'  => (int)$gradedProjects
                    ]
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Submission not found']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid submission ID']);
        exit;
    }
}

// Fetch Initial Metrics Counters
$totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$pendingGrading   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Pending' OR status IS NULL")->fetchColumn();
$gradedProjects   = $pdo->query("SELECT COUNT(*) FROM submissions WHERE status = 'Graded'")->fetchColumn();

// Filter & Search Parameters
$searchQ  = trim($_GET['search'] ?? '');
$sectionQ = trim($_GET['section'] ?? '');
$trackQ   = trim($_GET['track'] ?? '');
$statusQ  = trim($_GET['status'] ?? '');

// Build Query
$query = "SELECT * FROM submissions WHERE 1=1";
$params = [];

if (!empty($searchQ)) {
    $query .= " AND (
        student1_first_name LIKE ? OR 
        student1_last_name LIKE ? OR 
        student1_email LIKE ? OR 
        student2_first_name LIKE ? OR 
        student2_last_name LIKE ? OR 
        student2_email LIKE ?
    )";
    $like = "%$searchQ%";
    $params = array_merge($params, [$like, $like, $like, $like, $like,$like]);
}

if (!empty($sectionQ)) {$query .= " AND section = ?";
    $params[] =$sectionQ;
}

if (!empty($trackQ)) {$query .= " AND project_option = ?";
    $params[] =$trackQ;
}

if (!empty($statusQ)) {
    if ($statusQ === 'Pending') {$query .= " AND (status = 'Pending' OR status IS NULL)";
    } else {
        $query .= " AND status = ?";
        $params[] =$statusQ;
    }
}

$query .= " ORDER BY submitted_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$submissions =$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Evaluation Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6b3ba7',
                        primaryHover: '#562f85',
                        brandBg: '#f6f6fc',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f6f6fc] min-h-screen text-slate-700 font-sans p-4 sm:p-8">

    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Top Header Bar -->
        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl border-2 border-purple-200 bg-white flex items-center justify-center shadow-xs">
                    <svg class="w-7 h-7 text-[#6b3ba7]" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12c0 2.85 1.2 5.42 3.12 7.24.12-.4.32-.98.6-1.54.5-.98 1.28-2.02 2.38-2.58.4-.2.85-.32 1.34-.32h5.12c.49 0 .94.12 1.34.32 1.1.56 1.88 1.6 2.38 2.58.28.56.48 1.14.6 1.54C20.8 17.42 22 14.85 22 12c0-5.52-4.48-10-10-10zm-3 8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm6 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm-3 6c-1.66 0-3-1.12-3-2.5h6c0 1.38-1.34 2.5-3 2.5z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-[#3a2063] tracking-tight">Teacher Evaluation Portal</h1>
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">Manage submissions, record evaluations, and dispatch grades</p>
                </div>
            </div>

            <a href="index.php" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:border-[#6b3ba7] text-xs sm:text-sm font-bold text-slate-700 rounded-xl transition shadow-xs self-start sm:self-auto">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                View Student Portal
            </a>
        </header>

        <!-- KPI Metrics Grid Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white border border-[#eeeaf7] rounded-3xl p-6 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider block mb-2">Total Submissions</span>
                    <span id="metric-total" class="text-4xl font-extrabold text-[#3a2063]"><?= number_format($totalSubmissions) ?></span>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[#f5f1fc] text-[#6b3ba7] flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white border border-[#eeeaf7] rounded-3xl p-6 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider block mb-2">Pending Grading</span>
                    <span id="metric-pending" class="text-4xl font-extrabold text-[#3a2063]"><?= number_format($pendingGrading) ?></span>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[#f5f1fc] text-[#6b3ba7] flex items-center justify-center">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white border border-[#eeeaf7] rounded-3xl p-6 shadow-xs flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-wider block mb-2">Graded Projects</span>
                    <span id="metric-graded" class="text-4xl font-extrabold text-[#3a2063]"><?= number_format($gradedProjects) ?></span>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[#592b9b] text-white flex items-center justify-center shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="bg-white border border-[#eeeaf7] rounded-3xl p-4 shadow-xs">
            <form method="GET" action="teacher.php" class="flex flex-col lg:flex-row items-center gap-3">
                
                <!-- Search Input -->
                <div class="relative flex-1 w-full">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Search student name or email..." class="w-full pl-10 pr-4 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-2xl text-xs sm:text-sm focus:outline-none focus:border-[#6b3ba7]">
                </div>

                <!-- Section Selector -->
                <select name="section" class="w-full lg:w-40 px-3.5 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-600 focus:outline-none focus:border-[#6b3ba7]">
                    <option value="">All Sections</option>
                    <?php foreach (['21004', '21005', '21006', '21007', '21008', '21009', '21010', '21011'] as $s): ?>
                        <option value="<?= $s ?>" <?= $sectionQ === $s ? 'selected' : '' ?>>Section <?= $s ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Project Track Filter -->
                <select name="track" class="w-full lg:w-48 px-3.5 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-600 focus:outline-none focus:border-[#6b3ba7]">
                    <option value="">All Project Options</option>
                    <option value="PORTFOLIO" <?= $trackQ === 'PORTFOLIO' ? 'selected' : '' ?>>Portfolio</option>
                    <option value="STORYBOOK" <?= $trackQ === 'STORYBOOK' ? 'selected' : '' ?>>Storybook</option>
                    <option value="CLIENT-BASED" <?= $trackQ === 'CLIENT-BASED' ? 'selected' : '' ?>>Client-Based</option>
                </select>

                <!-- Status Selector -->
                <select name="status" class="w-full lg:w-36 px-3.5 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-2xl text-xs sm:text-sm text-slate-600 focus:outline-none focus:border-[#6b3ba7]">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $statusQ === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Graded" <?= $statusQ === 'Graded' ? 'selected' : '' ?>>Graded</option>
                </select>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <button type="submit" class="flex-1 lg:flex-none px-6 py-2.5 bg-[#6b3ba7] hover:bg-[#562f85] text-white font-bold text-xs sm:text-sm rounded-2xl transition shadow-xs">
                        Filter
                    </button>
                    <a href="teacher.php" class="flex-1 lg:flex-none px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs sm:text-sm rounded-2xl transition text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Submissions Table Card -->
        <div class="bg-white border border-[#eeeaf7] rounded-3xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-white">
                            <th class="py-4 px-5">Section</th>
                            <th class="py-4 px-5">Project Track</th>
                            <th class="py-4 px-5">Student(s)</th>
                            <th class="py-4 px-5">Gmail Address</th>
                            <th class="py-4 px-4 text-center">Photo Proof</th>
                            <th class="py-4 px-5">Project Link</th>
                            <th class="py-4 px-5">Status / Grade</th>
                            <th class="py-4 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="submissionsTableBody" class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($submissions)): ?>
                            <tr id="empty-row">
                                <td colspan="8" class="text-center py-12 text-slate-400 text-xs sm:text-sm">
                                    No student submissions match your query.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $row):$isGraded = ($row['status'] === 'Graded');$fullName = htmlspecialchars(strtoupper($row['student1_first_name'] . ' ' .$row['student1_last_name']));
                                $emails   = htmlspecialchars(strtolower($row['student1_email']));

                                if (!empty($row['student2_first_name'])) {$fullName .= '<br><span class="text-slate-400 text-xs font-normal">+ ' . htmlspecialchars(strtoupper($row['student2_first_name'] . ' ' . $row['student2_last_name'])) . '</span>';$emails   .= '<br><span class="text-slate-400 text-xs font-normal">' . htmlspecialchars(strtolower($row['student2_email'])) . '</span>';
                                }

                                $track = strtoupper($row['project_option']);
                                if ($track === 'PORTFOLIO' || $track === 'STORYBOOK') {$track .= ' (INDIVIDUAL)';
                                } elseif ($track === 'CLIENT-BASED') {$track .= ' (BY PAIR)';
                                }

                                $studentDisplayName = $row['student1_first_name'] . ' ' .$row['student1_last_name'];
                            ?>
                            <tr id="row-submission-<?= (int)$row['id'] ?>" class="hover:bg-slate-50/60 transition">
                                <td class="py-4 px-5 font-extrabold text-[#3a2063]">
                                    <?= htmlspecialchars($row['section']) ?>
                                </td>
                                
                                <td class="py-4 px-5">
                                    <span class="inline-block px-3 py-1 bg-[#f4effc] text-[#6b3ba7] rounded-xl text-xs font-bold whitespace-nowrap">
                                        <?= htmlspecialchars($track) ?>
                                    </span>
                                </td>

                                <td class="py-4 px-5 font-bold text-slate-800 leading-tight">
                                    <?= $fullName ?>
                                </td>

                                <td class="py-4 px-5 text-slate-500 leading-tight">
                                    <?= $emails ?>
                                </td>

                                <!-- DEDICATED PHOTO PROOF COLUMN -->
                                <td class="py-4 px-4 text-center">
                                    <?php if (!empty($row['proof_image']) && file_exists(__DIR__ . '/uploads/' .$row['proof_image'])): ?>
                                        <button type="button" 
                                                onclick="previewProofPhoto('uploads/<?= htmlspecialchars($row['proof_image'], ENT_QUOTES, 'UTF-8') ?>', <?= htmlspecialchars(json_encode($studentDisplayName), ENT_QUOTES, 'UTF-8') ?>)"
                                                class="group relative inline-block focus:outline-none">
                                            <img src="uploads/<?= htmlspecialchars($row['proof_image']) ?>" 
                                                 alt="Client Proof" 
                                                 class="w-12 h-12 object-cover rounded-xl border border-purple-200 shadow-xs group-hover:opacity-85 transition ring-2 ring-transparent group-hover:ring-[#6b3ba7]">
                                            <span class="absolute inset-0 flex items-center justify-center bg-black/25 opacity-0 group-hover:opacity-100 rounded-xl transition text-white">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                                </svg>
                                            </span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-300 font-semibold">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-5">
                                    <a href="<?= htmlspecialchars($row['project_link']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f4effc] hover:bg-[#eae1fa] text-[#6b3ba7] text-xs font-bold rounded-xl transition whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                        View Link
                                    </a>
                                </td>

                                <td id="status-cell-<?= (int)$row['id'] ?>" class="py-4 px-5">
                                    <?php if ($isGraded): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-800 rounded-xl text-xs font-bold">
                                            ✓ Grade: <span class="grade-val"><?= htmlspecialchars($row['grade']) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-[#f4effc] text-[#6b3ba7] rounded-xl text-xs font-bold">
                                            ⏱ Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-4 px-5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <!-- Grade Action Button -->
                                        <button type="button" 
                                                id="btn-grade-<?= (int)$row['id'] ?>" 
                                                data-grade="<?= htmlspecialchars($row['grade'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                                data-feedback="<?= htmlspecialchars($row['feedback'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                                onclick="openGradeModal(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($studentDisplayName), ENT_QUOTES, 'UTF-8') ?>)"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#592b9b] hover:bg-[#47227d] text-white text-xs font-bold rounded-xl transition shadow-xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                            <span class="btn-text"><?= $isGraded ? 'Edit Grade' : 'Grade' ?></span>
                                        </button>

                                        <!-- Delete Submission Button -->
                                        <button type="button" 
                                                id="btn-delete-<?= (int)$row['id'] ?>"
                                                onclick="deleteSubmissionAsync(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($studentDisplayName), ENT_QUOTES, 'UTF-8') ?>)"
                                                title="Delete submission and allow resubmission"
                                                class="inline-flex items-center justify-center p-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition shadow-xs">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ================= PHOTO PREVIEW MODAL ================= -->
    <div id="photoModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-3xl p-5 max-w-xl w-full shadow-2xl relative border border-purple-100">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-sm font-extrabold text-[#3a2063]">Photo Proof with Client</h3>
                    <p id="photoModalStudent" class="text-xs text-slate-500 font-medium">Student Name</p>
                </div>
                <button type="button" onclick="closePhotoModal()" class="text-slate-400 hover:text-slate-600 text-2xl font-bold leading-none">✕</button>
            </div>
            
            <div class="w-full max-h-[70vh] overflow-hidden rounded-2xl flex items-center justify-center bg-slate-50">
                <img id="photoModalImg" src="" alt="Proof Preview" class="w-full h-auto max-h-[70vh] object-contain rounded-2xl">
            </div>

            <div class="mt-4 flex justify-end">
                <a id="photoModalDownload" href="" target="_blank" download class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#6b3ba7] hover:bg-[#562f85] text-white text-xs font-bold rounded-xl transition shadow-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Open Full Image
                </a>
            </div>
        </div>
    </div>

    <!-- ================= ASYNC GRADING MODAL ================= -->
    <div id="gradeModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white border border-[#eeeaf7] rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-extrabold text-[#3a2063]">Evaluate Student Submission</h3>
                <button type="button" onclick="closeGradeModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
            </div>

            <form id="ajaxGradeForm" onsubmit="submitGradeAsync(event)" class="mt-4 space-y-4">
                <input type="hidden" name="action" value="save_grade">
                <input type="hidden" name="submission_id" id="modal_submission_id" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Student</label>
                    <div id="modal_student_name" class="font-extrabold text-[#3a2063] text-sm bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        Student Name
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Grade (e.g. 95 or 1.25)</label>
                    <input type="text" name="grade" id="modal_grade" required placeholder="Enter numerical or descriptive grade" class="w-full px-3.5 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7] font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Evaluation Feedback / Comments</label>
                    <textarea name="feedback" id="modal_feedback" rows="3" placeholder="Optional comments..." class="w-full px-3.5 py-2.5 bg-[#fbfafd] border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-[#6b3ba7]"></textarea>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" onclick="closeGradeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" id="modal_submit_btn" class="flex-1 py-2.5 bg-[#6b3ba7] hover:bg-[#562f85] text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center justify-center gap-1.5">
                        <span>Save Evaluation</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Photo Proof Lightbox
        function previewProofPhoto(imgSrc, studentName) {
            document.getElementById('photoModalImg').src = imgSrc;
            document.getElementById('photoModalStudent').innerText = studentName;
            document.getElementById('photoModalDownload').href = imgSrc;
            document.getElementById('photoModal').classList.remove('hidden');
        }

        function closePhotoModal() {
            document.getElementById('photoModal').classList.add('hidden');
        }

        // Grade Modal
        function openGradeModal(id, studentName) {
            const btn = document.getElementById(`btn-grade-${id}`);
            const currentGrade = btn.getAttribute('data-grade') || '';
            const currentFeedback = btn.getAttribute('data-feedback') || '';

            document.getElementById('modal_submission_id').value = id;
            document.getElementById('modal_student_name').innerText = studentName;
            document.getElementById('modal_grade').value = currentGrade;
            document.getElementById('modal_feedback').value = currentFeedback;
            document.getElementById('gradeModal').classList.remove('hidden');
        }

        function closeGradeModal() {
            document.getElementById('gradeModal').classList.add('hidden');
        }

        // Live Asynchronous Submission (Preserves URL, Filters, and Scroll)
        async function submitGradeAsync(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('modal_submit_btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Saving...';
            submitBtn.disabled = true;

            const form = document.getElementById('ajaxGradeForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.success) {
                    const id = res.id;
                    const statusCell = document.getElementById(`status-cell-${id}`);
                    const actionBtn = document.getElementById(`btn-grade-${id}`);

                    // Update Status Badge
                    statusCell.innerHTML = `
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-100 text-green-800 rounded-xl text-xs font-bold">
                            ✓ Grade: <span class="grade-val">${res.grade}</span>
                        </span>
                    `;

                    // Update Button Data Attributes
                    actionBtn.setAttribute('data-grade', res.grade);
                    actionBtn.setAttribute('data-feedback', res.feedback);
                    actionBtn.querySelector('.btn-text').innerText = 'Edit Grade';

                    // Update KPI Counters
                    if (res.metrics) {
                        document.getElementById('metric-total').innerText = Number(res.metrics.total).toLocaleString();
                        document.getElementById('metric-pending').innerText = Number(res.metrics.pending).toLocaleString();
                        document.getElementById('metric-graded').innerText = Number(res.metrics.graded).toLocaleString();
                    }

                    closeGradeModal();
                } else {
                    alert('Error saving grade: ' + (res.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Connection error. Could not record grade.');
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }

        // Live Asynchronous Deletion
        async function deleteSubmissionAsync(id, studentName) {
            const confirmed = confirm(`Are you sure you want to delete the submission for "${studentName}"?\n\nThis will remove their submission and photo proof completely so they can submit again.`);
            if (!confirmed) return;

            const deleteBtn = document.getElementById(`btn-delete-${id}`);
            deleteBtn.disabled = true;
            deleteBtn.classList.add('opacity-50', 'cursor-not-allowed');

            const formData = new FormData();
            formData.append('action', 'delete_submission');
            formData.append('submission_id', id);

            try {
                const response = await fetch('teacher.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();

                if (res.success) {
                    const row = document.getElementById(`row-submission-${id}`);
                    if (row) {
                        row.classList.add('transition-all', 'duration-300', 'opacity-0', 'scale-95');
                        setTimeout(() => {
                            row.remove();
                            // Check if table is empty after deletion
                            const tbody = document.getElementById('submissionsTableBody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = `
                                    <tr id="empty-row">
                                        <td colspan="8" class="text-center py-12 text-slate-400 text-xs sm:text-sm">
                                            No student submissions match your query.
                                        </td>
                                    </tr>
                                `;
                            }
                        }, 300);
                    }

                    // Update KPI Counters
                    if (res.metrics) {
                        document.getElementById('metric-total').innerText = Number(res.metrics.total).toLocaleString();
                        document.getElementById('metric-pending').innerText = Number(res.metrics.pending).toLocaleString();
                        document.getElementById('metric-graded').innerText = Number(res.metrics.graded).toLocaleString();
                    }
                } else {
                    alert('Error deleting submission: ' + (res.error || 'Unknown error'));
                    deleteBtn.disabled = false;
                    deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            } catch (err) {
                alert('Connection error. Could not delete submission.');
                deleteBtn.disabled = false;
                deleteBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    </script>
</body>
</html>