<?php
/**
 * Migration: add theme settings columns to template_settings.
 * Safe to run multiple times (checks for existing columns).
 * Usage: open http://localhost/bitac_leave/migrations/add_theme_settings.php once.
 */
require_once(__DIR__ . '/../config/connection.php');

$columns = [
    'sidebar_bg_color'           => ["VARCHAR(20)",  "'#0f1419'"],
    'sidebar_menu_color'         => ["VARCHAR(20)",  "'#c1c2c5'"],
    'sidebar_icon_color'         => ["VARCHAR(20)",  "'#909196'"],
    'sidebar_hover_bg'           => ["VARCHAR(40)",  "'rgba(255,255,255,0.04)'"],
    'sidebar_hover_color'        => ["VARCHAR(20)",  "'#ffffff'"],
    'sidebar_active_bg'          => ["VARCHAR(20)",  "'#24302C'"],
    'sidebar_active_color'       => ["VARCHAR(20)",  "'#ffffff'"],
    'sidebar_menu_font_size'     => ["VARCHAR(20)",  "'1.075rem'"],
    'sidebar_submenu_color'      => ["VARCHAR(20)",  "'#a1a1aa'"],
    'sidebar_submenu_hover_bg'   => ["VARCHAR(40)",  "'rgba(255,255,255,0.04)'"],
    'sidebar_submenu_hover_color'=> ["VARCHAR(20)",  "'#ffffff'"],
    'sidebar_submenu_active_bg'  => ["VARCHAR(20)",  "'#24302C'"],
    'sidebar_submenu_active_color'=>["VARCHAR(20)",  "'#ffffff'"],
    'sidebar_submenu_font_size'  => ["VARCHAR(20)",  "'0.9rem'"],
    'sidebar_section_label_color'=> ["VARCHAR(20)",  "'#52525b'"],
    'sidebar_brand_color'        => ["VARCHAR(20)",  "'#ffffff'"],
    'content_bg_color'           => ["VARCHAR(20)",  "'#D9DDE0'"],
    'sidebar_menu_font_weight'   => ["VARCHAR(10)",  "'600'"],
    'sidebar_submenu_font_weight'=> ["VARCHAR(10)",  "'500'"],
];

$existing = [];
$res = mysqli_query($con, "SHOW COLUMNS FROM template_settings");
while ($r = mysqli_fetch_assoc($res)) {
    $existing[$r['Field']] = true;
}

$log = [];
foreach ($columns as $name => $def) {
    if (isset($existing[$name])) {
        $log[] = "SKIP: $name (already exists)";
        continue;
    }
    [$type, $default] = $def;
    $sql = "ALTER TABLE template_settings ADD COLUMN `$name` $type DEFAULT $default";
    if (mysqli_query($con, $sql)) {
        // Also set the default for the existing row #1
        $updSql = "UPDATE template_settings SET `$name` = $default WHERE dataID = 1";
        mysqli_query($con, $updSql);
        $log[] = "ADD:  $name  ($type DEFAULT $default)";
    } else {
        $log[] = "FAIL: $name - " . mysqli_error($con);
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Migration: add_theme_settings\n";
echo "============================\n";
foreach ($log as $line) echo $line . "\n";
echo "\nDone.\n";
