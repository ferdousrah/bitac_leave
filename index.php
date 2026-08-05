<?php
include('connection.php');

function getClientIP() {
    if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = 'Unknown IP';
    }
    if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
    return trim($ip);
}
$clientIP = getClientIP();

$assetURL = defined('BASE_URL') ? BASE_URL . '/vuexy-assets' : 'vuexy-assets';
$logoURL  = defined('BASE_URL') ? BASE_URL . '/uploads/bitac-logo-inner.png' : 'uploads/bitac-logo-inner.png';

// Optional photo for the left panel — drop any image at uploads/login-side.jpg
// (or .png / .webp) and it replaces the abstract gradient automatically.
$sideImage = '';
foreach (['login-side.jpg', 'login-side.jpeg', 'login-side.png', 'login-side.webp'] as $cand) {
    if (file_exists(__DIR__ . '/uploads/' . $cand)) {
        $sideImage = (defined('BASE_URL') ? BASE_URL : '.') . '/uploads/' . $cand;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="BITAC Leave Management System — login">
    <title>প্রবেশ · BITAC ছুটি ব্যবস্থাপনা</title>

    <link rel="shortcut icon" type="image/x-icon" href="app-assets/img/ico/favicon.ico">

    <!-- Icons -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/fonts/iconify-icons.css" />

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/sweetalert2/sweetalert2.css" />

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent:      #7367f0;
            --accent-dark: #5e50ee;
            --accent-soft: rgba(115, 103, 240, 0.12);
            --ink:         #23263a;
            --body:        #4a4d63;
            --muted:       #9095ab;
            --line:        #e4e6ef;
            --bg:          #eef0f5;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
            font-family: 'Hind Siliguri', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Full-viewport split ──────────────────────────── */
        .shell {
            flex: 1;
            min-height: 100vh;
            background: #fff;
            display: flex;
            animation: shellIn 0.4s ease both;
        }
        @keyframes shellIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) { .shell { animation: none; } }

        /* ── Left: visual panel ───────────────────────────── */
        .visual {
            flex: 0 0 34%;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            <?php if ($sideImage): ?>
            background: url('<?= htmlspecialchars($sideImage) ?>') center/cover no-repeat;
            <?php else: ?>
            background:
                radial-gradient(720px 480px at 90% 110%, rgba(255,255,255,0.16), transparent 60%),
                radial-gradient(560px 420px at -10% -10%, rgba(255,255,255,0.10), transparent 55%),
                linear-gradient(158deg, #6a5de8 0%, #5246c9 48%, #37308f 100%);
            <?php endif; ?>
        }
        <?php if (!$sideImage): ?>
        /* Soft abstract rings — visual interest without any text */
        .visual::before {
            content: "";
            position: absolute;
            width: 480px; height: 480px;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.14);
            bottom: -140px; right: -120px;
        }
        .visual::after {
            content: "";
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.12);
            bottom: -50px; right: -30px;
        }
        <?php endif; ?>

        .visual-logo {
            position: absolute;
            top: 26px; left: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.94);
            border-radius: 12px;
            padding: 9px 11px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.14);
        }
        .visual-logo img { height: 34px; width: auto; display: block; }

        /* ── Right: form panel ────────────────────────────── */
        .pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 40px 28px;
            position: relative;
        }
        .form-box {
            width: 100%;
            max-width: 340px;
        }

        .headline {
            text-align: center;
            margin-bottom: 34px;
        }
        .headline h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.3px;
        }
        .headline p {
            font-size: 0.88rem;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.6;
        }

        /* ── Nucleus-style inputs: label inside the border ── */
        .input-box {
            border: 1.5px solid var(--line);
            border-radius: 11px;
            padding: 9px 14px 8px;
            margin-bottom: 14px;
            position: relative;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            cursor: text;
        }
        .input-box:hover { border-color: #cdd1e0; }
        .input-box:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px var(--accent-soft);
        }
        .input-box label {
            display: block;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: 0.2px;
            margin-bottom: 1px;
            cursor: text;
        }
        .input-box input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--ink);
            padding: 0;
            line-height: 1.5;
        }
        .input-box input::placeholder { color: #c3c7d6; }
        .input-box.has-toggle input { padding-right: 34px; }

        .pwd-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px; height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: var(--muted);
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .pwd-toggle:hover { color: var(--ink); }
        .pwd-toggle .ti { font-size: 1.05rem; }

        .forgot {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
            margin: 2px 0 20px;
        }
        .forgot:hover { color: var(--accent-dark); text-decoration: underline; }

        /* ── Remember toggle switch ───────────────────────── */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .remember-row .lbl {
            font-size: 0.88rem;
            color: var(--body);
        }
        .switch {
            position: relative;
            width: 42px; height: 24px;
            flex-shrink: 0;
            cursor: pointer;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .track {
            position: absolute;
            inset: 0;
            background: #d8dbe8;
            border-radius: 24px;
            transition: background 0.18s ease;
        }
        .switch .track::after {
            content: "";
            position: absolute;
            top: 3px; left: 3px;
            width: 18px; height: 18px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            transition: transform 0.18s ease;
        }
        .switch input:checked + .track { background: var(--accent); }
        .switch input:checked + .track::after { transform: translateX(18px); }

        /* ── Button ───────────────────────────────────────── */
        .btn-login {
            width: 100%;
            padding: 13px 16px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 26px;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            letter-spacing: 0.2px;
            cursor: pointer;
            transition: background 0.16s ease, transform 0.06s ease, box-shadow 0.16s ease;
            box-shadow: 0 6px 18px rgba(115, 103, 240, 0.32);
        }
        .btn-login:hover { background: var(--accent-dark); box-shadow: 0 8px 22px rgba(115, 103, 240, 0.38); }
        .btn-login:active { transform: translateY(1px); }
        .btn-login:disabled { opacity: 0.65; cursor: wait; transform: none; }

        .below-note {
            margin-top: 26px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.65;
        }
        .below-note a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .below-note a:hover { text-decoration: underline; }

        /* ── Page footer — pinned at the bottom of the form pane ── */
        .page-foot {
            position: absolute;
            bottom: 18px;
            left: 0; right: 0;
            font-size: 0.74rem;
            color: var(--muted);
            text-align: center;
        }
        .page-foot a { color: var(--body); font-weight: 500; text-decoration: none; }
        .page-foot a:hover { color: var(--accent); }
        .page-foot .dot { margin: 0 6px; opacity: 0.5; }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 860px) {
            .visual { display: none; }
            .pane { padding: 44px 26px 70px; }
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            vertical-align: -2px;
            margin-right: 7px;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="shell">

        <!-- Left visual (image or gradient), logo only — no text -->
        <div class="visual">
            <span class="visual-logo">
                <img src="<?= htmlspecialchars($logoURL) ?>" alt="BITAC" onerror="this.parentNode.style.display='none'">
            </span>
        </div>

        <!-- Right form -->
        <div class="pane">
            <div class="form-box">

                <div class="headline">
                    <h1>স্বাগতম</h1>
                    <p>বিটাক ছুটি ব্যবস্থাপনা সিস্টেমে প্রবেশ করতে<br>আপনার তথ্য প্রদান করুন</p>
                </div>

                <form name="form" id="form" action="login_action.php" method="POST" autocomplete="on">

                    <div class="input-box" onclick="document.getElementById('username').focus()">
                        <label for="username">ইউজারনেম</label>
                        <input type="text" name="username" id="username"
                               placeholder="আপনার ইউজারনেম" autocomplete="username" required>
                    </div>

                    <div class="input-box has-toggle" onclick="document.getElementById('password').focus()">
                        <label for="password">পাসওয়ার্ড</label>
                        <input type="password" name="password" id="password"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="pwd-toggle" id="togglePwd" aria-label="Show password">
                            <i class="ti tabler-eye-off" id="pwdEyeIcon"></i>
                        </button>
                    </div>

                    <a href="#" class="forgot" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'পাসওয়ার্ড সহায়তা',text:'পাসওয়ার্ড পুনরুদ্ধারের জন্য আপনার সংশ্লিষ্ট কেন্দ্রের প্রশাসন বিভাগের সাথে যোগাযোগ করুন।',confirmButtonColor:'#7367f0',customClass:{confirmButton:'btn btn-primary'},buttonsStyling:false});">পাসওয়ার্ড ভুলে গেছেন?</a>

                    <div class="remember-row">
                        <span class="lbl">লগইন তথ্য মনে রাখুন</span>
                        <label class="switch">
                            <input type="checkbox" name="rememberme" id="rememberme">
                            <span class="track"></span>
                        </label>
                    </div>

                    <button type="submit" id="submit" class="btn-login">
                        <span id="submitLabel">প্রবেশ করুন</span>
                    </button>
                </form>

                <div class="below-note">
                    অ্যাকাউন্ট নেই? আপনার কেন্দ্রের <a href="#" onclick="event.preventDefault();">প্রশাসন বিভাগের</a> সাথে যোগাযোগ করুন
                </div>

            </div>

            <div class="page-foot">
                © <?= date('Y') ?> BITAC<span class="dot">·</span>Developed by <a href="https://technocratsbd.com" target="_blank" rel="noopener">Technocrats</a><span class="dot">·</span><?= htmlspecialchars($clientIP) ?>
            </div>
        </div>
    </div>

</div>

<!-- jQuery + SweetAlert2 -->
<script src="<?= $assetURL ?>/vendor/libs/jquery/jquery.js"></script>
<script src="<?= $assetURL ?>/vendor/libs/sweetalert2/sweetalert2.js"></script>

<script>
$(function() {
    var $form = $('#form');
    var $submit = $('#submit');
    var $label = $('#submitLabel');

    // Password show/hide
    $('#togglePwd').on('click', function(e) {
        e.stopPropagation();
        var $p = $('#password');
        var t = $p.attr('type') === 'password' ? 'text' : 'password';
        $p.attr('type', t);
        $('#pwdEyeIcon').toggleClass('tabler-eye-off tabler-eye');
    });

    function swalError(text) {
        Swal.fire({
            icon: 'error',
            title: 'ত্রুটি',
            text: text,
            confirmButtonColor: '#7367f0',
            customClass: { confirmButton: 'btn btn-primary' },
            buttonsStyling: false
        });
    }

    $form.on('submit', function(e) {
        e.preventDefault();

        var username = $('#username').val().trim();
        var password = $('#password').val().trim();
        if (!username) return swalError('অনুগ্রহপূর্বক ইউজারনেম প্রদান করুন');
        if (!password) return swalError('অনুগ্রহপূর্বক পাসওয়ার্ড প্রদান করুন');

        $submit.prop('disabled', true);
        $label.html('<span class="spinner"></span> প্রক্রিয়াকরণ...');

        $.ajax({
            url: 'login_action.php',
            type: 'POST',
            dataType: 'html',
            data: $form.serialize(),
            success: function(data) {
                var trimmed = String(data).trim();
                if (trimmed === '1') {
                    $label.text('সফল · রিডাইরেক্ট হচ্ছে...');
                    window.location = 'dashboard?menuslug=dashboard';
                    return;
                }
                // Structured lockout / attempt-remaining message from login_action.php
                // Format: LOCKED:<bangla message>
                if (trimmed.indexOf('LOCKED:') === 0) {
                    swalError(trimmed.substring('LOCKED:'.length));
                } else {
                    swalError('ভুল ইউজারনেম বা পাসওয়ার্ড');
                }
                $submit.prop('disabled', false);
                $label.text('প্রবেশ করুন');
            },
            error: function() {
                swalError('সার্ভার ত্রুটি — কিছুক্ষণ পর পুনরায় চেষ্টা করুন');
                $submit.prop('disabled', false);
                $label.text('প্রবেশ করুন');
            }
        });
    });
});
</script>

</body>
</html>
