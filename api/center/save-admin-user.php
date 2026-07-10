<?php
session_start();
require_once(__DIR__ . '/../../connection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 0, 'message' => 'আপনি লগইন করেননি!']);
    exit;
}

$centerID        = intval($_POST['centerID']        ?? 0);
$adminUserDataID = intval($_POST['adminUserDataID'] ?? 0);
$fullName        = trim($_POST['full_name']         ?? '');
$username        = trim($_POST['username']          ?? '');
$password        = trim($_POST['password']          ?? '');

if (!$centerID || !$username || !$fullName) {
    echo json_encode(['status' => 0, 'message' => 'পূর্ণ নাম ও ইউজারনেম আবশ্যক!']);
    exit;
}

// Verify center exists
$stmt = $con->prepare("SELECT id FROM organization WHERE id = ? AND deleted = 0");
$stmt->bind_param("i", $centerID);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['status' => 0, 'message' => 'কেন্দ্র খুঁজে পাওয়া যায়নি!']);
    $stmt->close();
    exit;
}
$stmt->close();

if ($adminUserDataID === 0) {
    // ── INSERT new admin user ──────────────────────────────────────────────
    if (!$password) {
        echo json_encode(['status' => 0, 'message' => 'নতুন ব্যবহারকারীর জন্য পাসওয়ার্ড আবশ্যক!']);
        exit;
    }

    // Check username duplicate
    $stmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['status' => 0, 'message' => 'এই ইউজারনেম ইতিমধ্যে বিদ্যমান!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $con->prepare(
        "INSERT INTO user_list (user_id, password, full_name, user_type, dashboardType, organization_id, isCenterAdmin, user_group_id)
         VALUES (?, ?, ?, 2, 2, ?, 1, 2)"
    );
    $stmt->bind_param("sssi", $username, $hashedPassword, $fullName, $centerID);

    if ($stmt->execute()) {
        $newID = $con->insert_id;
        // Mirror to multi-role assignment table — Center Admin (group_id=2) marked default
        $a = $con->prepare("INSERT INTO user_group_assignment (user_id, group_id, is_default) VALUES (?, 2, 1)");
        $a->bind_param("i", $newID);
        $a->execute();
        $a->close();
        echo json_encode(['status' => 1, 'message' => 'অ্যাডমিন ব্যবহারকারী সফলভাবে সংরক্ষণ করা হয়েছে!', 'dataID' => $newID]);
    } else {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি: ' . $con->error]);
    }
    $stmt->close();

} else {
    // ── UPDATE existing admin user ─────────────────────────────────────────
    // Check username duplicate (excluding current user)
    $stmt = $con->prepare("SELECT dataID FROM user_list WHERE user_id = ? AND dataID != ?");
    $stmt->bind_param("si", $username, $adminUserDataID);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        echo json_encode(['status' => 0, 'message' => 'এই ইউজারনেম ইতিমধ্যে বিদ্যমান!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $con->prepare(
            "UPDATE user_list SET user_id = ?, password = ?, full_name = ?, organization_id = ?, isCenterAdmin = 1, user_group_id = 2 WHERE dataID = ?"
        );
        $stmt->bind_param("sssii", $username, $hashedPassword, $fullName, $centerID, $adminUserDataID);
    } else {
        $stmt = $con->prepare(
            "UPDATE user_list SET user_id = ?, full_name = ?, organization_id = ?, isCenterAdmin = 1, user_group_id = 2 WHERE dataID = ?"
        );
        $stmt->bind_param("ssii", $username, $fullName, $centerID, $adminUserDataID);
    }

    if ($stmt->execute()) {
        // Ensure Center Admin (group_id=2) is in the assignment table for this user;
        // don't disturb other roles they may have been multi-assigned.
        $upsert = $con->prepare(
            "INSERT INTO user_group_assignment (user_id, group_id, is_default) VALUES (?, 2, 1)
             ON DUPLICATE KEY UPDATE is_default = 1"
        );
        $upsert->bind_param("i", $adminUserDataID);
        $upsert->execute();
        $upsert->close();
        echo json_encode(['status' => 1, 'message' => 'অ্যাডমিন ব্যবহারকারী সফলভাবে আপডেট করা হয়েছে!']);
    } else {
        echo json_encode(['status' => 0, 'message' => 'ডাটাবেস ত্রুটি: ' . $con->error]);
    }
    $stmt->close();
}
