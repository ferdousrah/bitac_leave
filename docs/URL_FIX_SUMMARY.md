# URL Generation Fix - Summary

## Problem Identified

After the menu migration, clicking menu links from within refactored view pages resulted in 404 errors.

### Example:
- **Dashboard** → **Employee Management** ✅ Works
- **Employee Management** → **Any other menu item** ❌ 404 Error

### Root Cause:
Two separate issues:

1. **BASE_URL Calculation Issue**
   - `BASE_URL` was calculated using `dirname($_SERVER['SCRIPT_NAME'])`
   - When inside `views/employees/manage.php`, this gave `/bitac_leave/views/employees`
   - Menu links became: `/bitac_leave/views/employees/manage_center` ❌

2. **Mixed Link Formats**
   - Refactored links: `views/employees/manage.php` (new style - full path)
   - Non-refactored links: `manage_center` (old style - no path, no extension)
   - Sidebar concatenated `BASE_URL + page_link` without handling differences

## Solutions Implemented

### 1. Fixed BASE_URL Calculation

**File:** `config/paths.php`

**Changes:**
```php
// OLD (broken):
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', $protocol . '://' . $host . rtrim($base, '/'));

// NEW (fixed):
$app_folder = basename(ROOT_PATH);  // 'bitac_leave'
$script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$pos = strpos($script_path, '/' . $app_folder . '/');
if ($pos !== false) {
    $base = substr($script_path, 0, $pos + strlen('/' . $app_folder));
} else {
    $base = '/' . $app_folder;
}
define('BASE_URL', $protocol . '://' . $host . $base);
```

**Result:**
- BASE_URL is now **always** `http://localhost/bitac_leave`
- Works correctly from any subdirectory depth

### 2. Added Smart URL Builder

**File:** `includes/sidebar_menu_vuexy.php`

**Added Helper Function:**
```php
function buildMenuUrl($baseURL, $pageLink) {
    // Handle empty or placeholder links
    if (empty($pageLink) || $pageLink === '#') {
        return 'javascript:void(0);';
    }

    // New style: starts with 'views/' - use as-is
    if (strpos($pageLink, 'views/') === 0) {
        return $baseURL . '/' . $pageLink;
    }

    // Old style: doesn't have .php extension - add it
    if (substr($pageLink, -4) !== '.php') {
        return $baseURL . '/' . $pageLink . '.php';
    }

    // Old style with .php extension already
    return $baseURL . '/' . $pageLink;
}
```

**Updated Link Generation:**
```php
// OLD:
<a href="<?= $baseURL . $submodule['page_link'] ?>?menuslug=...">

// NEW:
<a href="<?= buildMenuUrl($baseURL, $submodule['page_link']) ?>?menuslug=...">
```

## URL Generation Examples

### New Style (Refactored Modules)
| Input | Output |
|-------|--------|
| `views/employees/manage.php` | `http://localhost/bitac_leave/views/employees/manage.php` |
| `views/leave/approval.php` | `http://localhost/bitac_leave/views/leave/approval.php` |

### Old Style (Non-Refactored Modules)
| Input | Output |
|-------|--------|
| `manage_center` | `http://localhost/bitac_leave/manage_center.php` |
| `manage_designations` | `http://localhost/bitac_leave/manage_designations.php` |
| `dashboard.php` | `http://localhost/bitac_leave/dashboard.php` |

### Special Cases
| Input | Output |
|-------|--------|
| `#` | `javascript:void(0);` |
| `(empty)` | `javascript:void(0);` |

## Benefits

✅ **Backward Compatible** - Old-style links still work
✅ **Forward Compatible** - New-style links work correctly
✅ **Consistent BASE_URL** - Same URL regardless of current page
✅ **No Database Changes** - Works with existing menu data
✅ **Gradual Migration** - Can refactor modules one at a time

## Testing Checklist

After these fixes, verify:

- [ ] Navigate from Dashboard to any refactored module (Employee, Leave, etc.)
- [ ] From within a refactored module, click other menu items
- [ ] Click non-refactored module links (Center Management, Designations, etc.)
- [ ] Verify old modules still load correctly
- [ ] Test navigation between different modules
- [ ] Check mobile menu "My Account" link
- [ ] Verify all menu dropdowns expand/collapse correctly

## Files Modified

1. **config/paths.php** - Fixed BASE_URL calculation
2. **includes/sidebar_menu_vuexy.php** - Added buildMenuUrl() helper and updated all link generation

## Expected Behavior Now

✅ **All menu links should work** from any page
✅ **Refactored modules** load from `views/` directory
✅ **Non-refactored modules** load from root directory with `.php` extension
✅ **Navigation between modules** works seamlessly

---

**Fix Applied:** January 10, 2026
**Status:** ✅ Ready for Testing
