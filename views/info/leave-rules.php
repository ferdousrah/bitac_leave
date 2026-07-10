<?php
$pageTitle    = 'ছুটির বিধিমালা';
$pageSubtitle = 'সরকারি চাকরি বিধিমালা — সহজ ভাষায়';

require_once(__DIR__ . '/../../includes/header_vuexy.php');
?>

<style>
/* ─── Info hub styles ─── */
.info-hero {
    background: linear-gradient(135deg, #696cff 0%, #7367f0 100%);
    color: #fff;
    border-radius: 14px;
    padding: 26px 30px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.info-hero::after {
    content: "";
    position: absolute;
    right: -40px; top: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}
.info-hero h4 { color: #fff; font-weight: 600; margin: 0 0 6px; font-size: 1.4rem; }
.info-hero p { color: rgba(255,255,255,0.85); margin: 0; font-size: 0.92rem; line-height: 1.6; }

.info-section { margin-bottom: 32px; }
.info-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.info-section-title .num {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: #eef0ff;
    color: #696cff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.info-section-title h5 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1f2937;
}

/* Leave-type card */
.leave-card {
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 12px;
    padding: 18px 18px 16px;
    height: 100%;
    transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
    position: relative;
    overflow: hidden;
}
.leave-card:hover {
    box-shadow: 0 8px 22px rgba(16,24,40,0.08);
    transform: translateY(-2px);
    border-color: #dfe3ea;
}
.leave-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--lc-accent, #696cff);
}
.leave-card .lc-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 12px;
    background: var(--lc-bg, #eef0ff);
    color: var(--lc-accent, #696cff);
}
.leave-card .lc-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 8px;
}
.leave-card .lc-meta {
    display: inline-block;
    background: var(--lc-bg);
    color: var(--lc-accent);
    font-size: 0.72rem;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 999px;
    margin-bottom: 10px;
}
.leave-card .lc-desc {
    font-size: 0.85rem;
    color: #4b5563;
    line-height: 1.55;
    margin: 0;
}
.leave-card .lc-rules {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #eef0f3;
    font-size: 0.78rem;
    color: #64748b;
}
.leave-card .lc-rules strong { color: #1f2937; }

/* Highlight rule callouts */
.rule-callout {
    display: flex;
    gap: 14px;
    padding: 14px 16px;
    background: #fafbff;
    border: 1px solid #eef0f3;
    border-left: 4px solid var(--rc-accent, #696cff);
    border-radius: 0 10px 10px 0;
    margin-bottom: 12px;
}
.rule-callout .rc-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: var(--rc-bg, #eef0ff);
    color: var(--rc-accent, #696cff);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.rule-callout .rc-content { flex: 1; }
.rule-callout .rc-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 3px;
}
.rule-callout .rc-text {
    font-size: 0.85rem;
    color: #4b5563;
    line-height: 1.55;
    margin: 0;
}

/* FAQ accordion */
.faq-card {
    background: #fff;
    border: 1px solid #eef0f3;
    border-radius: 10px;
    margin-bottom: 10px;
    overflow: hidden;
    transition: border-color .15s ease;
}
.faq-card:hover { border-color: #dfe3ea; }
.faq-q {
    cursor: pointer;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 500;
    color: #1f2937;
    font-size: 0.92rem;
    background: #fafbff;
    user-select: none;
}
.faq-q:hover { background: #eef0ff; }
.faq-q .toggle-icon {
    transition: transform .2s ease;
    color: #6b7280;
}
.faq-card.open .faq-q .toggle-icon { transform: rotate(180deg); color: #696cff; }
.faq-a {
    display: none;
    padding: 14px 18px;
    border-top: 1px solid #eef0f3;
    color: #4b5563;
    font-size: 0.88rem;
    line-height: 1.7;
}
.faq-card.open .faq-a { display: block; }

.formula-box {
    background: #f3f5f8;
    padding: 12px 16px;
    border-radius: 8px;
    font-family: 'Inter', monospace;
    font-size: 0.88rem;
    color: #1f2937;
    margin: 10px 0;
}

.example-box {
    background: #f0fdf4;
    border-left: 3px solid #28c76f;
    padding: 12px 16px;
    border-radius: 6px;
    margin: 12px 0 4px;
    font-size: 0.86rem;
    color: #1f3a2c;
}
.example-box .ex-title {
    font-weight: 600;
    color: #166534;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.example-box .ex-row {
    display: flex;
    gap: 10px;
    padding: 4px 0;
    border-bottom: 1px dashed #d1fae5;
}
.example-box .ex-row:last-child { border-bottom: none; }
.example-box .ex-when {
    flex: 0 0 110px;
    font-weight: 600;
    color: #047857;
}
.example-box .ex-what { flex: 1; }
.example-box .ex-summary {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 2px solid #28c76f;
    font-weight: 600;
    color: #166534;
}
</style>

<!-- Hero -->
<div class="info-hero">
    <h4><i class="ti tabler-book me-2"></i>ছুটির বিধিমালা — সহজ ভাষায়</h4>
    <p>সরকারি চাকরি বিধিমালা (Bangladesh Service Rules) অনুযায়ী এই system-এ যে নিয়মগুলো প্রয়োগ করা আছে তার সংক্ষিপ্ত গাইড। যেকোনো কর্মচারী এখান থেকে নিজের অধিকার ও সীমাবদ্ধতা সহজে জানতে পারবেন।</p>
</div>

<!-- Section 1: Leave types -->
<div class="info-section">
    <div class="info-section-title">
        <span class="num">১</span>
        <h5>ছুটির ধরন</h5>
    </div>

    <div class="row g-3">
        <!-- পূর্ণ গড় বেতনে -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#696cff; --lc-bg:#eef0ff;">
                <span class="lc-icon"><i class="ti tabler-briefcase"></i></span>
                <h6 class="lc-title">পূর্ণ গড় বেতনে ছুটি</h6>
                <span class="lc-meta">পূর্ণ বেতন</span>
                <p class="lc-desc">নিয়মিত অর্জিত ছুটি। চাকরিকালের প্রতি ১১ দিনে ১ দিন জমা হয়।</p>
                <div class="lc-rules">
                    <strong>সীমা:</strong> এক আবেদনে / একটানা সর্বোচ্চ ১২০ দিন (৪ মাস) — <em>চাকরি জীবনের মোট না, প্রতি স্পেলে এই limit।</em>
                </div>
            </div>
        </div>

        <!-- অর্ধ-গড় বেতনে -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#00cfe8; --lc-bg:#e0f9fc;">
                <span class="lc-icon"><i class="ti tabler-chart-bar"></i></span>
                <h6 class="lc-title">অর্ধ-গড় বেতনে ছুটি</h6>
                <span class="lc-meta">অর্ধেক বেতন</span>
                <p class="lc-desc">চিকিৎসা বা ব্যক্তিগত কারণে ব্যবহার্য। প্রতি ১২ দিনে ১ দিন জমা হয়।</p>
                <div class="lc-rules">
                    <strong>Medical:</strong> ১:২ অনুপাতে পূর্ণ বেতনে রূপান্তর সম্ভব<br>
                    <strong>সংযুক্তি:</strong> Medical certificate বাধ্যতামূলক
                </div>
            </div>
        </div>

        <!-- নৈমিত্তিক -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#28c76f; --lc-bg:#e6f8ee;">
                <span class="lc-icon"><i class="ti tabler-calendar-event"></i></span>
                <h6 class="lc-title">নৈমিত্তিক (Casual) ছুটি</h6>
                <span class="lc-meta">২০ দিন/বছর</span>
                <p class="lc-desc">ব্যক্তিগত জরুরি প্রয়োজনে। সরাসরি সুপারভাইজার অনুমোদন দিতে পারেন।</p>
                <div class="lc-rules">
                    <strong>একটানা সর্বোচ্চ:</strong> ১০ দিন<br>
                    <strong>মিশানো নিষেধ:</strong> অন্য ধরনের ছুটির সাথে<br>
                    <strong>Lapse:</strong> ৩১ ডিসেম্বরে অবশিষ্ট ছুটি বাতিল
                </div>
            </div>
        </div>

        <!-- ঐচ্ছিক -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#ff9f43; --lc-bg:#fff3e5;">
                <span class="lc-icon"><i class="ti tabler-star"></i></span>
                <h6 class="lc-title">ঐচ্ছিক ছুটি</h6>
                <span class="lc-meta">৩ দিন/বছর</span>
                <p class="lc-desc">সরকার ঘোষিত ঐচ্ছিক ছুটির তালিকা থেকে নিজের পছন্দে বেছে নেওয়া যায় (ধর্মীয়/সাংস্কৃতিক)।</p>
                <div class="lc-rules">
                    <strong>Lapse:</strong> ৩১ ডিসেম্বরে — carry-forward নেই
                </div>
            </div>
        </div>

        <!-- প্রসূতি -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#ea5455; --lc-bg:#fde7e7;">
                <span class="lc-icon"><i class="ti tabler-baby-carriage"></i></span>
                <h6 class="lc-title">প্রসূতি ছুটি</h6>
                <span class="lc-meta">৬ মাস</span>
                <p class="lc-desc">নারী কর্মচারীর গর্ভাবস্থা ও সন্তান প্রসবকালীন। পূর্ণ বেতনে।</p>
                <div class="lc-rules">
                    <strong>সর্বোচ্চ:</strong> সার্ভিস জীবনে ২ বার<br>
                    <strong>সংযুক্তি:</strong> Medical certificate
                </div>
            </div>
        </div>

        <!-- সংগনিরোধ -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#7367f0; --lc-bg:#eeebfb;">
                <span class="lc-icon"><i class="ti tabler-shield"></i></span>
                <h6 class="lc-title">সংগনিরোধ (Quarantine) ছুটি</h6>
                <span class="lc-meta">পূর্ণ বেতন</span>
                <p class="lc-desc">সংক্রামক রোগের কারণে কর্তৃপক্ষ-ঘোষিত quarantine সময়ের জন্য।</p>
                <div class="lc-rules">
                    <strong>সংযুক্তি:</strong> Health authority-র আদেশ
                </div>
            </div>
        </div>

        <!-- অক্ষমতাজনিত -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#475569; --lc-bg:#eef0f5;">
                <span class="lc-icon"><i class="ti tabler-bandage"></i></span>
                <h6 class="lc-title">অক্ষমতাজনিত বিশেষ ছুটি</h6>
                <span class="lc-meta">২৮ মাস পর্যন্ত</span>
                <p class="lc-desc">কর্মস্থলে আঘাত বা পেশাগত অসুস্থতাজনিত। প্রথম ৪ মাস পূর্ণ বেতন, পরের সর্বোচ্চ ২৪ মাস অর্ধ-গড় বেতনে।</p>
                <div class="lc-rules">
                    <strong>সংযুক্তি:</strong> Medical Board সার্টিফিকেট
                </div>
            </div>
        </div>

        <!-- বিনা বেতনে -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#a78bfa; --lc-bg:#f5f3ff;">
                <span class="lc-icon"><i class="ti tabler-cash-off"></i></span>
                <h6 class="lc-title">বিনা বেতনে ছুটি</h6>
                <span class="lc-meta">বেতন ছাড়া</span>
                <p class="lc-desc">নিয়মিত ছুটি শেষে বা বিশেষ পরিস্থিতিতে নেওয়া যায়। বেতন পাবেন না।</p>
                <div class="lc-rules">
                    <strong>মোট সীমা:</strong> সার্ভিস জীবনে ৫ বছর<br>
                    <strong>প্রভাব:</strong> চাকরিকাল ও pension থেকে বাদ
                </div>
            </div>
        </div>

        <!-- অসাধারণ -->
        <div class="col-md-6 col-lg-4">
            <div class="leave-card" style="--lc-accent:#f59e0b; --lc-bg:#fef3c7;">
                <span class="lc-icon"><i class="ti tabler-alert-triangle"></i></span>
                <h6 class="lc-title">অসাধারণ ছুটি (EOL)</h6>
                <span class="lc-meta">বেতন ছাড়া</span>
                <p class="lc-desc">Regular leave শেষে বিশেষ পরিস্থিতিতে। উচ্চ কর্তৃপক্ষের অনুমোদন লাগে।</p>
                <div class="lc-rules">
                    <strong>প্রভাব:</strong> Promotion ও seniority গণনায় বাদ যায়
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Key rules to remember -->
<div class="info-section">
    <div class="info-section-title">
        <span class="num">২</span>
        <h5>গুরুত্বপূর্ণ নিয়ম যা মনে রাখতে হবে</h5>
    </div>

    <div class="rule-callout" style="--rc-accent:#ea5455; --rc-bg:#fde7e7;">
        <span class="rc-icon"><i class="ti tabler-clock-stop"></i></span>
        <div class="rc-content">
            <p class="rc-title">পূর্ণ গড় বেতনে — এক স্পেলে ৪ মাসের বেশি না</p>
            <p class="rc-text">যত balance-ই থাকুক না কেন, <strong>এক আবেদনে / একটানা</strong> সর্বোচ্চ ১২০ দিন (৪ মাস) পূর্ণ গড় বেতনে ছুটি নেওয়া যাবে। এর বেশি দরকার হলে বাকিটা অর্ধ-গড় বেতনে নিতে হবে অথবা পৃথক আবেদন করতে হবে। এটা <em>প্রতি স্পেলের</em> limit — চাকরি জীবনে আপনি একাধিকবার এই ৪-মাসী স্পেল নিতে পারবেন (প্রতিটার মাঝে duty period থাকতে হবে)।</p>
        </div>
    </div>

    <div class="rule-callout" style="--rc-accent:#28c76f; --rc-bg:#e6f8ee;">
        <span class="rc-icon"><i class="ti tabler-calendar-x"></i></span>
        <div class="rc-content">
            <p class="rc-title">নৈমিত্তিক — একটানা ১০ দিন, অন্য ছুটির সাথে মিশানো যাবে না</p>
            <p class="rc-text">নৈমিত্তিক ছুটি সর্বোচ্চ ১০ দিন একটানা নেওয়া যায়। অন্য কোনো ধরনের ছুটির সাথে এক আবেদনে বা পাশাপাশি (consecutive) মিশানো যাবে না — আলাদা আবেদন করতে হবে।</p>
        </div>
    </div>

    <div class="rule-callout" style="--rc-accent:#ff9f43; --rc-bg:#fff3e5;">
        <span class="rc-icon"><i class="ti tabler-archive"></i></span>
        <div class="rc-content">
            <p class="rc-title">অবসরে ১৮ মাসের বেশি ছুটি cash হবে না</p>
            <p class="rc-text">আপনার পূর্ণ গড় বেতনে যত ছুটিই জমা থাকুক, অবসর গ্রহণের সময় সর্বোচ্চ ১৮ মাসের সমপরিমাণ বেতন encash পাবেন। বাকিটা lapse। তাই ১৮ মাস-এর বেশি জমে যাওয়ার আগে ভোগ বা encashable পথ ভাবা উচিত।</p>
        </div>
    </div>

    <div class="rule-callout" style="--rc-accent:#696cff; --rc-bg:#eef0ff;">
        <span class="rc-icon"><i class="ti tabler-calendar-off"></i></span>
        <div class="rc-content">
            <p class="rc-title">ক্যালেন্ডার বছরে নৈমিত্তিক ও ঐচ্ছিক lapse হয়</p>
            <p class="rc-text">৩১ ডিসেম্বরে নৈমিত্তিক ছুটি (২০ দিন/বছর) ও ঐচ্ছিক ছুটি (৩ দিন/বছর) যা অব্যবহৃত থাকে — তা পরের বছরে carry-forward হয় না। বছরের মধ্যে ব্যবহার না করলে চলে যায়।</p>
        </div>
    </div>
</div>

<!-- Section 3: Accrual / balance math -->
<div class="info-section">
    <div class="info-section-title">
        <span class="num">৩</span>
        <h5>ছুটি জমার নিয়ম (Accrual)</h5>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="leave-card" style="--lc-accent:#696cff; --lc-bg:#eef0ff;">
                <span class="lc-icon"><i class="ti tabler-trending-up"></i></span>
                <h6 class="lc-title">পূর্ণ গড় বেতনে জমার হিসাব</h6>
                <p class="lc-desc">আপনার <strong>প্রকৃত চাকরিকালের প্রতি ১১ দিনে ১ দিন</strong> পূর্ণ গড় বেতনে ছুটি জমা হয়।</p>
                <div class="formula-box">পূর্ণ গড় বেতনে balance = প্রকৃত চাকরিকাল ÷ ১১</div>
                <p class="lc-desc" style="font-size:0.78rem; color:#64748b;">প্রকৃত চাকরিকাল = মোট চাকরির সময় – (বিনা বেতনে + অসাধারণ + কর্তনহীন ছুটি)</p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="leave-card" style="--lc-accent:#00cfe8; --lc-bg:#e0f9fc;">
                <span class="lc-icon"><i class="ti tabler-chart-bar"></i></span>
                <h6 class="lc-title">অর্ধ-গড় বেতনে জমার হিসাব</h6>
                <p class="lc-desc">প্রকৃত চাকরিকালের <strong>প্রতি ১২ দিনে ১ দিন</strong> অর্ধ-গড় বেতনে ছুটি জমা হয়।</p>
                <div class="formula-box">অর্ধ-গড় বেতনে balance = প্রকৃত চাকরিকাল ÷ ১২</div>
                <p class="lc-desc" style="font-size:0.78rem; color:#64748b;">Medical ground-এ <strong>২ দিন অর্ধ-গড় = ১ দিন পূর্ণ গড়</strong> হিসাবে রূপান্তর করা যায়।</p>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Attachment requirements -->
<div class="info-section">
    <div class="info-section-title">
        <span class="num">৪</span>
        <h5>সংযুক্তি কখন বাধ্যতামূলক</h5>
    </div>

    <div class="row g-2">
        <div class="col-md-6">
            <div class="rule-callout" style="--rc-accent:#ea5455; --rc-bg:#fde7e7;">
                <span class="rc-icon"><i class="ti tabler-stethoscope"></i></span>
                <div class="rc-content">
                    <p class="rc-title">অর্ধ-গড় বেতনে ও প্রসূতি</p>
                    <p class="rc-text">Registered ডাক্তারের <strong>Medical certificate</strong> বাধ্যতামূলক। প্রসূতি ছুটির ক্ষেত্রে গর্ভাবস্থা/প্রসব সম্পর্কিত certificate লাগবে।</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="rule-callout" style="--rc-accent:#7367f0; --rc-bg:#eeebfb;">
                <span class="rc-icon"><i class="ti tabler-shield"></i></span>
                <div class="rc-content">
                    <p class="rc-title">সংগনিরোধ (Quarantine)</p>
                    <p class="rc-text">Health authority-র <strong>quarantine আদেশ/সার্টিফিকেট</strong> বাধ্যতামূলক।</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="rule-callout" style="--rc-accent:#475569; --rc-bg:#eef0f5;">
                <span class="rc-icon"><i class="ti tabler-bandage"></i></span>
                <div class="rc-content">
                    <p class="rc-title">অক্ষমতাজনিত বিশেষ ছুটি</p>
                    <p class="rc-text"><strong>Medical Board সার্টিফিকেট</strong> এবং on-duty injury report বাধ্যতামূলক।</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="rule-callout" style="--rc-accent:#28c76f; --rc-bg:#e6f8ee;">
                <span class="rc-icon"><i class="ti tabler-check"></i></span>
                <div class="rc-content">
                    <p class="rc-title">অন্যান্য ক্ষেত্রে</p>
                    <p class="rc-text">পূর্ণ গড়, নৈমিত্তিক, ঐচ্ছিক, বিনা বেতনে ছুটির জন্য সাধারণত সংযুক্তি লাগে না — তবে কারণ অনুযায়ী কর্তৃপক্ষ চাইতে পারেন।</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 5: FAQ -->
<div class="info-section">
    <div class="info-section-title">
        <span class="num">৫</span>
        <h5>প্রায়শই জিজ্ঞাসিত প্রশ্ন (FAQ)</h5>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>আমি একসাথে সর্বোচ্চ কত দিন পূর্ণ বেতনে ছুটি নিতে পারি?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            <strong>এক আবেদনে / একটানা</strong> সর্বোচ্চ <strong>১২০ দিন (৪ মাস)</strong> পূর্ণ গড় বেতনে নিতে পারবেন। এর বেশি দরকার হলে বাকি অংশ অর্ধ-গড় বেতনে নিতে হবে অথবা পরে আলাদা আবেদন করতে হবে। যত balance-ই থাকুক, একটানা ৪ মাসের বেশি অনুমোদন হবে না।<br><br>
            <em>মনে রাখুন:</em> এটা <strong>চাকরি জীবনের মোট না</strong> — প্রতি স্পেলের limit। duty-তে ফিরে গিয়ে কিছুদিন পর আপনি আরেকটা ৪-মাসী স্পেল নিতে পারবেন (যদি balance থাকে)।

            <div class="example-box">
                <div class="ex-title"><i class="ti tabler-bulb"></i> উদাহরণ — জনাব করিম সাহেব</div>
                <div class="ex-row"><div class="ex-when">২০১৫ সাল</div><div class="ex-what">অসুস্থতার কারণে ১২০ দিন পূর্ণ গড় বেতনে ছুটি নিলেন → ফিরে এসে duty শুরু করলেন।</div></div>
                <div class="ex-row"><div class="ex-when">২০১৯ সাল</div><div class="ex-what">পারিবারিক প্রয়োজনে আরো ১২০ দিন পূর্ণ গড় বেতনে ছুটি নিলেন (balance ছিল) → আবার duty।</div></div>
                <div class="ex-row"><div class="ex-when">২০২৪ সাল</div><div class="ex-what">আবারো ৯০ দিন পূর্ণ গড় বেতনে ছুটি নিলেন।</div></div>
                <div class="ex-summary">✓ মোট ৩৩০ দিন পূর্ণ গড় বেতনে ছুটি — সবই বৈধ, কারণ <em>প্রতিটা স্পেল</em> ১২০ দিনের নিচে ছিল।</div>
            </div>

            <div class="example-box" style="background:#fef2f2; border-left-color:#ea5455;">
                <div class="ex-title" style="color:#991b1b;"><i class="ti tabler-x"></i> ভুল উদাহরণ — যা অনুমোদন হবে না</div>
                <div class="ex-row" style="border-bottom-color:#fecaca;"><div class="ex-when" style="color:#b91c1c;">আবেদন</div><div class="ex-what">একটানা ১৮০ দিন পূর্ণ গড় বেতনে ছুটি চাইলেন (balance ৩০০ দিন আছে)।</div></div>
                <div class="ex-summary" style="color:#991b1b; border-top-color:#ea5455;">✗ অনুমোদন হবে না। সমাধান: ১২০ দিন পূর্ণ গড় + ৬০ দিন অর্ধ-গড় (একই আবেদনে multi-segment), অথবা পরে আলাদা আবেদন।</div>
            </div>
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>নৈমিত্তিক ছুটি কি জমা হয়? পরের বছরে যাবে?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            না — নৈমিত্তিক ছুটি প্রতি ক্যালেন্ডার বছরে <strong>২০ দিন</strong> বরাদ্দ। বছরের শেষে (৩১ ডিসেম্বর) যা অব্যবহৃত থাকে তা <strong>lapse</strong> হয়ে যায়, পরের বছরে carry-forward হয় না। তাই বছরের মধ্যেই ব্যবহার করা ভালো।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>অবসরের সময় কত দিন ছুটি cash পাব?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            অবসর গ্রহণের সময় আপনার পূর্ণ গড় বেতনে balance থেকে সর্বোচ্চ <strong>১৮ মাস (৫৪০ দিন)</strong> encash করা যায়। হিসাব: শেষ-drawn মূল বেতন × ১৮। যদি ১৮ মাসের বেশি জমা থাকে, অতিরিক্তটা lapse হবে।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>একই আবেদনে কি কয়েক ধরনের ছুটি যোগ করা যায়?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            হ্যাঁ, এই system-এ আপনি একই আবেদনে <strong>একাধিক ধরনের ছুটি</strong> যোগ করতে পারবেন (যেমন: ৪ মাস পূর্ণ গড় + ২ মাস অর্ধ-গড়)। কিন্তু <strong>নৈমিত্তিক ছুটি</strong> কখনই অন্য ধরনের সাথে মিশানো যাবে না — সেটা সবসময় আলাদা আবেদন।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>অর্ধ-গড় বেতনে ছুটিকে পূর্ণ-বেতনে কি করা যায়?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            হ্যাঁ — চিকিৎসার কারণে (medical certificate সহ) <strong>২ দিন অর্ধ-গড়</strong> ছুটিকে <strong>১ দিন পূর্ণ গড় বেতনে</strong> রূপান্তর (commute) করা যায়। এটা মেডিকেল board অনুমোদন করলে কার্যকর।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>প্রসূতি ছুটি কতবার পাবো?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            সার্ভিস জীবনে <strong>সর্বোচ্চ ২ বার</strong> প্রসূতি ছুটি (৬ মাস করে) পাবেন। এটা পূর্ণ বেতনে এবং আপনার নিয়মিত balance থেকে কাটা হবে না — পৃথক বরাদ্দ।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>বিনা বেতনে ছুটি কি অসীম নেওয়া যায়?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            না — সার্ভিস জীবনে aggregate (মোট) <strong>৫ বছর</strong> পর্যন্ত বিনা বেতনে ছুটি নেওয়া যায়। এই সময়ে বেতন পাবেন না, প্রকৃত চাকরিকাল থেকে বাদ যাবে, এবং promotion/seniority গণনায় গণ্য হবে না।
        </div>
    </div>

    <div class="faq-card" onclick="toggleFaq(this)">
        <div class="faq-q">
            <span><i class="ti tabler-help me-2"></i>ছুটি অনুমোদিত হওয়ার পর সময়মতো যোগদান না করলে?</span>
            <i class="ti tabler-chevron-down toggle-icon"></i>
        </div>
        <div class="faq-a">
            অনুমোদিত ছুটির শেষ তারিখের পর কর্মস্থলে যোগদান বাধ্যতামূলক। বর্ধিত ছুটি প্রয়োজন হলে <strong>ছুটি শেষের আগেই</strong> বর্ধিত আবেদন জমা দিতে হবে। অনুমতি ছাড়া অনুপস্থিতি disciplinary action-এর কারণ হতে পারে।
        </div>
    </div>
</div>

<!-- Footer note -->
<div class="alert alert-info mb-4" style="border-left: 4px solid #696cff;">
    <i class="ti tabler-info-circle me-2"></i>
    <strong>মনে রাখুন:</strong> এই পৃষ্ঠাটি দ্রুত রেফারেন্সের জন্য — সম্পূর্ণ বিধান জানতে সরকারি চাকরি বিধিমালা (BSR) এবং সংশ্লিষ্ট পরিপত্র দেখতে হবে। কোনো confusion থাকলে HR বিভাগের সাথে যোগাযোগ করুন।
</div>

<script>
function toggleFaq(el) {
    el.classList.toggle('open');
}
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
