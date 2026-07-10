<?php
session_start();
include('connection.php');

// Securely get user information using prepared statements
$stmt = $con->prepare("SELECT dataID, employee_id, user_group_id FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if user has a group assigned
if (!empty($getUserInfoQRW['user_group_id'])) {
    // Fetch accessible modules based on user's group permissions
    // This query gets all module-submodule combinations that the group has access to
    $query = "
        SELECT DISTINCT
            m.dataID AS module_id,
            m.module_name,
            m.page_link,
            m.icon,
            m.slug,
            m.display_order AS module_display_order,
            sm.dataID AS submodule_id,
            sm.submodule_name,
            sm.page_link AS submodule_link,
            sm.slug AS submodule_slug,
            sm.display_order AS submodule_display_order
        FROM group_access_permission gap
        INNER JOIN modules m ON gap.module_id = m.dataID
        LEFT JOIN submodules sm ON (
            (gap.submodule_id IS NULL AND sm.module_id = m.dataID AND sm.deleted = 0) OR
            (gap.submodule_id = sm.dataID)
        )
        WHERE gap.user_group_id = ? AND m.deleted = 0
        ORDER BY m.display_order ASC, sm.display_order ASC
    ";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $getUserInfoQRW['user_group_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Fallback to individual user permissions if no group assigned
    $query = "
        SELECT DISTINCT
            m.dataID AS module_id,
            m.module_name,
            m.page_link,
            m.icon,
            m.slug,
            m.display_order AS module_display_order,
            sm.dataID AS submodule_id,
            sm.submodule_name,
            sm.page_link AS submodule_link,
            sm.slug AS submodule_slug,
            sm.display_order AS submodule_display_order
        FROM access_permission ap
        INNER JOIN modules m ON ap.module_id = m.dataID
        LEFT JOIN submodules sm ON (
            (ap.submodule_id IS NULL AND sm.module_id = m.dataID AND sm.deleted = 0) OR
            (ap.submodule_id = sm.dataID)
        )
        WHERE ap.user_id = ? AND m.deleted = 0
        ORDER BY m.display_order ASC, sm.display_order ASC
    ";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $getUserInfoQRW['dataID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}

// Organize the results by modules and submodules
$menuData = [];
while ($row = $result->fetch_assoc()) {
    $module_id = $row['module_id'];
    if (!isset($menuData[$module_id])) {
        $menuData[$module_id] = [
            'module_name' => $row['module_name'],
            'page_link' => $row['page_link'],
            'icon' => $row['icon'],
            'slug' => $row['slug'],
            'submodules' => []
        ];
    }
    if ($row['submodule_id']) {
        $menuData[$module_id]['submodules'][] = [
            'submodule_name' => $row['submodule_name'],
            'page_link' => $row['submodule_link'],
            'slug' => $row['submodule_slug']
        ];
    }
}

$menuSlug = $_GET['menuslug'] ?? $_POST['menuslug'] ?? '';
?>

<ul class="custom-sidebar-menu">
    <?php foreach ($menuData as $module_id => $module): ?>
        <li class="<?= !empty($module['submodules']) ? 'has-submenu' : '' ?>">
            <a href="<?= $module['page_link'] == '#' ? 'javascript:void(0)' : $baseURL . $module['page_link'] . '?menuslug=' . $module['slug'] ?>" <?= !empty($module['submodules']) ? 'data-turbo="false"' : '' ?>>
                <i class="<?= $module['icon'] ?>"></i>
                <span class="menu-title"><?= $module['module_name'] ?></span>
                <?php if($module['slug'] == 'leave-management'): ?>
                    <span class="badge badge-pill badge-danger menu-badge" id="totalTask" style="display: none;">0</span>
                <?php endif; ?>
            </a>

            <?php if (!empty($module['submodules'])): ?>
                <ul class="custom-submenu">
                    <?php foreach ($module['submodules'] as $submodule): ?>
                        <li>
                            <a href="<?= $baseURL . $submodule['page_link'] ?>?menuslug=<?= $submodule['slug'] ?>">
                                <?= $submodule['submodule_name'] ?>
                                <span class="badge badge-pill badge-danger menu-badge" id="<?= $submodule['slug'] ?>" style="display: none;">0</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>

    <!-- Mobile menu items -->
    <li id="mobile-tab-button">
        <a href="./my_profile?menuslug=dashboard">
            <i class="ft-user font-medium-3"></i>
            <span class="menu-title">My Account</span>
        </a>
    </li>
    <li id="mobile-tab-button">
        <a href="javascript:void(0)" onclick="logout()">
            <i class="ft-power"></i>
            <span class="menu-title">Logout</span>
        </a>
    </li>
</ul>
