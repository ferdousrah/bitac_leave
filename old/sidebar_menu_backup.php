<?php
session_start();
include('connection.php');

// Securely get user information using prepared statements
$stmt = $con->prepare("SELECT dataID, employee_id FROM user_list WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$getUserInfoQRW = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch accessible modules for the logged-in user with their submodules in a single query
$query = "
    SELECT 
        m.dataID AS module_id,
        m.module_name,
        m.page_link,
        m.icon,
        m.slug,
        sm.dataID AS submodule_id,
        sm.submodule_name,
        sm.page_link AS submodule_link,
        sm.slug AS submodule_slug
    FROM access_permission ap
    INNER JOIN modules m ON ap.module_id = m.dataID
    LEFT JOIN submodules sm ON ap.submodule_id = sm.dataID
    WHERE ap.user_id = ? 
    ORDER BY m.display_order ASC, sm.display_order ASC
";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $getUserInfoQRW['dataID']);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

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

// Get the current menu slug
$menuSlug = $_GET['menuslug'] ?? $_POST['menuslug'] ?? '';
?>

<div class="nav-container">
    <ul id="main-menu-navigation" data-menu="menu-navigation" class="navigation navigation-main">
        <?php foreach ($menuData as $module_id => $module): ?>
            <li class="nav-item <?= !empty($module['submodules']) ? 'has-sub' : '' ?> <?= $menuSlug == $module['slug'] ? 'active' : '' ?>">
                <a href="<?= $module['page_link'] == '#' ? '#' : $baseURL . $module['page_link'] ?>?menuslug=<?= $module['slug'] ?>">
                    <i class="<?= $module['icon'] ?>"></i>
                    <span class="menu-title"><?= $module['module_name'] ?></span>
					<?php if($module['slug'] == 'leave-management'){ ?><span class="badge badge-pill badge-danger" id="totalTask" style="display: none;">0</span> <?php } ?>
                </a>
                <?php if (!empty($module['submodules'])): ?>
                    <ul class="menu-content">
                        <?php foreach ($module['submodules'] as $submodule): ?>
                            <li class="<?= $menuSlug == $submodule['slug'] ? 'active' : '' ?>">
                                <a href="<?= $baseURL . $submodule['page_link'] ?>?menuslug=<?= $submodule['slug'] ?>" class="menu-item">
                                    <?= $submodule['submodule_name'] ?>
									<span class="badge badge-pill badge-danger" id="<?= $submodule['slug'] ?>" style="display: none;">0</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        
        <!-- Mobile and tablet menu items -->
        <hr id="mobile-tab-button">
        <li class="nav-item" id="mobile-tab-button"><a href="./my_profile?menuslug=dashboard"><i style="color: #fff;" class="ft-user font-medium-3"></i><span class="menu-title">My Account</span></a></li>
        <li class="nav-item" id="mobile-tab-button"><a onClick="logout()"><i class="ft-power mr-2"></i><span class="menu-title">Logout</span></a></li>
    </ul>
</div>