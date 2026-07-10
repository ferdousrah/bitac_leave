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

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" type="text/css" href="<?= $assetURL ?>/vendor/css/core.css" />
    <link rel="stylesheet" type="text/css" href="<?= $assetURL ?>/css/demo.css" />

    <!-- Icons -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/fonts/iconify-icons.css" />

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= $assetURL ?>/vendor/libs/sweetalert2/sweetalert2.css" />

    <!-- Fonts: institutional pairing — Hind Siliguri (Bengali) + Cormorant Garamond (serif prestige accent) + Inter (form) -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Hind+Siliguri:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --navy-900: #0e1e34;
            --navy-800: #142a48;
            --navy-700: #1e3a5f;
            --navy-500: #2f507a;
            --gold-500: #b18b3e;
            --gold-400: #cba25a;
            --gold-100: #f4ecda;
            --surface: #f8f9fb;
            --text-dark: #101827;
            --text-body: #374151;
            --text-muted: #6b7280;
            --text-faint: #9ca3af;
            --border: #e2e6ec;
            --border-strong: #cbd2dc;
            --success: #2f8259;
        }

        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0; height: 100%;
            font-family: 'Hind Siliguri', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            color: var(--text-dark);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow: hidden;
        }

        .login-wrap {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: stretch;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* ═══════════════════════════════════════════════════════════
           LEFT PANEL — institutional hero
           ═══════════════════════════════════════════════════════════ */
        .login-hero {
            flex: 1 1 58%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px 64px 36px;
            background: linear-gradient(155deg, var(--navy-900) 0%, var(--navy-700) 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        /* Etched pattern */
        .login-hero::before {
            content: "";
            position: absolute; inset: 0;
            background-image:
                radial-gradient(circle at 1px 1px, rgba(255,255,255,0.045) 1px, transparent 0);
            background-size: 26px 26px;
            pointer-events: none;
        }
        /* Corner accent — angled gold sliver top-right */
        .login-hero::after {
            content: "";
            position: absolute;
            top: 0; right: 0;
            width: 240px; height: 240px;
            background:
                linear-gradient(135deg, transparent 62%, var(--gold-500) 62%, var(--gold-500) 62.4%, transparent 62.4%);
            opacity: 0.55;
            pointer-events: none;
        }

        /* ─── Top strip: national identity ─── */
        .hero-header {
            position: relative; z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
        }
        .hero-national {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .hero-national-mark {
            width: 44px; height: 44px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 4px;
        }
        .hero-national-mark img { height: 30px; width: auto; }
        .hero-national-text {
            display: flex; flex-direction: column;
            line-height: 1.2;
        }
        .hero-national-text .kicker {
            font-size: 0.68rem;
            font-weight: 500;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--gold-400);
        }
        .hero-national-text .name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: rgba(255,255,255,0.94);
            margin-top: 2px;
        }
        .hero-govt-line {
            font-size: 0.72rem;
            font-weight: 400;
            color: rgba(255,255,255,0.55);
            letter-spacing: 0.3px;
        }

        /* ─── Middle: institutional title block ─── */
        .hero-body {
            position: relative; z-index: 2;
            max-width: 560px;
            padding: 24px 0;
        }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold-400);
            margin-bottom: 28px;
        }
        .hero-eyebrow::before {
            content: "";
            width: 28px; height: 1px;
            background: var(--gold-500);
        }
        .hero-title {
            font-size: 2.3rem;
            font-weight: 600;
            line-height: 1.28;
            margin: 0 0 22px;
            letter-spacing: -0.4px;
            color: #ffffff;
        }
        .hero-title-em {
            color: var(--gold-400);
            font-weight: 700;
        }
        .hero-title-en {
            font-family: 'Cormorant Garamond', serif;
            display: block;
            font-size: 1.05rem;
            font-weight: 500;
            color: rgba(255,255,255,0.6);
            letter-spacing: 0.5px;
            margin-top: 8px;
            font-style: italic;
        }
        .hero-subtitle {
            font-size: 0.96rem;
            line-height: 1.75;
            color: rgba(255,255,255,0.72);
            margin: 0 0 34px;
            font-weight: 400;
            max-width: 500px;
        }
        .hero-features {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .hero-feature {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.92rem;
            font-weight: 400;
            padding: 14px 0;
            color: rgba(255,255,255,0.85);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .hero-feature-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gold-400);
            width: 28px;
            letter-spacing: 1px;
        }
        .hero-feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px; height: 22px;
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
            flex-shrink: 0;
        }

        /* ─── Bottom: meta strip ─── */
        .hero-footer {
            position: relative; z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            gap: 16px;
            flex-wrap: wrap;
        }
        .hero-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            margin-left: 18px;
            transition: color 0.15s ease;
        }
        .hero-footer a:hover { color: var(--gold-400); }

        /* ═══════════════════════════════════════════════════════════
           RIGHT PANEL — form
           ═══════════════════════════════════════════════════════════ */
        .login-form-side {
            flex: 1 1 42%;
            display: flex;
            flex-direction: column;
            padding: 0;
            background: #ffffff;
            position: relative;
        }
        /* Top security strip */
        .form-topstrip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 32px;
            font-size: 0.72rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .form-topstrip .secure {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--success);
            font-weight: 500;
        }
        .form-topstrip .secure .ti { font-size: 0.9rem; }
        .form-topstrip .meta { color: var(--text-faint); }

        .login-form-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 32px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            position: relative;
        }
        .login-brand {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 44px;
        }
        .login-brand-mark {
            width: 58px; height: 58px;
            border-radius: 6px;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid var(--border);
        }
        .login-brand-mark img { height: 44px; width: auto; }
        .login-brand-body { display: flex; flex-direction: column; margin-top: 4px; }
        .login-brand-body .abbr {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--navy-700);
            line-height: 1;
            letter-spacing: 1px;
        }
        .login-brand-body .full {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 4px;
            letter-spacing: 0.2px;
        }
        .login-brand-body .divider {
            width: 32px; height: 2px;
            background: var(--gold-500);
            margin: 10px 0 4px;
        }

        .login-heading {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0 0 6px;
            letter-spacing: -0.2px;
        }
        .login-sub {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin: 0 0 28px;
            line-height: 1.55;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-body);
            margin-bottom: 6px;
            display: block;
            letter-spacing: 0.1px;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 18px;
        }
        .input-wrap > .ti {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-faint);
            font-size: 1rem;
            pointer-events: none;
            z-index: 2;
            transition: color 0.15s ease;
        }
        .input-wrap:focus-within > .ti { color: var(--navy-700); }

        .login-card .input-wrap .form-control {
            width: 100% !important;
            padding: 11px 44px 11px 42px !important;
            height: auto !important;
            border: 1px solid var(--border) !important;
            border-radius: 5px !important;
            font-size: 0.92rem !important;
            color: var(--text-dark) !important;
            background: #fff !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease !important;
            outline: none !important;
            font-family: inherit !important;
            line-height: 1.5 !important;
            box-shadow: none;
        }
        .login-card .input-wrap .form-control:hover {
            border-color: var(--border-strong) !important;
        }
        .login-card .input-wrap .form-control:focus {
            border-color: var(--navy-700) !important;
            box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.08) !important;
            background: #fdfdff !important;
        }
        .login-card .input-wrap .form-control::placeholder { color: var(--text-faint); }
        .login-card .input-wrap:not(:has(.pwd-toggle)) .form-control {
            padding-right: 14px !important;
        }

        .pwd-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-faint);
            cursor: pointer;
            padding: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: color 0.15s ease, background 0.15s ease;
        }
        .pwd-toggle:hover { color: var(--navy-700); background: var(--surface); }
        .pwd-toggle .ti { font-size: 1rem; }

        .form-row-extra {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 2px 0 24px;
        }
        .form-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
            color: var(--text-body);
        }
        .form-check input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--navy-700);
            cursor: pointer;
        }
        .form-help-link {
            font-size: 0.82rem;
            color: var(--navy-700);
            text-decoration: none;
            font-weight: 500;
        }
        .form-help-link:hover { color: var(--gold-500); text-decoration: underline; }

        .btn-login {
            width: 100%;
            padding: 12px 16px;
            background: var(--navy-700);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.92rem;
            font-weight: 500;
            letter-spacing: 0.4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.18s ease, transform 0.05s ease;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        .btn-login::after {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--gold-500);
            transition: width 0.2s ease;
        }
        .btn-login:hover { background: var(--navy-800); }
        .btn-login:hover::after { width: 5px; }
        .btn-login:active { transform: translateY(1px); }
        .btn-login:disabled { opacity: 0.7; cursor: wait; }
        .btn-login .ti { font-size: 1rem; }

        .login-note {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 24px;
            padding: 12px 14px;
            background: var(--surface);
            border-left: 3px solid var(--gold-500);
            border-radius: 3px;
            line-height: 1.55;
        }
        .login-note strong { color: var(--text-dark); font-weight: 600; }

        /* Bottom form meta */
        .form-bottom {
            padding: 14px 32px;
            border-top: 1px solid var(--border);
            font-size: 0.7rem;
            color: var(--text-faint);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfd;
        }

        /* ── Responsive ───────────────────────────────────────── */
        /* Mobile / small tablet: hide the hero panel entirely — form only */
        @media (max-width: 991px) {
            html, body { overflow: auto; }
            .login-wrap {
                position: static;
                min-height: 100vh;
            }
            .login-hero { display: none; }
            .login-form-side {
                flex: 1 1 100%;
                min-height: 100vh;
            }
            .form-topstrip { padding: 12px 20px; font-size: 0.7rem; }
            .login-form-container { padding: 32px 20px; }
            .form-bottom { padding: 12px 20px; font-size: 0.66rem; }
        }
        @media (max-width: 575px) {
            .login-card { max-width: 100%; }
            .login-heading { font-size: 1.25rem; }
            .login-brand-mark { width: 50px; height: 50px; }
            .login-brand-mark img { height: 36px; }
            .login-brand-body .abbr { font-size: 1.25rem; }
            .form-topstrip { padding: 10px 16px; font-size: 0.66rem; }
            .login-form-container { padding: 24px 16px; }
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- ═══════════════════ LEFT: institutional hero ═══════════════════ -->
    <div class="login-hero">

        <!-- Top identity strip -->
        <div class="hero-header">
            <div class="hero-national">
                <span class="hero-national-mark">
                    <img src="<?= htmlspecialchars($logoURL) ?>" alt="BITAC" onerror="this.style.display='none'">
                </span>
                <div class="hero-national-text">
                    <span class="kicker">প্রধান কার্যালয়</span>
                    <span class="name">Bangladesh Industrial Technical Assistance Center</span>
                </div>
            </div>
            <div class="hero-govt-line d-none d-lg-block">
                গণপ্রজাতন্ত্রী বাংলাদেশ সরকার
            </div>
        </div>

        <!-- Middle: institutional title -->
        <div class="hero-body">
            <span class="hero-eyebrow">ছুটি ব্যবস্থাপনা পোর্টাল</span>
            <h1 class="hero-title">
                বাংলাদেশ শিল্প কারিগরি সহায়তা কেন্দ্র                
                <span class="hero-title-en">Leave &amp; Increment Management System</span>
            </h1>
            <p class="hero-subtitle">
                কর্মকর্তা ও কর্মচারীগণের ছুটির আবেদন, অনুমোদন প্রক্রিয়া এবং বার্ষিক বেতন বৃদ্ধি ব্যবস্থাপনার আধুনিক অনলাইন প্ল্যাটফর্ম।
            </p>
            <div class="hero-features">
                <div class="hero-feature">
                    <span class="hero-feature-num">I.</span>
                    <span class="hero-feature-icon"><i class="ti tabler-calendar-event"></i></span>
                    ছুটির আবেদন ও অনুমোদন প্রক্রিয়া
                </div>
                <div class="hero-feature">
                    <span class="hero-feature-num">II.</span>
                    <span class="hero-feature-icon"><i class="ti tabler-currency-taka"></i></span>
                    বার্ষিক বেতন বৃদ্ধি ব্যবস্থাপনা
                </div>
                <div class="hero-feature">
                    <span class="hero-feature-num">III.</span>
                    <span class="hero-feature-icon"><i class="ti tabler-report"></i></span>
                    প্রতিবেদন ও অফিস আদেশ প্রণয়ন
                </div>
            </div>
        </div>

        <!-- Bottom meta -->
        <div class="hero-footer">
            <span>© <?= date('Y') ?> BITAC · সকল অধিকার সংরক্ষিত</span>
            <span>
                Developed by <a href="https://technocratsbd.com" target="_blank" rel="noopener">Technocrats</a>
            </span>
        </div>
    </div>

    <!-- ═══════════════════ RIGHT: form ═══════════════════ -->
    <div class="login-form-side">

        <!-- Security / info strip -->
        <div class="form-topstrip">
            <span class="secure">
                <i class="ti tabler-shield-check"></i>
                ব্যবহারকারীর লগইন
            </span>
            <span class="meta">সর্বশেষ আপডেট: <?= date('d/m/Y') ?></span>
        </div>

        <!-- Form -->
        <div class="login-form-container">
            <div class="login-card">
                <div class="login-brand">
                    <span class="login-brand-mark">
                        <img src="<?= htmlspecialchars($logoURL) ?>" alt="BITAC" onerror="this.style.display='none'">
                    </span>
                    <div class="login-brand-body">
                        <span class="abbr">BITAC</span>
                        <span class="full">Leave Management System</span>
                        <span class="divider"></span>
                    </div>
                </div>

                <h2 class="login-heading">ব্যবহারকারী লগইন</h2>
                <p class="login-sub">অনুগ্রহপূর্বক আপনার ইউজারনেম ও পাসওয়ার্ড প্রদান করুন</p>

                <form name="form" id="form" action="login_action.php" method="POST" autocomplete="on">
                    <label class="form-label" for="username">ইউজারনেম</label>
                    <div class="input-wrap">
                        <i class="ti tabler-user"></i>
                        <input type="text" class="form-control" name="username" id="username"
                               placeholder="আপনার ইউজারনেম" autocomplete="username" required>
                    </div>

                    <label class="form-label" for="password">পাসওয়ার্ড</label>
                    <div class="input-wrap">
                        <i class="ti tabler-lock"></i>
                        <input type="password" class="form-control" name="password" id="password"
                               placeholder="আপনার পাসওয়ার্ড" autocomplete="current-password" required>
                        <button type="button" class="pwd-toggle" id="togglePwd" aria-label="Show password">
                            <i class="ti tabler-eye-off" id="pwdEyeIcon"></i>
                        </button>
                    </div>

                    <div class="form-row-extra">
                        <label class="form-check">
                            <input type="checkbox" name="rememberme" id="rememberme">
                            <span>আমাকে মনে রাখুন</span>
                        </label>
                        <a href="#" class="form-help-link" onclick="event.preventDefault(); Swal.fire({icon:'info',title:'পাসওয়ার্ড সহায়তা',text:'পাসওয়ার্ড পুনরুদ্ধারের জন্য আপনার সংশ্লিষ্ট কেন্দ্রের প্রশাসন বিভাগের সাথে যোগাযোগ করুন।',confirmButtonColor:'#1e3a5f',customClass:{confirmButton:'btn btn-primary'},buttonsStyling:false});">সহায়তা</a>
                    </div>

                    <button type="submit" id="submit" class="btn-login">
                        <i class="ti tabler-login-2"></i>
                        <span id="submitLabel">প্রবেশ করুন</span>
                    </button>
                </form>

                <div class="login-note">
                    <strong>প্রথমবার প্রবেশ করছেন?</strong> আপনার অ্যাকাউন্ট তৈরি ও লগইন সংক্রান্ত সহায়তার জন্য অনুগ্রহপূর্বক আপনার সংশ্লিষ্ট কেন্দ্রের প্রশাসন বিভাগের সাথে যোগাযোগ করুন।
                </div>
            </div>
        </div>

        <!-- Form footer -->
        <div class="form-bottom">
            <span>v <?= date('Y.m') ?></span>
            <span>Session · <?= htmlspecialchars($clientIP) ?></span>
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
    $('#togglePwd').on('click', function() {
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
            confirmButtonColor: '#1e3a5f',
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
        $label.html('<span style="display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,0.35);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;margin-right:6px;vertical-align:-2px;"></span> প্রক্রিয়াকরণ...');

        $.ajax({
            url: 'login_action.php',
            type: 'POST',
            dataType: 'html',
            data: $form.serialize(),
            success: function(data) {
                if (data == 0) {
                    swalError('ভুল ইউজারনেম বা পাসওয়ার্ড');
                    $submit.prop('disabled', false);
                    $label.text('প্রবেশ করুন');
                } else {
                    $label.text('সফল · রিডাইরেক্ট হচ্ছে...');
                    window.location = 'dashboard?menuslug=dashboard';
                }
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

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

</body>
</html>
