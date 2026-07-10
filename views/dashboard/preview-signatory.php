<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
// ───────────────────────────────────────────────────────────
// PREVIEW: Signatory + Employee hybrid Dashboard — static placeholder
// ───────────────────────────────────────────────────────────
?>

<style>
.preview-banner {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 4px solid #f59e0b;
    padding: 10px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    color: #78350f;
}

/* Hero with role indicator */
.dash-hero {
    background: linear-gradient(135deg, #7c6ba4 0%, #a89cc4 100%);
    color: #fff;
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: 0 4px 20px rgba(124, 107, 164, 0.15);
}
.dash-hero h4 { color:#fff; margin:0 0 4px; font-weight:700; }
.dash-hero .role-pill {
    background: rgba(255,255,255,0.22);
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 0.78rem;
    font-weight: 600;
}
.dash-hero .hero-meta { font-size:0.85rem; opacity:0.9; }

/* Big action card */
.action-card {
    background: linear-gradient(135deg, #fbf6dd 0%, #faf2dc 100%);
    border: 1px solid #e8d99c;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 18px;
    cursor: pointer;
    transition: all .25s ease;
}
.action-card:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(212,160,86,0.15); }
.action-card .ac-icon {
    width: 64px; height: 64px;
    background: #fff;
    border-radius: 16px;
    display:flex; align-items:center; justify-content:center;
    font-size: 2rem;
    color: #c89060;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(200,144,96,0.15);
}
.action-card .ac-content { flex:1; }
.action-card .ac-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #8b6f47;
    margin-bottom: 4px;
}
.action-card .ac-num {
    font-size: 2.4rem;
    font-weight: 800;
    color: #a47b54;
    line-height: 1;
    display:inline-block;
}
.action-card .ac-meta { font-size: 0.84rem; color: #8b6f47; margin-top:6px; }
.action-card .ac-arrow {
    font-size: 1.5rem;
    color: #c89060;
    flex-shrink: 0;
    transition: transform .25s ease;
}
.action-card:hover .ac-arrow { transform: translateX(4px); }

/* Stat strip */
.stat-strip { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
.stat-pill {
    flex:1; min-width:160px;
    background:#fff;
    border-radius:12px;
    padding:14px 18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
    display:flex; align-items:center; gap:14px;
}
.stat-pill .sp-icon {
    width:42px; height:42px;
    border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem;
    flex-shrink:0;
}
.stat-pill.s-green .sp-icon { background:#e8f5ee; color:#5fa885; }
.stat-pill.s-red .sp-icon { background:#fbeded; color:#c97777; }
.stat-pill.s-blue .sp-icon { background:#e8eef9; color:#6c8cc4; }
.stat-pill.s-amber .sp-icon { background:#faf2dc; color:#c89060; }
.stat-pill .sp-num { font-size:1.4rem; font-weight:700; color:#1f2937; line-height:1; }
.stat-pill .sp-label { font-size:0.78rem; color:#6b7280; margin-top:2px; }

/* Pending queue */
.section-card {
    background:#fff;
    border-radius:12px;
    padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    margin-bottom:18px;
    height: calc(100% - 18px);
    display: flex;
    flex-direction: column;
}
.section-card .sc-title { flex-shrink: 0; }
.section-card .sc-title {
    font-weight:600;
    color:#1f2937;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    font-size:0.95rem;
}
.section-card .sc-title .sc-link {
    font-size:0.78rem;
    color:#7d9bc5;
    text-decoration:none;
    font-weight:500;
}

.pending-row {
    display:flex; align-items:center; gap:12px;
    padding:12px 8px;
    border:1px solid #e5e7eb;
    border-radius:10px;
    margin-bottom:8px;
    background: #fff;
    transition: all .15s ease;
}
.pending-row:hover { border-color:#a89cc4; background:#faf7fc; }
.pending-row .p-avatar {
    width:42px; height:42px;
    border-radius:50%;
    background: linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 100%);
    color:#3730a3;
    display:flex; align-items:center; justify-content:center;
    font-weight:700;
    flex-shrink:0;
}
.pending-row .p-info { flex:1; min-width:0; }
.pending-row .p-name { font-weight:600; color:#1f2937; font-size:0.92rem; }
.pending-row .p-detail { font-size:0.78rem; color:#6b7280; margin-top:2px; }
.pending-row .p-center {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.72rem;
    color: #5b21b6;
    background: #ede9fe;
    padding: 1px 7px;
    border-radius: 4px;
    margin-top: 4px;
    font-weight: 500;
}
.pending-row .p-center i { font-size: 0.8rem; }

/* Center filter chips */
.center-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px dashed #e5e7eb;
}
.center-chip {
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    padding: 5px 12px;
    border-radius: 99px;
    font-size: 0.78rem;
    color: #4b5563;
    cursor: pointer;
    transition: all .15s ease;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.center-chip:hover { border-color: #8b5cf6; color: #5b21b6; }
.center-chip.active {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    border-color: transparent;
    color: #fff;
}
.center-chip .cc-count {
    background: rgba(0,0,0,0.08);
    padding: 1px 7px;
    border-radius: 99px;
    font-size: 0.7rem;
    font-weight: 600;
}
.center-chip.active .cc-count { background: rgba(255,255,255,0.25); }
.pending-row .p-pill {
    font-size:0.72rem;
    padding:3px 9px;
    border-radius:5px;
    font-weight:600;
    flex-shrink:0;
}
.pending-row .p-actions { display:flex; gap:6px; flex-shrink:0; }
.pending-row .p-btn {
    width:34px; height:34px;
    border:none;
    border-radius:8px;
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer;
    font-size:1rem;
    transition: all .15s ease;
}
.pending-row .p-btn.b-view { background:#e8f0f9; color:#6c8cc4; }
.pending-row .p-btn.b-approve { background:#e8f5ee; color:#5fa885; }
.pending-row .p-btn.b-forward { background:#efeaf5; color:#7c6ba4; }
.pending-row .p-btn:hover { transform:scale(1.08); }

/* Personal compact strip */
.personal-strip {
    background: linear-gradient(135deg, #f5f8fc 0%, #e8eef9 100%);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.personal-strip .ps-label {
    font-size: 0.82rem;
    color: #5b7396;
    font-weight: 600;
    flex-shrink: 0;
}
.personal-strip .ps-tile {
    background: #fff;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.82rem;
    color: #4b5563;
    border: 1px solid #d6dff0;
}
.personal-strip .ps-tile strong { color:#5b7396; }
.personal-strip .ps-link {
    margin-left: auto;
    font-size: 0.82rem;
    color: #6c8cc4;
    text-decoration: none;
    font-weight: 500;
}

/* Mini approval activity chart */
.mini-bars { display:flex; align-items:flex-end; gap:5px; height:80px; padding-top:14px; }
.mini-bars .mb-bar {
    flex:1;
    background:linear-gradient(180deg, #b9aed1 0%, #7c6ba4 100%);
    border-radius:3px 3px 0 0;
    min-height:3px;
    position:relative;
}
.mini-bars .mb-bar::after {
    content: attr(data-val);
    position:absolute; top:-16px; left:50%;
    transform:translateX(-50%);
    font-size:0.66rem; color:#7c6ba4; font-weight:600;
}
.mini-bars-labels { display:flex; gap:5px; }
.mini-bars-labels span { flex:1; text-align:center; font-size:0.66rem; color:#6b7280; }
</style>

<div class="preview-banner">
    <i class="ti tabler-flask me-1"></i><strong>PREVIEW</strong> — Signatory + Employee Hybrid Dashboard (placeholder data)
</div>

<!-- Hero with role indicator -->
<div class="dash-hero">
    <div>
        <span class="role-pill"><i class="ti tabler-signature me-1"></i>Signatory · কর্মচারী</span>
        <h4 class="mt-2">শুভ সকাল, মোঃ ফেরদৌস রহমান</h4>
        <div class="hero-meta">উপ-পরিচালক · প্রশাসন শাখা · বিটাক, প্রধান কার্যালয়</div>
    </div>
    <div class="text-end">
        <div style="font-size:0.78rem;opacity:0.85;">আজকের তারিখ</div>
        <div style="font-size:1.2rem;font-weight:600;">শনিবার, ২ মে ২০২৬</div>
    </div>
</div>

<!-- BIG action card — pending approvals -->
<a href="#" class="action-card" style="text-decoration:none;">
    <span class="ac-icon"><i class="ti tabler-clipboard-list"></i></span>
    <div class="ac-content">
        <div class="ac-title">আপনার অনুমোদনের অপেক্ষায়</div>
        <div><span class="ac-num">৭</span> <span style="font-size:0.95rem;color:#92400e;font-weight:500;margin-left:6px;">টি ছুটির আবেদন</span></div>
        <div class="ac-meta"><i class="ti tabler-clock me-1"></i>সবচেয়ে পুরাতনটি ৩ দিন আগে জমা হয়েছে</div>
    </div>
    <i class="ti tabler-arrow-right ac-arrow"></i>
</a>

<!-- Activity stat strip -->
<div class="stat-strip">
    <div class="stat-pill s-green">
        <span class="sp-icon"><i class="ti tabler-check"></i></span>
        <div>
            <div class="sp-num">২৪</div>
            <div class="sp-label">এই মাসে অনুমোদিত</div>
        </div>
    </div>
    <div class="stat-pill s-red">
        <span class="sp-icon"><i class="ti tabler-x"></i></span>
        <div>
            <div class="sp-num">৩</div>
            <div class="sp-label">এই মাসে বাতিল</div>
        </div>
    </div>
    <div class="stat-pill s-amber">
        <span class="sp-icon"><i class="ti tabler-clock"></i></span>
        <div>
            <div class="sp-num">১.৪ দিন</div>
            <div class="sp-label">গড় approval সময়</div>
        </div>
    </div>
    <div class="stat-pill s-blue">
        <span class="sp-icon"><i class="ti tabler-arrow-forward"></i></span>
        <div>
            <div class="sp-num">৫</div>
            <div class="sp-label">এই মাসে forward</div>
        </div>
    </div>
</div>

<!-- Pending queue (top 5) -->
<div class="section-card">
    <div class="sc-title">
        <span><i class="ti tabler-clipboard-list me-1" style="color:#c89060;"></i>সাম্প্রতিক pending আবেদন</span>
        <a href="#" class="sc-link">সব ৭ টি দেখুন →</a>
    </div>

    <!-- Center filter chips -->
    <div class="center-chips">
        <span class="center-chip active"><i class="ti tabler-asterisk"></i> সব <span class="cc-count">৭</span></span>
        <span class="center-chip"><i class="ti tabler-building"></i> প্রধান কার্যালয়, ঢাকা <span class="cc-count">৩</span></span>
        <span class="center-chip"><i class="ti tabler-building"></i> চট্টগ্রাম <span class="cc-count">২</span></span>
        <span class="center-chip"><i class="ti tabler-building"></i> খুলনা <span class="cc-count">১</span></span>
        <span class="center-chip"><i class="ti tabler-building"></i> রাজশাহী <span class="cc-count">১</span></span>
    </div>

    <div class="pending-row">
        <span class="p-avatar">র</span>
        <div class="p-info">
            <div class="p-name">রহিম উদ্দিন</div>
            <div class="p-detail">৫ মে → ২৫ মে · ২০ দিন · জমা: ২ দিন আগে</div>
            <span class="p-center"><i class="ti tabler-building"></i> চট্টগ্রাম কেন্দ্র</span>
        </div>
        <span class="p-pill" style="background:#fbeded;color:#c97777;">অর্ধ-গড়</span>
        <div class="p-actions">
            <button class="p-btn b-view" title="দেখুন"><i class="ti tabler-eye"></i></button>
            <button class="p-btn b-approve" title="অনুমোদন"><i class="ti tabler-check"></i></button>
            <button class="p-btn b-forward" title="Forward"><i class="ti tabler-arrow-forward"></i></button>
        </div>
    </div>
    <div class="pending-row">
        <span class="p-avatar" style="background:linear-gradient(135deg,#fbcfe8,#f9a8d4);color:#9d174d;">সু</span>
        <div class="p-info">
            <div class="p-name">সুফিয়া আক্তার</div>
            <div class="p-detail">১০ মে → ১২ মে · ৩ দিন · জমা: কাল</div>
            <span class="p-center"><i class="ti tabler-building"></i> প্রধান কার্যালয়, ঢাকা</span>
        </div>
        <span class="p-pill" style="background:#e8f5ee;color:#5fa885;">নৈমিত্তিক</span>
        <div class="p-actions">
            <button class="p-btn b-view"><i class="ti tabler-eye"></i></button>
            <button class="p-btn b-approve"><i class="ti tabler-check"></i></button>
            <button class="p-btn b-forward"><i class="ti tabler-arrow-forward"></i></button>
        </div>
    </div>
    <div class="pending-row">
        <span class="p-avatar" style="background:linear-gradient(135deg,#bfdbfe,#93c5fd);color:#1e3a8a;">কা</span>
        <div class="p-info">
            <div class="p-name">কামাল হোসেন</div>
            <div class="p-detail">৮ মে → ২০ মে · ১৩ দিন · জমা: ৩ দিন আগে</div>
            <span class="p-center"><i class="ti tabler-building"></i> খুলনা কেন্দ্র</span>
        </div>
        <span class="p-pill" style="background:#e8eef9;color:#5b7396;">পূর্ণ গড়</span>
        <div class="p-actions">
            <button class="p-btn b-view"><i class="ti tabler-eye"></i></button>
            <button class="p-btn b-approve"><i class="ti tabler-check"></i></button>
            <button class="p-btn b-forward"><i class="ti tabler-arrow-forward"></i></button>
        </div>
    </div>
    <div class="pending-row">
        <span class="p-avatar" style="background:linear-gradient(135deg,#bbf7d0,#86efac);color:#14532d;">আ</span>
        <div class="p-info">
            <div class="p-name">আনিসুর রহমান</div>
            <div class="p-detail">৪ মে → ৫ মে · ২ দিন · জমা: আজ</div>
            <span class="p-center"><i class="ti tabler-building"></i> প্রধান কার্যালয়, ঢাকা</span>
        </div>
        <span class="p-pill" style="background:#e8f5ee;color:#5fa885;">নৈমিত্তিক</span>
        <div class="p-actions">
            <button class="p-btn b-view"><i class="ti tabler-eye"></i></button>
            <button class="p-btn b-approve"><i class="ti tabler-check"></i></button>
            <button class="p-btn b-forward"><i class="ti tabler-arrow-forward"></i></button>
        </div>
    </div>
    <div class="pending-row">
        <span class="p-avatar" style="background:linear-gradient(135deg,#fde68a,#fcd34d);color:#78350f;">ফা</span>
        <div class="p-info">
            <div class="p-name">ফারহানা ইসলাম</div>
            <div class="p-detail">১৪ মে → ১৬ মে · ৩ দিন · জমা: আজ</div>
            <span class="p-center"><i class="ti tabler-building"></i> রাজশাহী কেন্দ্র</span>
        </div>
        <span class="p-pill" style="background:#efeaf5;color:#7c6ba4;">ঐচ্ছিক</span>
        <div class="p-actions">
            <button class="p-btn b-view"><i class="ti tabler-eye"></i></button>
            <button class="p-btn b-approve"><i class="ti tabler-check"></i></button>
            <button class="p-btn b-forward"><i class="ti tabler-arrow-forward"></i></button>
        </div>
    </div>
</div>

<!-- Personal compact strip -->
<div class="personal-strip">
    <span class="ps-label"><i class="ti tabler-user me-1"></i>আপনার নিজের তথ্য:</span>
    <span class="ps-tile">পূর্ণ গড়: <strong>১২০</strong> দিন</span>
    <span class="ps-tile">অর্ধ-গড়: <strong>১৮০</strong> দিন</span>
    <span class="ps-tile">নৈমিত্তিক: <strong>২০</strong> দিন</span>
    <span class="ps-tile">নিজের আবেদন: <strong style="color:#dc2626;">২ টি pending</strong></span>
    <a href="#" class="ps-link">নিজের details দেখুন →</a>
</div>

<!-- Approval activity + Quick links -->
<div class="row g-3">
    <div class="col-12 col-md-7">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-chart-line me-1" style="color:#a89cc4;"></i>আপনার অনুমোদনের ইতিহাস</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">সর্বশেষ ৬ মাস</span>
            </div>
            <div class="mini-bars">
                <div class="mb-bar" style="height:50%;" data-val="১২"></div>
                <div class="mb-bar" style="height:65%;" data-val="১৬"></div>
                <div class="mb-bar" style="height:80%;" data-val="২০"></div>
                <div class="mb-bar" style="height:55%;" data-val="১৪"></div>
                <div class="mb-bar" style="height:90%;" data-val="২২"></div>
                <div class="mb-bar" style="height:100%;" data-val="২৪"></div>
            </div>
            <div class="mini-bars-labels mt-2">
                <span>নভে</span><span>ডিসে</span><span>জানু</span><span>ফেব্রু</span><span>মার্চ</span><span>এপ্রিল</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-5">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-bolt me-1" style="color:#d4a056;"></i>দ্রুত কাজ</span>
            </div>
            <a href="#" class="d-flex align-items-center gap-2 p-2 rounded mb-2" style="background:#f5f0fa;text-decoration:none;color:#7c6ba4;font-size:0.86rem;">
                <i class="ti tabler-clipboard-list"></i> Approval Queue (৭)
            </a>
            <a href="#" class="d-flex align-items-center gap-2 p-2 rounded mb-2" style="background:#f0f4fa;text-decoration:none;color:#5b7396;font-size:0.86rem;">
                <i class="ti tabler-history"></i> পূর্বের সিদ্ধান্ত দেখুন
            </a>
            <a href="#" class="d-flex align-items-center gap-2 p-2 rounded mb-2" style="background:#eef7f0;text-decoration:none;color:#5fa885;font-size:0.86rem;">
                <i class="ti tabler-plus"></i> নিজে নতুন ছুটির আবেদন
            </a>
            <a href="#" class="d-flex align-items-center gap-2 p-2 rounded" style="background:#faf2dc;text-decoration:none;color:#8b6f47;font-size:0.86rem;">
                <i class="ti tabler-calendar-event"></i> ছুটির ক্যালেন্ডার
            </a>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
