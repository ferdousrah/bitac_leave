<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
// ───────────────────────────────────────────────────────────
// PREVIEW: Center Admin Dashboard — static placeholder data
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
.dash-hero {
    background: linear-gradient(135deg, #5b7396 0%, #7d9bc5 100%);
    color: #fff;
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: 0 4px 20px rgba(91, 115, 150, 0.15);
}
.dash-hero h4 { color:#fff; margin:0 0 4px; font-weight:700; }
.dash-hero .role-pill {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 0.78rem;
    font-weight: 600;
}
.dash-hero .hero-meta { font-size:0.85rem; opacity:0.9; }
.hero-stats {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    align-items: center;
}
.hero-stat {
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 10px;
    padding: 10px 16px;
    text-align: center;
    min-width: 120px;
    backdrop-filter: blur(4px);
}
.hero-stat .hs-num {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}
.hero-stat .hs-label {
    font-size: 0.74rem;
    opacity: 0.88;
    margin-top: 4px;
    letter-spacing: 0.2px;
}
.hero-stat.is-emphasis {
    background: rgba(255,255,255,0.95);
    color: #4a5b78;
    border-color: rgba(255,255,255,0.95);
}
.hero-stat.is-emphasis .hs-label { opacity: 0.75; color:#4a5b78; }

.kpi-card {
    background:#fff;
    border-radius:12px;
    padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    transition: all .2s ease;
    height:100%;
    position:relative;
    overflow:hidden;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.1); }
.kpi-card .kpi-icon {
    width: 48px; height: 48px;
    border-radius: 10px;
    display:inline-flex;
    align-items:center; justify-content:center;
    font-size:1.4rem;
    margin-bottom:10px;
}
.kpi-card .kpi-num { font-size:1.8rem; font-weight:700; color:#1f2937; line-height:1; }
.kpi-card .kpi-label { font-size:0.82rem; color:#6b7280; margin-top:4px; }
.kpi-card .kpi-action { font-size:0.75rem; color:#7d9bc5; margin-top:8px; display:inline-block; }

.kpi-card.k-red    .kpi-icon { background:#fef0f0; color:#e76f6f; }
.kpi-card.k-amber  .kpi-icon { background:#fef7e6; color:#d4a056; }
.kpi-card.k-green  .kpi-icon { background:#e8f5ee; color:#5fa885; }
.kpi-card.k-blue   .kpi-icon { background:#e8eef9; color:#6c8cc4; }

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

.emp-row {
    display:flex;
    align-items:center;
    gap:12px;
    padding:10px 6px;
    border-bottom:1px solid #f3f4f6;
    font-size:0.85rem;
}
.emp-row:last-child { border-bottom:none; }
.emp-row .emp-avatar {
    width:36px; height:36px;
    border-radius:50%;
    background:linear-gradient(135deg, #c7d2fe 0%, #a5b4fc 100%);
    color:#3730a3;
    display:inline-flex;
    align-items:center; justify-content:center;
    font-weight:600;
    font-size:0.8rem;
    flex-shrink:0;
}
.emp-row .emp-info { flex:1; min-width:0; }
.emp-row .emp-name { font-weight:600; color:#1f2937; }
.emp-row .emp-meta { font-size:0.75rem; color:#6b7280; }
.emp-row .emp-pill {
    font-size:0.7rem;
    padding:2px 8px;
    border-radius:4px;
    font-weight:500;
    flex-shrink:0;
}
.emp-row .emp-return { font-size:0.74rem; color:#5fa885; flex-shrink:0; }

.donut {
    width:140px; height:140px;
    border-radius:50%;
    background: conic-gradient(#7d9bc5 0% 42%, #d4a056 42% 64%, #7fb59c 64% 84%, #a89cc4 84% 100%);
    margin:8px auto;
    display:flex;
    align-items:center; justify-content:center;
}
.donut::after {
    content:"";
    width:84px; height:84px;
    background:#fff;
    border-radius:50%;
}
.donut-legend { font-size:0.78rem; }
.donut-legend .dl-row {
    display:flex; align-items:center; gap:8px;
    padding:4px 0;
}
.donut-legend .dl-dot { width:10px; height:10px; border-radius:2px; flex-shrink:0; }

.bar-trend { display:flex; align-items:flex-end; gap:6px; height:140px; padding-top:8px; }
.bar-trend .bt-bar {
    flex:1;
    background:linear-gradient(180deg, #8da9ce 0%, #5b7396 100%);
    border-radius:4px 4px 0 0;
    position:relative;
    min-height:4px;
}
.bar-trend .bt-bar::after {
    content: attr(data-val);
    position:absolute;
    top:-18px;
    left:50%;
    transform:translateX(-50%);
    font-size:0.7rem;
    color:#1f2937;
    font-weight:600;
}
.bar-trend .bt-label {
    text-align:center;
    font-size:0.68rem;
    color:#6b7280;
    margin-top:6px;
}

.section-bar {
    display:flex; align-items:center; gap:10px;
    padding:8px 0;
}
.section-bar .sb-name { flex:0 0 130px; font-size:0.84rem; color:#1f2937; font-weight:500; }
.section-bar .sb-track { flex:1; height:8px; background:#f3f4f6; border-radius:99px; overflow:hidden; }
.section-bar .sb-fill {
    height:100%; border-radius:99px;
    background: linear-gradient(90deg, #8b9dc9 0%, #a89cc4 100%);
}
.section-bar .sb-num { flex:0 0 60px; text-align:right; font-size:0.82rem; color:#7382a6; font-weight:600; }

.quick-action-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
.quick-action {
    display:flex; align-items:center; gap:10px;
    padding:14px 12px;
    background:#f9fafb;
    border:1px solid #e5e7eb;
    border-radius:10px;
    cursor:pointer;
    transition: all .15s ease;
    text-decoration:none;
    color:#1f2937;
}
.quick-action:hover {
    background:#fff;
    border-color:#7d9bc5;
    transform:translateY(-1px);
    color:#7d9bc5;
}
.quick-action i { font-size:1.2rem; color:#7d9bc5; }
.quick-action span { font-size:0.85rem; font-weight:500; }
</style>

<div class="preview-banner">
    <i class="ti tabler-flask me-1"></i><strong>PREVIEW</strong> — Center Admin Dashboard (placeholder data, design review-এর জন্য)
</div>

<!-- Hero -->
<div class="dash-hero">
    <div>
        <span class="role-pill"><i class="ti tabler-shield-check me-1"></i>সেন্টার অ্যাডমিন</span>
        <h4 class="mt-2">স্বাগতম, আবদুল্লাহ আল-মামুন</h4>
        <div class="hero-meta">বিটাক, ঢাকা প্রধান কার্যালয় · শনিবার, ২ মে ২০২৬</div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat is-emphasis">
            <div class="hs-num">৪৬</div>
            <div class="hs-label">মোট কর্মচারী</div>
        </div>
        <div class="hero-stat">
            <div class="hs-num">৩৪</div>
            <div class="hs-label">কর্মস্থলে</div>
        </div>
        <div class="hero-stat">
            <div class="hs-num">১২</div>
            <div class="hs-label">ছুটিতে</div>
        </div>
    </div>
</div>

<!-- Action KPI Cards -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="kpi-card k-amber">
            <span class="kpi-icon"><i class="ti tabler-clock"></i></span>
            <div class="kpi-num">৫</div>
            <div class="kpi-label">approval-এর অপেক্ষায়</div>
            <a href="#" class="kpi-action">সব দেখুন →</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-red">
            <span class="kpi-icon"><i class="ti tabler-user-off"></i></span>
            <div class="kpi-num">১২</div>
            <div class="kpi-label">আজ ছুটিতে আছেন</div>
            <a href="#" class="kpi-action">তালিকা →</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-green">
            <span class="kpi-icon"><i class="ti tabler-check"></i></span>
            <div class="kpi-num">৪৭</div>
            <div class="kpi-label">এই মাসে অনুমোদিত</div>
            <a href="#" class="kpi-action">রিপোর্ট →</a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card k-blue">
            <span class="kpi-icon"><i class="ti tabler-file-text"></i></span>
            <div class="kpi-num">১৮</div>
            <div class="kpi-label">এই সপ্তাহে নতুন আবেদন</div>
            <a href="#" class="kpi-action">দেখুন →</a>
        </div>
    </div>
</div>

<!-- Today on leave + Upcoming returns -->
<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-user-off me-1" style="color:#c97777;"></i>আজ ছুটিতে আছেন</span>
                <a href="#" class="sc-link">সম্পূর্ণ তালিকা →</a>
            </div>
            <div class="emp-row">
                <span class="emp-avatar">রহ</span>
                <div class="emp-info">
                    <div class="emp-name">রহিম উদ্দিন</div>
                    <div class="emp-meta">সহকারী পরিচালক · পরিকল্পনা শাখা</div>
                </div>
                <span class="emp-pill" style="background:#fbeded;color:#c97777;">অর্ধ-গড়</span>
                <span class="emp-return">ফিরবেন: ৫ মে</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:linear-gradient(135deg,#fbcfe8,#f9a8d4);color:#9d174d;">সু</span>
                <div class="emp-info">
                    <div class="emp-name">সুফিয়া আক্তার</div>
                    <div class="emp-meta">সহযোগী পরিচালক · প্রশিক্ষণ শাখা</div>
                </div>
                <span class="emp-pill" style="background:#fbe7eb;color:#b46578;">প্রসূতি</span>
                <span class="emp-return">ফিরবেন: ১৫ আগস্ট</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:linear-gradient(135deg,#bfdbfe,#93c5fd);color:#1e3a8a;">কা</span>
                <div class="emp-info">
                    <div class="emp-name">কামাল হোসেন</div>
                    <div class="emp-meta">টেকনিকাল অ্যাসিস্ট্যান্ট · কারিগরি শাখা</div>
                </div>
                <span class="emp-pill" style="background:#e8eef9;color:#5b7396;">পূর্ণ গড়</span>
                <span class="emp-return">ফিরবেন: ১০ মে</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:linear-gradient(135deg,#bbf7d0,#86efac);color:#14532d;">আ</span>
                <div class="emp-info">
                    <div class="emp-name">আনিসুর রহমান</div>
                    <div class="emp-meta">অফিস সহকারী · প্রশাসন শাখা</div>
                </div>
                <span class="emp-pill" style="background:#e8f5ee;color:#5fa885;">নৈমিত্তিক</span>
                <span class="emp-return">ফিরবেন: ৩ মে</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:linear-gradient(135deg,#fde68a,#fcd34d);color:#78350f;">ফা</span>
                <div class="emp-info">
                    <div class="emp-name">ফারহানা ইসলাম</div>
                    <div class="emp-meta">সহকারী পরিচালক · হিসাব শাখা</div>
                </div>
                <span class="emp-pill" style="background:#efeaf5;color:#7c6ba4;">ঐচ্ছিক</span>
                <span class="emp-return">ফিরবেন: ৬ মে</span>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-calendar-check me-1" style="color:#5fa885;"></i>আসন্ন ফেরত</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">পরের ৭ দিনে</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar">আ</span>
                <div class="emp-info">
                    <div class="emp-name">আনিসুর রহমান</div>
                    <div class="emp-meta">কাল, ৩ মে</div>
                </div>
            </div>
            <div class="emp-row">
                <span class="emp-avatar">র</span>
                <div class="emp-info">
                    <div class="emp-name">রহিম উদ্দিন</div>
                    <div class="emp-meta">৫ মে (৩ দিন পর)</div>
                </div>
            </div>
            <div class="emp-row">
                <span class="emp-avatar">ফা</span>
                <div class="emp-info">
                    <div class="emp-name">ফারহানা ইসলাম</div>
                    <div class="emp-meta">৬ মে (৪ দিন পর)</div>
                </div>
            </div>
            <div class="emp-row">
                <span class="emp-avatar">কা</span>
                <div class="emp-info">
                    <div class="emp-name">কামাল হোসেন</div>
                    <div class="emp-meta">১০ মে (৮ দিন পর)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-3">
    <div class="col-12 col-md-7">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-chart-bar me-1" style="color:#7d9bc5;"></i>মাসিক অনুমোদনের প্রবণতা</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">সর্বশেষ ৬ মাস</span>
            </div>
            <div class="bar-trend">
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:50%;" data-val="২৪"></div>
                    <div class="bt-label">নভে</div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:65%;" data-val="৩১"></div>
                    <div class="bt-label">ডিসে</div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:80%;" data-val="৩৮"></div>
                    <div class="bt-label">জানু</div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:55%;" data-val="২৬"></div>
                    <div class="bt-label">ফেব্রু</div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:90%;" data-val="৪৩"></div>
                    <div class="bt-label">মার্চ</div>
                </div>
                <div style="flex:1;display:flex;flex-direction:column;">
                    <div class="bt-bar" style="height:100%;" data-val="৪৭"></div>
                    <div class="bt-label">এপ্রিল</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-5">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-chart-donut me-1" style="color:#a89cc4;"></i>ছুটির ধরণ অনুযায়ী</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই মাসে</span>
            </div>
            <div class="row align-items-center">
                <div class="col-6"><div class="donut"></div></div>
                <div class="col-6">
                    <div class="donut-legend">
                        <div class="dl-row"><span class="dl-dot" style="background:#3b82f6;"></span> পূর্ণ গড় ৪২%</div>
                        <div class="dl-row"><span class="dl-dot" style="background:#f59e0b;"></span> অর্ধ-গড় ২২%</div>
                        <div class="dl-row"><span class="dl-dot" style="background:#10b981;"></span> নৈমিত্তিক ২০%</div>
                        <div class="dl-row"><span class="dl-dot" style="background:#8b5cf6;"></span> অন্যান্য ১৬%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section breakdown + Top users + Quick actions -->
<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-building me-1" style="color:#8b9dc9;"></i>শাখা অনুযায়ী ছুটি</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই মাসে</span>
            </div>
            <div class="section-bar"><div class="sb-name">কারিগরি শাখা</div><div class="sb-track"><div class="sb-fill" style="width:90%;"></div></div><div class="sb-num">১৮</div></div>
            <div class="section-bar"><div class="sb-name">প্রশাসন শাখা</div><div class="sb-track"><div class="sb-fill" style="width:75%;"></div></div><div class="sb-num">১৫</div></div>
            <div class="section-bar"><div class="sb-name">প্রশিক্ষণ শাখা</div><div class="sb-track"><div class="sb-fill" style="width:60%;"></div></div><div class="sb-num">১২</div></div>
            <div class="section-bar"><div class="sb-name">পরিকল্পনা শাখা</div><div class="sb-track"><div class="sb-fill" style="width:50%;"></div></div><div class="sb-num">১০</div></div>
            <div class="section-bar"><div class="sb-name">হিসাব শাখা</div><div class="sb-track"><div class="sb-fill" style="width:40%;"></div></div><div class="sb-num">৮</div></div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="section-card">
            <div class="sc-title">
                <span><i class="ti tabler-flame me-1" style="color:#c97777;"></i>সর্বাধিক ছুটিগ্রহণকারী</span>
                <span style="font-size:0.74rem;color:#6b7280;font-weight:500;">এই বছর</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:#fbeded;color:#c97777;">১</span>
                <div class="emp-info">
                    <div class="emp-name">রহিম উদ্দিন</div>
                    <div class="emp-meta">পরিকল্পনা শাখা</div>
                </div>
                <span class="emp-pill" style="background:#faf2dc;color:#8b6f47;">৪৮ দিন</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:#faf2dc;color:#a47b54;">২</span>
                <div class="emp-info">
                    <div class="emp-name">কামাল হোসেন</div>
                    <div class="emp-meta">কারিগরি শাখা</div>
                </div>
                <span class="emp-pill" style="background:#faf2dc;color:#8b6f47;">৪২ দিন</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:#fbf6dd;color:#9c8055;">৩</span>
                <div class="emp-info">
                    <div class="emp-name">সুফিয়া আক্তার</div>
                    <div class="emp-meta">প্রশিক্ষণ শাখা</div>
                </div>
                <span class="emp-pill" style="background:#e8eef9;color:#5b7396;">৩৬ দিন (প্রসূতি)</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:#f3f4f6;color:#4b5563;">৪</span>
                <div class="emp-info">
                    <div class="emp-name">আনিসুর রহমান</div>
                    <div class="emp-meta">প্রশাসন শাখা</div>
                </div>
                <span class="emp-pill" style="background:#f3f4f6;color:#4b5563;">২৪ দিন</span>
            </div>
            <div class="emp-row">
                <span class="emp-avatar" style="background:#f3f4f6;color:#4b5563;">৫</span>
                <div class="emp-info">
                    <div class="emp-name">ফারহানা ইসলাম</div>
                    <div class="emp-meta">হিসাব শাখা</div>
                </div>
                <span class="emp-pill" style="background:#f3f4f6;color:#4b5563;">২২ দিন</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-card">
    <div class="sc-title">
        <span><i class="ti tabler-bolt me-1" style="color:#d4a056;"></i>দ্রুত কাজ</span>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-3"><a href="#" class="quick-action"><i class="ti tabler-clipboard-list"></i><span>Approval Queue</span></a></div>
        <div class="col-6 col-md-3"><a href="#" class="quick-action"><i class="ti tabler-users"></i><span>কর্মচারী তালিকা</span></a></div>
        <div class="col-6 col-md-3"><a href="#" class="quick-action"><i class="ti tabler-calendar-event"></i><span>ছুটির ক্যালেন্ডার</span></a></div>
        <div class="col-6 col-md-3"><a href="#" class="quick-action"><i class="ti tabler-report-analytics"></i><span>রিপোর্টস</span></a></div>
    </div>
</div>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
