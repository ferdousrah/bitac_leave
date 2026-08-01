<?php
require_once(__DIR__ . '/../../config/connection.php');
header('Content-Type: application/json');

$ruleId            = intval($_POST['rule_id']            ?? 0);
$gradesRaw         = $_POST['grades']                    ?? [];
$leaveTypeId       = !empty($_POST['leave_type_id']) ? intval($_POST['leave_type_id']) : null;
$route             = $_POST['route']                     ?? '';
$description       = trim($_POST['description']          ?? '');
// Only meaningful for center_then_hq; checkbox absent = 0
$hqApprovalRequired = ($route === 'center_then_hq')
    ? (isset($_POST['hq_approval_required']) ? 1 : 0)
    : 1;

$validRoutes = ['center_only', 'center_then_hq', 'hq_only'];

$gradeIds = array_filter(array_map('intval', (array)$gradesRaw));
if (empty($gradeIds)) {
    echo json_encode(['status' => 0, 'message' => 'অন্তত একটি গ্রেড নির্বাচন করুন।']);
    exit;
}
if (!in_array($route, $validRoutes)) {
    echo json_encode(['status' => 0, 'message' => 'সঠিক রাউটিং নির্বাচন করুন।']);
    exit;
}

$gradesStr = implode(',', $gradeIds);

// Conflict check: warn if any of the selected grades already appear in another rule
// with the same leave_type scope (both NULL = all-types, or both same specific leave_type_id).
// A specific-type rule (leave_type_id set) + an all-types fallback (leave_type_id NULL) for the
// same grades is intentional and valid — do NOT flag that as a conflict.
$existingRulesQ = mysqli_query($con, "SELECT id, grades, route, leave_type_id FROM leave_signatory_rule"
    . ($ruleId > 0 ? " WHERE id != $ruleId" : ""));
$conflictGrades  = [];
$conflictRoutes  = [];
while ($er = mysqli_fetch_assoc($existingRulesQ)) {
    // Determine whether the existing rule has the same leave_type scope as the new/edited rule.
    $erLeaveType = ($er['leave_type_id'] !== null && $er['leave_type_id'] !== '')
                   ? intval($er['leave_type_id']) : null;
    $sameScope = ($leaveTypeId === null && $erLeaveType === null)
              || ($leaveTypeId !== null && $erLeaveType !== null && $erLeaveType === $leaveTypeId);
    if (!$sameScope) continue; // different scopes = valid, no conflict
    $erGrades = array_filter(array_map('intval', explode(',', $er['grades'])));
    $overlap  = array_intersect($gradeIds, $erGrades);
    if (!empty($overlap)) {
        $conflictGrades = array_merge($conflictGrades, array_values($overlap));
        $conflictRoutes[] = $er['route'] . ' (নিয়ম #' . $er['id'] . ')';
    }
}
if (!empty($conflictGrades)) {
    // Load grade titles for the conflicting grade IDs
    $inList = implode(',', array_unique($conflictGrades));
    $gtQ    = mysqli_query($con, "SELECT id, grade_title FROM grade WHERE id IN ($inList)");
    $gtMap  = [];
    while ($gt = mysqli_fetch_assoc($gtQ)) $gtMap[$gt['id']] = $gt['grade_title'];
    $gradeTitles = implode(', ', array_map(fn($g) => $gtMap[$g] ?? "গ্রেড $g", array_unique($conflictGrades)));
    $conflictMsg = "সতর্কতা: নিম্নলিখিত গ্রেড অন্য নিয়মে আছে: $gradeTitles — দ্বন্দ্বপূর্ণ নিয়ম: " . implode('; ', $conflictRoutes)
        . "। পূর্ববর্তী নিয়ম অগ্রাধিকার পাবে। নতুন নিয়ম তৈরি হয়েছে।";
} else {
    $conflictMsg = null;
}

if ($ruleId > 0) {
    if ($leaveTypeId !== null) {
        $stmt = mysqli_prepare($con, "UPDATE leave_signatory_rule SET grades=?, leave_type_id=?, route=?, description=?, hq_approval_required=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sissii', $gradesStr, $leaveTypeId, $route, $description, $hqApprovalRequired, $ruleId);
    } else {
        $stmt = mysqli_prepare($con, "UPDATE leave_signatory_rule SET grades=?, leave_type_id=NULL, route=?, description=?, hq_approval_required=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssii', $gradesStr, $route, $description, $hqApprovalRequired, $ruleId);
    }
    $msg = 'নিয়ম আপডেট করা হয়েছে।';
} else {
    if ($leaveTypeId !== null) {
        $stmt = mysqli_prepare($con, "INSERT INTO leave_signatory_rule (grades, leave_type_id, route, description, hq_approval_required) VALUES (?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sissi', $gradesStr, $leaveTypeId, $route, $description, $hqApprovalRequired);
    } else {
        $stmt = mysqli_prepare($con, "INSERT INTO leave_signatory_rule (grades, leave_type_id, route, description, hq_approval_required) VALUES (?,NULL,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sssi', $gradesStr, $route, $description, $hqApprovalRequired);
    }
    $msg = 'নতুন নিয়ম যোগ করা হয়েছে।';
}

if (mysqli_stmt_execute($stmt)) {
    if (function_exists('audit_log')) {
        audit_log('signatory_routing_rule_saved', [
            'target_type' => 'leave_signatory_rule',
            'target_id'   => isset($id) && $id > 0 ? (int)$id : (int)mysqli_insert_id($con),
            'note'        => "route=$route; grades=$gradesStr",
        ]);
    }
    $resp = ['status' => 1, 'message' => $msg];
    if ($conflictMsg) $resp['warning'] = $conflictMsg;
    echo json_encode($resp);
} else {
    echo json_encode(['status' => 0, 'message' => 'সংরক্ষণে সমস্যা: ' . mysqli_error($con)]);
}
