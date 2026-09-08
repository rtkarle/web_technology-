<?php
// ============================================================
// QuizCraft - Single File Application
// Database: quizcraft  |  Tables: quizzes, questions, quiz_results
// ============================================================
session_start();

// ── DB Connection ────────────────────────────────────────────
$conn = new mysqli('sql311.infinityfree.com', 'if0_42862273', 'RK03karle', 'if0_42862273_quizz_management_db');
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:40px;color:red;">
        <h2>Database Connection Failed</h2>
        <p>' . $conn->connect_error . '</p>
        <p>Make sure XAMPP is running and import <strong>database.sql</strong> in phpMyAdmin.</p>
    </div>');
}
$conn->set_charset('utf8mb4');

// ── Helper ───────────────────────────────────────────────────
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
function clean($conn, $v) { return $conn->real_escape_string(trim($v ?? '')); }
function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Route ────────────────────────────────────────────────────
$page   = $_GET['page']    ?? 'dashboard';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// POST HANDLERS
// ============================================================

// ── Create / Update Quiz ─────────────────────────────────────
if ($action === 'save_quiz') {
    $id      = isset($_POST['quiz_id']) && is_numeric($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
    $title   = clean($conn, $_POST['title']   ?? '');
    $subject = clean($conn, $_POST['subject'] ?? '');
    $errors  = [];
    if (!$title)   $errors[] = 'Quiz title is required.';
    if (!$subject) $errors[] = 'Subject is required.';
    if (empty($errors)) {
        if ($id) {
            $conn->query("UPDATE quizzes SET title='$title', subject='$subject' WHERE id=$id");
            flash('Quiz updated successfully.');
        } else {
            $conn->query("INSERT INTO quizzes (title, subject, total_marks, question, quiz_result) VALUES ('$title','$subject',0,0,0)");
            $id = $conn->insert_id;
            flash('Quiz created! Now add questions.');
        }
        header("Location: quiz.php?page=questions&quiz_id=$id");
        exit;
    }
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $_POST;
    header("Location: quiz.php?page=create_quiz" . ($id ? "&quiz_id=$id" : ''));
    exit;
}

// ── Delete Quiz ──────────────────────────────────────────────
if ($action === 'delete_quiz' && isset($_GET['quiz_id'])) {
    $id = (int)$_GET['quiz_id'];
    $conn->query("DELETE FROM quizzes WHERE id=$id");
    flash('Quiz deleted.');
    header('Location: quiz.php?page=dashboard');
    exit;
}

// ── Add / Update Question ────────────────────────────────────
if ($action === 'save_question') {
    $qid     = (int)($_POST['quiz_id']  ?? 0);
    $id      = (int)($_POST['q_id']     ?? 0);
    $text    = clean($conn, $_POST['question_text']  ?? '');
    $type    = in_array($_POST['question_type'] ?? '', ['MCQ','TF']) ? $_POST['question_type'] : '';
    $optA    = clean($conn, $_POST['option_a'] ?? '');
    $optB    = clean($conn, $_POST['option_b'] ?? '');
    $optC    = clean($conn, $_POST['option_c'] ?? '');
    $optD    = clean($conn, $_POST['option_d'] ?? '');
    $marks   = is_numeric($_POST['marks'] ?? '') && (int)$_POST['marks'] > 0 ? (int)$_POST['marks'] : 0;
    $correct = clean($conn, $_POST['correct_answer'] ?? '');
    $errors  = [];

    if (!$text)   $errors[] = 'Question text is required.';
    if (!$type)   $errors[] = 'Question type is required.';
    if (!$marks)  $errors[] = 'Marks must be a positive whole number.';
    if ($type === 'MCQ') {
        if (!$optA) $errors[] = 'Option A is required.';
        if (!$optB) $errors[] = 'Option B is required.';
        if (!$optC) $errors[] = 'Option C is required.';
        if (!$optD) $errors[] = 'Option D is required.';
        if (!in_array($correct, ['A','B','C','D'])) $errors[] = 'Select a valid correct answer (A/B/C/D).';
    }
    if ($type === 'TF' && !in_array($correct, ['True','False'])) {
        $errors[] = 'Select True or False as the correct answer.';
    }

    if (empty($errors)) {
        if ($id) {
            $conn->query("UPDATE questions SET question_text='$text', question_type='$type',
                option_a='$optA', option_b='$optB', option_c='$optC', option_d='$optD',
                correct_answer='$correct', marks=$marks
                WHERE id=$id AND quiz_id=$qid");
            flash('Question updated.');
        } else {
            $conn->query("INSERT INTO questions (quiz_id,question_text,question_type,option_a,option_b,option_c,option_d,correct_answer,marks)
                VALUES ($qid,'$text','$type','$optA','$optB','$optC','$optD','$correct',$marks)");
            // Update quiz counts
            $conn->query("UPDATE quizzes SET
                question    = (SELECT COUNT(*)    FROM questions WHERE quiz_id=$qid),
                total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=$qid)
                WHERE id=$qid");
            flash('Question added!');
        }
        header("Location: quiz.php?page=questions&quiz_id=$qid");
        exit;
    }
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = $_POST;
    header("Location: quiz.php?page=questions&quiz_id=$qid" . ($id ? "&edit_q=$id" : ''));
    exit;
}

// ── Delete Question ──────────────────────────────────────────
if ($action === 'delete_question' && isset($_GET['q_id'], $_GET['quiz_id'])) {
    $qid = (int)$_GET['quiz_id'];
    $id  = (int)$_GET['q_id'];
    $conn->query("DELETE FROM questions WHERE id=$id AND quiz_id=$qid");
    $conn->query("UPDATE quizzes SET
        question    = (SELECT COUNT(*)    FROM questions WHERE quiz_id=$qid),
        total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id=$qid)
        WHERE id=$qid");
    flash('Question deleted.');
    header("Location: quiz.php?page=questions&quiz_id=$qid");
    exit;
}

// ── Submit Quiz (student) ────────────────────────────────────
if ($action === 'submit_quiz') {
    $qid  = (int)($_POST['quiz_id'] ?? 0);
    $name = clean($conn, $_POST['student_name'] ?? 'Anonymous');
    $ans  = $_POST['ans'] ?? [];
    $qs   = [];
    $r    = $conn->query("SELECT * FROM questions WHERE quiz_id=$qid ORDER BY id");
    while ($row = $r->fetch_assoc()) $qs[] = $row;

    // Validate all answered
    $allAnswered = true;
    foreach ($qs as $q) {
        if (!isset($ans[$q['id']]) || $ans[$q['id']] === '') {
            $allAnswered = false; break;
        }
    }
    if (!$allAnswered) {
        $_SESSION['quiz_ans']    = $ans;
        $_SESSION['quiz_errors'] = 'Please answer all questions before submitting.';
        header("Location: quiz.php?page=take_quiz&quiz_id=$qid&student=" . urlencode($_POST['student_name']));
        exit;
    }

    $score = 0;
    foreach ($qs as $q) {
        $given = $ans[$q['id']] ?? '';
        if ($given === $q['correct_answer']) $score += $q['marks'];
    }

    $nameSafe = clean($conn, $name);
    $conn->query("INSERT INTO quiz_results (quiz_id, student_name, score) VALUES ($qid,'$nameSafe',$score)");
    $resultId = $conn->insert_id;
    // Update result count
    $conn->query("UPDATE quizzes SET quiz_result = (SELECT COUNT(*) FROM quiz_results WHERE quiz_id=$qid) WHERE id=$qid");

    // Store answers for result page
    $_SESSION['last_ans']    = $ans;
    $_SESSION['last_result'] = $resultId;

    header("Location: quiz.php?page=result&result_id=$resultId");
    exit;
}

// ============================================================
// FETCH DATA
// ============================================================
$flash_msg   = getFlash();
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data   = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

// Dashboard data
$allQuizzes = [];
if ($page === 'dashboard') {
    $r = $conn->query("SELECT * FROM quizzes ORDER BY created_at DESC");
    while ($row = $r->fetch_assoc()) $allQuizzes[] = $row;
}

// Single quiz
$quiz = null;
$quiz_id = isset($_GET['quiz_id']) && is_numeric($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id) {
    $r    = $conn->query("SELECT * FROM quizzes WHERE id=$quiz_id");
    $quiz = $r->fetch_assoc();
}

// Questions for a quiz
$questions = [];
if ($quiz_id && in_array($page, ['questions','take_quiz'])) {
    $r = $conn->query("SELECT * FROM questions WHERE quiz_id=$quiz_id ORDER BY id");
    while ($row = $r->fetch_assoc()) $questions[] = $row;
}

// Edit question prefill
$editQ = null;
$editQId = isset($_GET['edit_q']) && is_numeric($_GET['edit_q']) ? (int)$_GET['edit_q'] : 0;
if ($editQId && $quiz_id) {
    $r     = $conn->query("SELECT * FROM questions WHERE id=$editQId AND quiz_id=$quiz_id");
    $editQ = $r->fetch_assoc();
}

// Result data
$resultRow = null;
$resultQs  = [];
if ($page === 'result' && isset($_GET['result_id'])) {
    $rid  = (int)$_GET['result_id'];
    $r    = $conn->query("SELECT rr.*, q.title, q.subject, q.total_marks FROM quiz_results rr JOIN quizzes q ON q.id=rr.quiz_id WHERE rr.id=$rid");
    $resultRow = $r->fetch_assoc();
    if ($resultRow) {
        $r2 = $conn->query("SELECT * FROM questions WHERE quiz_id={$resultRow['quiz_id']} ORDER BY id");
        while ($row = $r2->fetch_assoc()) $resultQs[] = $row;
    }
}

// All results for a quiz
$allResults = [];
if ($page === 'results_list' && $quiz_id) {
    $r = $conn->query("SELECT * FROM quiz_results WHERE quiz_id=$quiz_id ORDER BY submitted_at DESC");
    while ($row = $r->fetch_assoc()) $allResults[] = $row;
}

// CSS ──────────────────────────────────────────────────────────
$css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:#f0f2f5;color:#333;}
a{text-decoration:none;color:inherit;}

/* NAVBAR */
.navbar{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;height:62px;position:sticky;top:0;z-index:100;
  box-shadow:0 3px 10px rgba(0,0,0,.2);}
.nav-brand{font-size:20px;font-weight:700;letter-spacing:.5px;}
.nav-brand small{font-size:11px;opacity:.8;font-weight:400;display:block;}
.nav-links{display:flex;gap:2px;}
.nav-links a{color:rgba(255,255,255,.85);padding:7px 14px;border-radius:7px;font-size:13px;font-weight:500;}
.nav-links a:hover,.nav-links a.active{background:rgba(255,255,255,.2);color:#fff;}
.btn-nav{background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.4);
  color:#fff;padding:7px 18px;border-radius:20px;font-size:13px;font-weight:600;}
.btn-nav:hover{background:rgba(255,255,255,.28);}

/* PAGE */
.page{max-width:1050px;margin:28px auto;padding:0 20px;}
.page-title{font-size:22px;font-weight:700;margin-bottom:4px;}
.page-sub{color:#888;font-size:13px;margin-bottom:22px;}

/* STATS */
.stats{display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap;}
.stat{background:#fff;border-radius:10px;padding:16px 20px;flex:1;min-width:120px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);display:flex;align-items:center;gap:12px;}
.stat-icon{width:42px;height:42px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:20px;}
.ic-p{background:#ede9fe;}.ic-b{background:#dbeafe;}.ic-g{background:#dcfce7;}.ic-o{background:#fff7ed;}
.stat-val{font-size:22px;font-weight:800;}.stat-lbl{font-size:11px;color:#888;}

/* CARD */
.card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.07);
  padding:24px 28px;margin-bottom:22px;}
.card-title{font-size:15px;font-weight:700;color:#444;padding-bottom:12px;
  border-bottom:2px solid #f0f2f5;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.card-icon{width:26px;height:26px;border-radius:6px;
  background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;}
.card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}

/* FORM */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-grid.cols1{grid-template-columns:1fr;}
.fg{display:flex;flex-direction:column;gap:5px;}
.fg.span2{grid-column:span 2;}
.fg label{font-size:13px;font-weight:600;color:#555;}
.req{color:red;}
.fg input,.fg select,.fg textarea{
  padding:9px 13px;border:1.5px solid #dde1e7;border-radius:8px;
  font-size:14px;font-family:inherit;background:#fafbfc;outline:none;transition:.2s;}
.fg input:focus,.fg select:focus,.fg textarea:focus{
  border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.12);background:#fff;}
.fg textarea{resize:vertical;min-height:72px;}

/* DIVIDER */
.divider{font-size:11px;font-weight:700;color:#888;text-transform:uppercase;
  letter-spacing:1px;margin:18px 0 12px;display:flex;align-items:center;gap:10px;}
.divider::after{content:'';flex:1;height:1px;background:#e8eaed;}

/* BUTTONS */
.btn{padding:9px 20px;border-radius:8px;border:none;cursor:pointer;font-size:13px;
  font-weight:600;font-family:inherit;transition:all .18s;display:inline-flex;
  align-items:center;gap:6px;text-decoration:none;}
.btn-sm{padding:6px 13px;font-size:12px;border-radius:7px;}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
  box-shadow:0 2px 8px rgba(102,126,234,.3);}
.btn-primary:hover{opacity:.9;transform:translateY(-1px);}
.btn-secondary{background:#f0f2f5;color:#555;border:1.5px solid #dde1e7;}
.btn-secondary:hover{background:#e4e7ed;}
.btn-success{background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;
  box-shadow:0 2px 8px rgba(17,153,142,.25);}
.btn-success:hover{opacity:.9;transform:translateY(-1px);}
.btn-warning{background:#fff7ed;color:#c2410c;border:1.5px solid #fed7aa;}
.btn-warning:hover{background:#ffeedd;}
.btn-danger{background:#fff0f0;color:#dc2626;border:1.5px solid #fca5a5;}
.btn-danger:hover{background:#fee2e2;}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:1px solid #f0f2f5;}
.btn-gap{display:flex;gap:6px;flex-wrap:wrap;}

/* MESSAGES */
.msg{padding:12px 18px;border-radius:8px;margin-bottom:18px;font-weight:600;font-size:14px;}
.msg-success{background:#f0fdf4;border:1.5px solid #86efac;color:#166534;}
.msg-error  {background:#fff0f0;border:1.5px solid #fca5a5;color:#991b1b;}

/* TABLE */
.tbl-wrap{overflow-x:auto;}
table.data{width:100%;border-collapse:collapse;font-size:14px;}
table.data thead tr{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;}
table.data thead th{padding:12px 14px;text-align:left;font-size:13px;font-weight:600;}
table.data thead th:first-child{border-radius:8px 0 0 0;}
table.data thead th:last-child{border-radius:0 8px 0 0;}
table.data tbody tr{border-bottom:1px solid #f0f2f5;transition:background .15s;}
table.data tbody tr:hover{background:#f7f8fc;}
table.data tbody td{padding:11px 14px;vertical-align:middle;}

/* BADGES */
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-mcq{background:#ede9fe;color:#7c3aed;}
.badge-tf {background:#dcfce7;color:#166534;}
.marks-chip{background:#fff7ed;color:#c2410c;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:700;}
.q-num{width:28px;height:28px;border-radius:50%;background:#ede9fe;color:#7c3aed;
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;}

/* QUIZ CARDS (take quiz list) */
.quiz-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.qcard{border:2px solid #dde1e7;border-radius:10px;padding:16px 18px;background:#fafbfc;
  display:flex;flex-direction:column;gap:8px;transition:.2s;}
.qcard:hover{border-color:#667eea;background:#f5f3ff;}
.qcard-title{font-size:15px;font-weight:700;}
.qcard-meta{font-size:12px;color:#888;line-height:1.8;}

/* QUESTION CARD (taking) */
.q-card{border:1.5px solid #e8eaed;border-radius:10px;padding:18px 20px;margin-bottom:16px;}
.q-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.q-lbl{font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;}
.q-text{font-size:15px;font-weight:600;margin-bottom:14px;}
.q-opts{display:grid;grid-template-columns:1fr 1fr;gap:8px;list-style:none;}
.q-opt{border:1.5px solid #dde1e7;border-radius:8px;}
.q-opt label{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;font-size:13px;}
.q-opt label:hover{background:#f5f3ff;}
.q-opt input[type=radio]{accent-color:#667eea;width:15px;height:15px;flex-shrink:0;}
.q-opt.correct-opt{border-color:#86efac;background:#f0fdf4;}
.q-opt.wrong-opt  {border-color:#fca5a5;background:#fff0f0;}
.opt-ltr{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);
  color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.fb{font-size:12px;margin-top:8px;font-weight:600;padding:6px 12px;border-radius:6px;}
.fb-correct{background:#f0fdf4;color:#166534;}.fb-wrong{background:#fff0f0;color:#dc2626;}

/* RESULT HEADER */
.result-hdr{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
  border-radius:10px 10px 0 0;margin:-24px -28px 20px;padding:24px 28px;}
.result-score{font-size:36px;font-weight:800;margin:6px 0;}
.result-grade{font-size:17px;opacity:.9;}
.result-meta{display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;}
.result-meta span{background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:12px;}
.pass-banner{padding:12px;border-radius:8px;text-align:center;font-size:15px;font-weight:700;margin-bottom:16px;}
.pass-banner.pass{background:#f0fdf4;color:#166534;border:2px solid #86efac;}
.pass-banner.fail{background:#fff0f0;color:#dc2626;border:2px solid #fca5a5;}

/* TIMER */
.timer-bar{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;
  border-radius:10px;padding:11px 20px;margin-bottom:18px;
  display:flex;justify-content:space-between;align-items:center;font-weight:700;}
.timer-val{font-size:22px;font-family:monospace;}
.timer-warn{background:linear-gradient(135deg,#f97316,#ef4444);}

/* ENTRY FORM */
.info-table{border-collapse:collapse;width:100%;font-size:13px;margin-bottom:14px;}
.info-table td{padding:8px 12px;border:1px solid #e8eaed;}
.info-table td:first-child{font-weight:600;background:#f7f8fc;width:35%;}

.empty{text-align:center;padding:40px;color:#aaa;}
.empty-icon{font-size:48px;margin-bottom:10px;}
CSS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QuizCraft</title>
  <style><?= $css ?></style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav class="navbar">
  <div class="nav-brand">&#9998; QuizCraft <small>Online Quiz Platform</small></div>
  <div class="nav-links">
    <a href="quiz.php?page=dashboard"  class="<?= $page==='dashboard'  ?'active':'' ?>">&#127968; Dashboard</a>
    <a href="quiz.php?page=create_quiz" class="<?= $page==='create_quiz'?'active':'' ?>">&#128221; Create Quiz</a>
    <a href="quiz.php?page=quiz_list"   class="<?= $page==='quiz_list'  ?'active':'' ?>">&#9654; Take Quiz</a>
  </div>
  <a href="quiz.php?page=create_quiz" class="btn-nav">+ New Quiz</a>
</nav>

<div class="page">

<?php
// Flash message
if ($flash_msg): ?>
  <div class="msg msg-<?= $flash_msg['type'] ?>">
    <?= $flash_msg['type'] === 'success' ? '&#10004;' : '&#10008;' ?>
    <?= e($flash_msg['msg']) ?>
  </div>
<?php endif;

// Form errors
if (!empty($form_errors)): ?>
  <div class="msg msg-error">
    &#10008; <?= implode('<br>', array_map('e', $form_errors)) ?>
  </div>
<?php endif;

// ============================================================
// PAGE: DASHBOARD
// ============================================================
if ($page === 'dashboard'):
    $totalQ = array_sum(array_column($allQuizzes, 'question'));
    $totalR = array_sum(array_column($allQuizzes, 'quiz_result'));
?>
  <div class="page-title">&#127968; Dashboard</div>
  <div class="page-sub">Manage all your quizzes — create, add questions, and view results.</div>

  <div class="stats">
    <div class="stat"><div class="stat-icon ic-p">&#127979;</div>
      <div><div class="stat-val"><?= count($allQuizzes) ?></div><div class="stat-lbl">Total Quizzes</div></div></div>
    <div class="stat"><div class="stat-icon ic-b">&#10067;</div>
      <div><div class="stat-val"><?= $totalQ ?></div><div class="stat-lbl">Total Questions</div></div></div>
    <div class="stat"><div class="stat-icon ic-g">&#128200;</div>
      <div><div class="stat-val"><?= $totalR ?></div><div class="stat-lbl">Total Attempts</div></div></div>
    <div class="stat"><div class="stat-icon ic-o">&#127941;</div>
      <div><div class="stat-val"><?= count($allQuizzes) ?></div><div class="stat-lbl">Published</div></div></div>
  </div>

  <div class="card">
    <div class="card-top">
      <strong style="font-size:16px;">&#128218; My Quizzes</strong>
      <a href="quiz.php?page=create_quiz" class="btn btn-primary">+ Create New Quiz</a>
    </div>
    <?php if (empty($allQuizzes)): ?>
      <div class="empty">
        <div class="empty-icon">&#128218;</div>
        <p>No quizzes yet. <a href="quiz.php?page=create_quiz">Create your first quiz</a>.</p>
      </div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>#</th><th>Quiz Title</th><th>Subject</th>
            <th>Questions</th><th>Total Marks</th><th>Attempts</th><th>Created</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($allQuizzes as $i => $q): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= e($q['title']) ?></strong></td>
            <td><?= e($q['subject']) ?></td>
            <td><?= $q['question'] ?></td>
            <td><?= $q['total_marks'] ?></td>
            <td><?= $q['quiz_result'] ?></td>
            <td style="font-size:12px;"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
            <td>
              <div class="btn-gap">
                <a href="quiz.php?page=create_quiz&quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-warning">&#9998; Edit</a>
                <a href="quiz.php?page=questions&quiz_id=<?= $q['id'] ?>"   class="btn btn-sm btn-primary">&#10067; Questions</a>
                <a href="quiz.php?page=quiz_list&quiz_id=<?= $q['id'] ?>"   class="btn btn-sm btn-success">&#9654; Take</a>
                <a href="quiz.php?page=results_list&quiz_id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary">&#128200; Results</a>
                <a href="quiz.php?action=delete_quiz&quiz_id=<?= $q['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete \'<?= addslashes($q['title']) ?>\'?')">&#128465;</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php
// ============================================================
// PAGE: CREATE / EDIT QUIZ
// ============================================================
elseif ($page === 'create_quiz'):
    $fd = $form_data ?: [];
?>
  <div class="page-title"><?= $quiz ? '&#9998; Edit Quiz' : '&#128221; Create New Quiz' ?></div>
  <div class="page-sub">Fill in quiz title and subject, then add questions.</div>

  <div class="card">
    <div class="card-title">
      <div class="card-icon">1</div>
      Quiz Details
    </div>
    <form method="post" action="quiz.php">
      <input type="hidden" name="action"   value="save_quiz">
      <input type="hidden" name="quiz_id"  value="<?= $quiz ? $quiz['id'] : '' ?>">
      <div class="form-grid">
        <div class="fg">
          <label>Quiz Title <span class="req">*</span></label>
          <input type="text" name="title" placeholder="e.g. General Knowledge Quiz"
                 value="<?= e($fd['title'] ?? $quiz['title'] ?? '') ?>" required>
        </div>
        <div class="fg">
          <label>Subject <span class="req">*</span></label>
          <input type="text" name="subject" placeholder="e.g. Science, Math, History"
                 value="<?= e($fd['subject'] ?? $quiz['subject'] ?? '') ?>" required>
        </div>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">
          <?= $quiz ? '&#10004; Update &amp; Continue' : 'Save &amp; Add Questions &#8594;' ?>
        </button>
        <a href="quiz.php?page=dashboard" class="btn btn-secondary">&#8592; Dashboard</a>
      </div>
    </form>
  </div>

  <?php if ($quiz): ?>
  <div class="card" style="background:#eff6ff;border:1.5px solid #bfdbfe;">
    <p style="font-size:14px;color:#1d4ed8;">
      &#128197; Quiz saved! Now go to
      <a href="quiz.php?page=questions&quiz_id=<?= $quiz['id'] ?>"><strong>Add Questions</strong></a>
      to complete this quiz.
    </p>
  </div>
  <?php endif; ?>

<?php
// ============================================================
// PAGE: QUESTIONS (Add / Edit / List)
// ============================================================
elseif ($page === 'questions' && $quiz):
    $fd   = $form_data ?: [];
    $type = $fd['question_type'] ?? $editQ['question_type'] ?? '';
?>
  <div class="page-title">&#10067; Questions — <?= e($quiz['title']) ?></div>
  <div class="page-sub">Subject: <?= e($quiz['subject']) ?> &nbsp;|&nbsp; <?= count($questions) ?> questions &nbsp;|&nbsp; Total Marks: <?= $quiz['total_marks'] ?></div>

  <!-- ADD / EDIT FORM -->
  <div class="card">
    <div class="card-title">
      <div class="card-icon"><?= $editQ ? '&#9998;' : '+' ?></div>
      <?= $editQ ? 'Edit Question #' . (array_search($editQ, $questions) + 1) : 'Add Question' ?>
    </div>

    <form method="post" action="quiz.php?page=questions&quiz_id=<?= $quiz['id'] ?><?= $editQ ? '&edit_q='.$editQId : '' ?>">
      <input type="hidden" name="action"  value="save_question">
      <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
      <?php if ($editQ): ?>
        <input type="hidden" name="q_id" value="<?= $editQ['id'] ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="fg span2">
          <label>Question Text <span class="req">*</span></label>
          <textarea name="question_text" rows="3" placeholder="Type your question here..."><?= e($fd['question_text'] ?? $editQ['question_text'] ?? '') ?></textarea>
        </div>
        <div class="fg">
          <label>Question Type <span class="req">*</span></label>
          <select name="question_type" id="qTypeSelect" onchange="toggleOpts()">
            <option value="">-- Select --</option>
            <option value="MCQ" <?= $type==='MCQ'?'selected':'' ?>>MCQ (Multiple Choice)</option>
            <option value="TF"  <?= $type==='TF' ?'selected':'' ?>>True / False</option>
          </select>
        </div>
        <div class="fg">
          <label>Marks <span class="req">*</span></label>
          <input type="number" name="marks" min="1" placeholder="e.g. 5"
                 value="<?= e($fd['marks'] ?? $editQ['marks'] ?? '') ?>">
        </div>
      </div>

      <!-- MCQ Options -->
      <div id="mcqBlock" style="<?= $type==='TF'?'display:none':'' ?>">
        <div class="divider">Answer Options</div>
        <div class="form-grid">
          <?php foreach (['a'=>'A','b'=>'B','c'=>'C','d'=>'D'] as $k=>$lbl): ?>
          <div class="fg">
            <label>Option <?= $lbl ?> <span class="req">*</span></label>
            <input type="text" name="option_<?= $k ?>" placeholder="Option <?= $lbl ?>"
                   value="<?= e($fd['option_'.$k] ?? $editQ['option_'.$k] ?? '') ?>">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="divider">Correct Answer</div>
      <div class="fg" style="max-width:280px;">
        <label>Correct Answer <span class="req">*</span></label>
        <select name="correct_answer" id="correctSel">
          <option value="">-- Select --</option>
          <?php
            $curCorr = $fd['correct_answer'] ?? $editQ['correct_answer'] ?? '';
            if ($type === 'TF') {
                foreach (['True','False'] as $v)
                    echo '<option value="'.$v.'"'.($curCorr===$v?' selected':'').'>'.$v.'</option>';
            } else {
                foreach (['A','B','C','D'] as $v)
                    echo '<option value="'.$v.'"'.($curCorr===$v?' selected':'').'>Option '.$v.'</option>';
            }
          ?>
        </select>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn btn-primary">
          <?= $editQ ? '&#10004; Update Question' : '&#43; Add Question' ?>
        </button>
        <?php if ($editQ): ?>
          <a href="quiz.php?page=questions&quiz_id=<?= $quiz['id'] ?>" class="btn btn-secondary">&#10005; Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- QUESTION LIST TABLE -->
  <div class="card">
    <div class="card-title">
      <div class="card-icon">&#9776;</div>
      Question List
      <span style="margin-left:auto;font-size:13px;font-weight:500;color:#888;">
        <?= count($questions) ?> questions &nbsp;|&nbsp; <?= $quiz['total_marks'] ?> total marks
      </span>
    </div>

    <?php if (empty($questions)): ?>
      <div class="empty">No questions yet. Add your first question above.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>#</th><th>Question</th><th>Type</th><th>Marks</th>
            <th>Options</th><th>Correct Answer</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($questions as $i => $q):
          $opts = $q['question_type']==='MCQ'
            ? 'A:'.e($q['option_a']).' | B:'.e($q['option_b']).'<br>C:'.e($q['option_c']).' | D:'.e($q['option_d'])
            : 'True / False';
          $corr = $q['question_type']==='MCQ'
            ? $q['correct_answer'].': '.e(['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']][$q['correct_answer']] ?? '')
            : $q['correct_answer'];
        ?>
          <tr>
            <td><div class="q-num"><?= $i+1 ?></div></td>
            <td><?= e($q['question_text']) ?></td>
            <td><?= $q['question_type']==='MCQ'?'<span class="badge badge-mcq">MCQ</span>':'<span class="badge badge-tf">T/F</span>' ?></td>
            <td><span class="marks-chip"><?= $q['marks'] ?> pt<?= $q['marks']>1?'s':'' ?></span></td>
            <td style="font-size:12px;"><?= $opts ?></td>
            <td><strong><?= $corr ?></strong></td>
            <td>
              <div class="btn-gap">
                <a href="quiz.php?page=questions&quiz_id=<?= $quiz['id'] ?>&edit_q=<?= $q['id'] ?>" class="btn btn-sm btn-warning">&#9998; Edit</a>
                <a href="quiz.php?action=delete_question&quiz_id=<?= $quiz['id'] ?>&q_id=<?= $q['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Delete this question?')">&#128465; Del</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <div class="btn-row">
      <a href="quiz.php?page=quiz_list&quiz_id=<?= $quiz['id'] ?>" class="btn btn-success">&#9654; Take This Quiz</a>
      <a href="quiz.php?page=dashboard" class="btn btn-secondary">&#127968; Dashboard</a>
    </div>
  </div>

<?php
// ============================================================
// PAGE: QUIZ LIST (Take Quiz)
// ============================================================
elseif ($page === 'quiz_list'):
    $listQuizzes = [];
    $r = $conn->query("SELECT * FROM quizzes ORDER BY created_at DESC");
    while ($row = $r->fetch_assoc()) $listQuizzes[] = $row;
?>
  <div class="page-title">&#9654; Take a Quiz</div>
  <div class="page-sub">Select any quiz to attempt it.</div>

  <?php if (empty($listQuizzes)): ?>
    <div class="card empty">
      <div class="empty-icon">&#128196;</div>
      <p>No quizzes available. <a href="quiz.php?page=create_quiz">Create one first</a>.</p>
    </div>
  <?php else: ?>
  <div class="quiz-grid">
    <?php foreach ($listQuizzes as $q): ?>
      <div class="qcard">
        <div class="qcard-title"><?= e($q['title']) ?></div>
        <div class="qcard-meta">
          Subject: <?= e($q['subject']) ?><br>
          Questions: <strong><?= $q['question'] ?></strong> &nbsp;|&nbsp;
          Total Marks: <strong><?= $q['total_marks'] ?></strong><br>
          Attempts: <?= $q['quiz_result'] ?>
        </div>
        <?php if ($q['question'] > 0): ?>
          <a href="quiz.php?page=entry&quiz_id=<?= $q['id'] ?>" class="btn btn-primary btn-sm" style="align-self:flex-start;">&#9654; Start Quiz</a>
        <?php else: ?>
          <span style="font-size:12px;color:#aaa;">No questions added yet.</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php
// ============================================================
// PAGE: ENTRY (Student Name)
// ============================================================
elseif ($page === 'entry' && $quiz):
?>
  <div class="page-title">&#9997; Start Quiz</div>
  <div class="page-sub">Enter your name and begin.</div>

  <div class="card">
    <div class="card-title"><div class="card-icon">&#128196;</div> Quiz Information</div>
    <table class="info-table">
      <tr><td>Quiz Title</td><td><strong><?= e($quiz['title']) ?></strong></td></tr>
      <tr><td>Subject</td>   <td><?= e($quiz['subject']) ?></td></tr>
      <tr><td>Questions</td> <td><?= $quiz['question'] ?></td></tr>
      <tr><td>Total Marks</td><td><?= $quiz['total_marks'] ?></td></tr>
    </table>
  </div>

  <div class="card">
    <div class="card-title"><div class="card-icon">&#9998;</div> Your Details</div>
    <form method="get" action="quiz.php">
      <input type="hidden" name="page"    value="take_quiz">
      <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
      <div class="fg" style="max-width:380px;">
        <label for="sname">Full Name <span class="req">*</span></label>
        <input type="text" id="sname" name="student" placeholder="Enter your full name" required>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">&#9654; Start Quiz</button>
        <a href="quiz.php?page=quiz_list" class="btn btn-secondary">&#8592; Back</a>
      </div>
    </form>
  </div>

<?php
// ============================================================
// PAGE: TAKE QUIZ
// ============================================================
elseif ($page === 'take_quiz' && $quiz && !empty($questions)):
    $studentName = e($_GET['student'] ?? 'Student');
    $savedAns    = $_SESSION['quiz_ans']    ?? [];
    $quizErr     = $_SESSION['quiz_errors'] ?? '';
    unset($_SESSION['quiz_ans'], $_SESSION['quiz_errors']);
?>
  <!-- Timer bar -->
  <div class="timer-bar" id="timerBar">
    <span>&#9200; Quiz: <?= e($quiz['title']) ?> &nbsp;|&nbsp; Student: <?= $studentName ?></span>
    <span class="timer-val" id="timerVal">&#9711;</span>
  </div>

  <?php if ($quizErr): ?>
    <div class="msg msg-error">&#10008; <?= e($quizErr) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title">
      <div class="card-icon">&#9997;</div>
      <?= e($quiz['title']) ?>
      <span style="margin-left:auto;font-size:12px;color:#888;font-weight:500;">
        <?= count($questions) ?> Questions &nbsp;|&nbsp; <?= $quiz['total_marks'] ?> Marks
      </span>
    </div>

    <form method="post" action="quiz.php" id="quizForm">
      <input type="hidden" name="action"       value="submit_quiz">
      <input type="hidden" name="quiz_id"      value="<?= $quiz['id'] ?>">
      <input type="hidden" name="student_name" value="<?= $studentName ?>">

      <?php foreach ($questions as $idx => $q):
        $opts = $q['question_type']==='MCQ'
          ? [['A',$q['option_a']],['B',$q['option_b']],['C',$q['option_c']],['D',$q['option_d']]]
          : [['True','True'],['False','False']];
      ?>
        <div class="q-card" id="qCard<?= $q['id'] ?>">
          <div class="q-header">
            <div class="q-lbl">
              <?= $q['question_type']==='MCQ'?'<span class="badge badge-mcq">MCQ</span>':'<span class="badge badge-tf">T/F</span>' ?>
              &nbsp; Q<?= $idx+1 ?> of <?= count($questions) ?>
            </div>
            <span class="marks-chip"><?= $q['marks'] ?> mark<?= $q['marks']>1?'s':'' ?></span>
          </div>
          <div class="q-text"><?= e($q['question_text']) ?></div>
          <ul class="q-opts">
            <?php foreach ($opts as [$k,$v]):
              $checked = (isset($savedAns[$q['id']]) && $savedAns[$q['id']] === $k) ? 'checked' : '';
            ?>
              <li class="q-opt" id="opt_<?= $q['id'] ?>_<?= $k ?>">
                <label>
                  <input type="radio" name="ans[<?= $q['id'] ?>]" value="<?= $k ?>"
                         <?= $checked ?> onchange="hlOpt(<?= $q['id'] ?>,'<?= $k ?>')">
                  <span class="opt-ltr"><?= $k[0] ?></span>
                  <?= e($v) ?>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <div id="qErr<?= $q['id'] ?>" style="color:red;font-size:12px;margin-top:6px;"></div>
        </div>
      <?php endforeach; ?>

      <div class="btn-row">
        <button type="button" class="btn btn-success" onclick="validateSubmit()">
          &#9989; Submit Quiz
        </button>
      </div>
      <div id="submitErr" style="color:red;font-size:13px;margin-top:8px;"></div>
    </form>
  </div>

  <script>
    function hlOpt(qId, val) {
      var opts = ['A','B','C','D','True','False'];
      opts.forEach(function(k){
        var el = document.getElementById('opt_'+qId+'_'+k);
        if(el){ el.style.borderColor='#dde1e7'; el.style.background=''; }
      });
      var sel = document.getElementById('opt_'+qId+'_'+val);
      if(sel){ sel.style.borderColor='#667eea'; sel.style.background='#f5f3ff'; }
      document.getElementById('qErr'+qId).textContent = '';
    }
    function validateSubmit(){
      var ids   = <?= json_encode(array_column($questions,'id')) ?>;
      var valid = true;
      ids.forEach(function(id){
        var rds = document.querySelectorAll('input[name="ans['+id+']"]');
        var ok  = false;
        rds.forEach(function(r){ if(r.checked) ok=true; });
        if(!ok){
          document.getElementById('qErr'+id).textContent='Please select an answer.';
          if(valid) document.getElementById('qCard'+id).scrollIntoView({behavior:'smooth'});
          valid=false;
        }
      });
      if(!valid){
        document.getElementById('submitErr').textContent='Please answer all questions before submitting.';
        return;
      }
      document.getElementById('quizForm').submit();
    }
    // Simple elapsed timer (no limit)
    var sec=0, timerInt=setInterval(function(){
      sec++; var m=Math.floor(sec/60), s=sec%60;
      document.getElementById('timerVal').textContent=(m<10?'0'+m:m)+':'+(s<10?'0'+s:s);
    },1000);
  </script>

<?php
// ============================================================
// PAGE: RESULT
// ============================================================
elseif ($page === 'result' && $resultRow):
    $ans      = $_SESSION['last_ans'] ?? [];
    unset($_SESSION['last_ans'], $_SESSION['last_result']);
    $pct      = $resultRow['total_marks'] > 0
              ? round($resultRow['score'] / $resultRow['total_marks'] * 100, 1)
              : 0;
    $grades   = [[90,'Excellent! &#127881;'],[75,'Very Good! &#127775;'],[60,'Good &#128076;'],[40,'Pass &#128077;']];
    $grade    = 'Needs Improvement &#128218;';
    foreach ($grades as [$min,$g]) { if ($pct >= $min) { $grade=$g; break; } }
?>
  <div class="card">
    <div class="result-hdr">
      <div style="font-size:12px;opacity:.8;">Quiz Result</div>
      <div class="result-score"><?= $resultRow['score'] ?> / <?= $resultRow['total_marks'] ?> (<?= $pct ?>%)</div>
      <div class="result-grade"><?= $grade ?></div>
      <div class="result-meta">
        <span>&#128203; <?= e($resultRow['title']) ?></span>
        <span>&#128100; <?= e($resultRow['student_name']) ?></span>
        <span>&#10067; <?= count($resultQs) ?> Questions</span>
        <span>&#128337; <?= date('d M Y H:i', strtotime($resultRow['submitted_at'])) ?></span>
      </div>
    </div>

    <h4 style="margin-bottom:14px;">&#128203; Answer Review</h4>

    <?php foreach ($resultQs as $idx => $q):
      $given     = $ans[$q['id']] ?? null;
      $isCorrect = ($given !== null && $given === $q['correct_answer']);
      $opts      = $q['question_type']==='MCQ'
        ? [['A',$q['option_a']],['B',$q['option_b']],['C',$q['option_c']],['D',$q['option_d']]]
        : [['True','True'],['False','False']];
    ?>
      <div class="q-card">
        <div class="q-header">
          <div class="q-lbl">
            <?= $q['question_type']==='MCQ'?'<span class="badge badge-mcq">MCQ</span>':'<span class="badge badge-tf">T/F</span>' ?>
            &nbsp; Q<?= $idx+1 ?>
          </div>
          <span class="marks-chip"><?= $q['marks'] ?> mark<?= $q['marks']>1?'s':'' ?></span>
        </div>
        <div class="q-text"><?= e($q['question_text']) ?></div>
        <ul class="q-opts">
          <?php foreach ($opts as [$k,$v]):
            $isAns  = ($k === $given);
            $isCorr = ($k === $q['correct_answer']);
            $cls    = $isCorr ? 'correct-opt' : ($isAns && !$isCorr ? 'wrong-opt' : '');
          ?>
            <li class="q-opt <?= $cls ?>">
              <label>
                <span class="opt-ltr"><?= $k[0] ?></span>
                <?= e($v) ?>
                <?php if($isCorr) echo ' &#10004;'; ?>
                <?php if($isAns && !$isCorr) echo ' &#10008;'; ?>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($isCorrect): ?>
          <div class="fb fb-correct">&#10004; Correct! +<?= $q['marks'] ?> marks</div>
        <?php else: ?>
          <div class="fb fb-wrong">&#10008; Wrong — Correct answer: <strong><?= e($q['correct_answer']) ?></strong></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="btn-row">
      <a href="quiz.php?page=entry&quiz_id=<?= $resultRow['quiz_id'] ?>" class="btn btn-primary">&#8635; Try Again</a>
      <a href="quiz.php?page=quiz_list"                                   class="btn btn-secondary">&#9654; Other Quizzes</a>
      <a href="quiz.php?page=dashboard"                                   class="btn btn-secondary">&#127968; Dashboard</a>
    </div>
  </div>

<?php
// ============================================================
// PAGE: RESULTS LIST
// ============================================================
elseif ($page === 'results_list' && $quiz):
?>
  <div class="page-title">&#128200; Results — <?= e($quiz['title']) ?></div>
  <div class="page-sub">All student attempts for this quiz.</div>

  <div class="card">
    <div class="card-top">
      <strong style="font-size:15px;"><?= e($quiz['subject']) ?> &nbsp;|&nbsp; <?= $quiz['question'] ?> Questions &nbsp;|&nbsp; <?= $quiz['total_marks'] ?> Total Marks</strong>
      <a href="quiz.php?page=dashboard" class="btn btn-secondary btn-sm">&#8592; Dashboard</a>
    </div>
    <?php if (empty($allResults)): ?>
      <div class="empty">No attempts yet for this quiz.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table class="data">
        <thead>
          <tr><th>#</th><th>Student Name</th><th>Score</th><th>Out of</th><th>Percentage</th><th>Submitted At</th></tr>
        </thead>
        <tbody>
        <?php foreach ($allResults as $i => $r):
          $pct = $quiz['total_marks'] > 0 ? round($r['score'] / $quiz['total_marks'] * 100, 1) : 0;
        ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= e($r['student_name']) ?></strong></td>
            <td><strong><?= $r['score'] ?></strong></td>
            <td><?= $quiz['total_marks'] ?></td>
            <td><?= $pct ?>%</td>
            <td style="font-size:12px;"><?= date('d M Y H:i', strtotime($r['submitted_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <!-- fallback / 404 -->
  <div class="card empty">
    <div class="empty-icon">&#128269;</div>
    <p>Page not found. <a href="quiz.php?page=dashboard">Go to Dashboard</a>.</p>
  </div>
<?php endif; ?>

</div><!-- /page -->
</body>
</html>
<?php $conn->close(); ?>
