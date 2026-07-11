<?php
session_start();
require_once(__DIR__ . '/../../config/connection.php');

header('Content-Type: application/json; charset=utf-8');

function reply($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok], $extra));
    exit;
}

// Must be logged in
if (empty($_SESSION['username'])) {
    reply(false, ['error' => 'Not logged in']);
}

// Gate: super admin only
$stmt = mysqli_prepare($con, "SELECT user_group_id FROM user_list WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row || (int)$row['user_group_id'] !== 1) {
    reply(false, ['error' => 'Forbidden — super admin only']);
}

// Whitelisted fields (never trust $_POST keys directly)
$allowed = [
    'sidebar_bg_color',
    'sidebar_menu_color',
    'sidebar_icon_color',
    'sidebar_hover_bg',
    'sidebar_hover_color',
    'sidebar_active_bg',
    'sidebar_active_color',
    'sidebar_menu_font_size',
    'sidebar_submenu_color',
    'sidebar_submenu_hover_bg',
    'sidebar_submenu_hover_color',
    'sidebar_submenu_active_bg',
    'sidebar_submenu_active_color',
    'sidebar_submenu_font_size',
    'sidebar_section_label_color',
    'sidebar_brand_color',
    'content_bg_color',
    'sidebar_menu_font_weight',
    'sidebar_submenu_font_weight',
];

// Basic sanity: each value must be short (< 60 chars), printable, no newlines / semicolons / angle brackets
function isSafeCssValue($v) {
    if (!is_string($v)) return false;
    if (strlen($v) === 0 || strlen($v) > 60) return false;
    // Reject dangerous chars that could break out of the CSS context
    if (preg_match('/[<>;\r\n{}"\']/', $v)) return false;
    return true;
}

$values = [];
foreach ($allowed as $field) {
    if (!isset($_POST[$field])) continue;
    $v = trim($_POST[$field]);
    if (!isSafeCssValue($v)) {
        reply(false, ['error' => "Invalid value for $field"]);
    }
    $values[$field] = $v;
}

// ── Logo upload (optional) ─────────────────────────────────────────────
// Fields handled here: header_logo (sidebar brand). If a file arrives under
// $_FILES['header_logo_file'], validate + move it into /uploads/ and store the
// generated filename in template_settings.header_logo.
if (isset($_FILES['header_logo_file']) && is_uploaded_file($_FILES['header_logo_file']['tmp_name'])) {
    $up = $_FILES['header_logo_file'];
    if ($up['error'] !== UPLOAD_ERR_OK) {
        reply(false, ['error' => 'Logo upload error code: ' . $up['error']]);
    }
    if ($up['size'] > 2 * 1024 * 1024) {
        reply(false, ['error' => 'লোগোর সর্বোচ্চ সাইজ ২ MB']);
    }
    $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        reply(false, ['error' => 'PNG / JPG / SVG / WEBP অনুমোদিত']);
    }

    // Verify MIME actually matches by magic bytes (defence-in-depth over just ext)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($up['tmp_name']);
    $allowedMime = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        reply(false, ['error' => 'ফাইলের MIME টাইপ সমর্থিত নয় (' . htmlspecialchars($mime) . ')']);
    }

    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $unique = 'header_logo_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $target = $uploadDir . $unique;
    if (!move_uploaded_file($up['tmp_name'], $target)) {
        reply(false, ['error' => 'ফাইল সংরক্ষণ ব্যর্থ']);
    }

    $values['header_logo'] = $unique;
}

if (empty($values)) {
    reply(false, ['error' => 'No fields to save']);
}

// Build dynamic UPDATE with prepared statement
$setParts = [];
$types = '';
$params = [];
foreach ($values as $field => $val) {
    $setParts[] = "`$field` = ?";
    $types .= 's';
    $params[] = $val;
}

$sql = "UPDATE template_settings SET " . implode(', ', $setParts) . " WHERE dataID = 1";
$stmt = mysqli_prepare($con, $sql);
if (!$stmt) reply(false, ['error' => 'Prepare failed: ' . mysqli_error($con)]);

mysqli_stmt_bind_param($stmt, $types, ...$params);
if (!mysqli_stmt_execute($stmt)) {
    reply(false, ['error' => 'Execute failed: ' . mysqli_stmt_error($stmt)]);
}
mysqli_stmt_close($stmt);

reply(true, ['message' => 'সেভ হয়েছে', 'updated' => count($values)]);
