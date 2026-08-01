<?php
require_once(__DIR__ . '/../../includes/header_vuexy.php');
require_once(LIBRARY_PATH . '/number_converter.php');
$menuslug = htmlspecialchars($_GET['menuslug'] ?? 'notifications-all');
?>

<style>
.notif-page-wrap { max-width: 860px; margin: 0 auto; }

.notif-scope-tabs {
    display: inline-flex;
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 10px;
    padding: 4px;
    gap: 4px;
    box-shadow: 0 1px 3px rgba(16,24,40,.04);
}
.notif-scope-tab {
    padding: 7px 16px;
    font-size: 0.83rem;
    font-weight: 500;
    color: #64708b;
    background: transparent;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    transition: background .15s, color .15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.notif-scope-tab:hover { background: #f5f7fa; color: #2c2e3a; }
.notif-scope-tab.is-active { background: #4338ca; color: #fff; }
.notif-scope-tab .cnt {
    font-size: 0.7rem;
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(255,255,255,.25);
    font-weight: 600;
}
.notif-scope-tab:not(.is-active) .cnt { background: #eef2ff; color: #4338ca; }

.notif-list-card {
    background: #fff;
    border: 1px solid #eef0f5;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(16,24,40,.04);
}

.notif-list-row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    border-bottom: 1px solid #f3f4f8;
    text-decoration: none;
    color: inherit;
    position: relative;
    transition: background .15s;
}
.notif-list-row:hover { background: #fafbff; color: inherit; }
.notif-list-row:last-child { border-bottom: 0; }
.notif-list-row.is-unread { background: #fafbff; }
.notif-list-row.is-unread .notif-list-msg { color: #1e293b; font-weight: 500; }

.notif-list-icon {
    flex-shrink: 0;
    width: 42px; height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
}
.notif-list-body { flex: 1; min-width: 0; }
.notif-list-msg  { font-size: 0.9rem; color: #3A3D53; line-height: 1.5; }
.notif-list-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
.notif-list-unread-dot { position: absolute; top: 22px; right: 18px; width: 8px; height: 8px; border-radius: 50%; background: #4338ca; }

.notif-empty {
    padding: 64px 20px;
    text-align: center;
    color: #94a3b8;
}
.notif-empty i { font-size: 3rem; display: block; margin-bottom: 12px; color: #dee2e6; }

.notif-pager {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #fafbfd;
    border-top: 1px solid #f0f2f5;
    font-size: 0.82rem;
    color: #64708b;
}
.notif-pager button {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all .15s;
}
.notif-pager button:hover:not(:disabled) { border-color: #4338ca; color: #4338ca; }
.notif-pager button:disabled { opacity: .5; cursor: not-allowed; }
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 notif-page-wrap">
    <div>
        <h4 class="fw-bold mb-0"><i class="ti tabler-bell me-2 text-primary"></i>নোটিফিকেশন</h4>
        <div class="text-muted small mt-1">সমস্ত অ্যালার্ট ও সিস্টেম মেসেজ</div>
    </div>
    <button type="button" onClick="window.history.go(-1); return false;" class="btn btn-sm btn-label-secondary">
        <i class="ti tabler-arrow-left me-1"></i>পূর্ববর্তী
    </button>
</div>

<div class="notif-page-wrap">
    <!-- Scope tabs -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="notif-scope-tabs">
            <button type="button" class="notif-scope-tab is-active" data-scope="unread">
                <i class="ti tabler-mail"></i> অপঠিত <span class="cnt" id="cnt-unread">০</span>
            </button>
            <button type="button" class="notif-scope-tab" data-scope="all">
                <i class="ti tabler-inbox"></i> সব
            </button>
            <button type="button" class="notif-scope-tab" data-scope="important">
                <i class="ti tabler-flag"></i> গুরুত্বপূর্ণ
            </button>
        </div>
        <button type="button" id="markAllReadBtn" class="btn btn-sm btn-label-primary">
            <i class="ti tabler-checks me-1"></i>সব পড়া হয়েছে
        </button>
    </div>

    <!-- List -->
    <div class="notif-list-card">
        <div id="notifList">
            <div class="notif-empty">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-3">লোড হচ্ছে...</div>
            </div>
        </div>
        <div class="notif-pager" id="notifPager" style="display:none;">
            <div id="pagerInfo"></div>
            <div class="d-flex gap-2">
                <button type="button" id="prevBtn"><i class="ti tabler-chevron-left"></i></button>
                <button type="button" id="nextBtn"><i class="ti tabler-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const PAGE_SIZE = 25;
    let scope  = 'unread';
    let offset = 0;
    let total  = 0;

    const listEl   = document.getElementById('notifList');
    const pagerEl  = document.getElementById('notifPager');
    const infoEl   = document.getElementById('pagerInfo');
    const prevBtn  = document.getElementById('prevBtn');
    const nextBtn  = document.getElementById('nextBtn');
    const cntUnread = document.getElementById('cnt-unread');

    function toBn(n) { return String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]); }

    // Normalize a stored notification link (usually a project-relative path
    // like "views/leave/…") into an absolute URL under BASE_URL, so links
    // work no matter where the notifications page itself lives.
    function resolveLink(link) {
        if (!link || link === 'javascript:void(0);') return 'javascript:void(0);';
        if (/^(https?:)?\/\//i.test(link) || link.startsWith('javascript:')) return link;
        const base = '<?php echo rtrim($baseURL, "/"); ?>';
        return base + '/' + link.replace(/^\/+/, '');
    }

    function iconFor(item) {
        const t = String(item.type || '').toLowerCase();
        const m = (item.message || '').toLowerCase();
        if (t.includes('reject') || m.includes('প্রত্যাখ্যাত'))       return {c:'tabler-x',                bg:'#fee2e2', col:'#dc2626'};
        if (t.includes('office') || m.includes('অফিস আদেশ'))          return {c:'tabler-file-certificate', bg:'#fff3cd', col:'#f59e0b'};
        if (t.includes('join')   || m.includes('যোগদান'))              return {c:'tabler-user-check',       bg:'#e0f2fe', col:'#0ea5e9'};
        if (t.includes('opa')    || t.includes('optional') || m.includes('ঐচ্ছিক')) return {c:'tabler-calendar-star', bg:'#f3e8ff', col:'#9333ea'};
        if (t.includes('leave')  || m.includes('ছুটি'))                return {c:'tabler-calendar-event',   bg:'#e8f5e9', col:'#28a745'};
        if (t.includes('addition') || t.includes('deduction') || m.includes('সংযোজন') || m.includes('কর্তন')) return {c:'tabler-adjustments-alt', bg:'#eef2ff', col:'#4338ca'};
        return {c:'tabler-bell', bg:'#eef0ff', col:'#667eea'};
    }

    function render(items) {
        if (!items || items.length === 0) {
            listEl.innerHTML = `
                <div class="notif-empty">
                    <i class="ti tabler-bell-off"></i>
                    <div>এই ভিউতে কোনো নোটিফিকেশন নেই</div>
                </div>`;
            pagerEl.style.display = 'none';
            return;
        }

        listEl.innerHTML = items.map(item => {
            const ic = iconFor(item);
            const href = resolveLink(item.link);
            return `
                <a href="${href}" class="notif-list-row ${item.isRead ? '' : 'is-unread'}" data-id="${item.id}">
                    <div class="notif-list-icon" style="background:${ic.bg}; color:${ic.col};">
                        <i class="ti ${ic.c}"></i>
                    </div>
                    <div class="notif-list-body">
                        <div class="notif-list-msg">${item.message || ''}</div>
                        <div class="notif-list-meta">
                            <i class="ti tabler-clock"></i>
                            ${item.dateHuman || item.dateTime || ''}
                        </div>
                    </div>
                    ${item.isRead ? '' : '<span class="notif-list-unread-dot"></span>'}
                </a>`;
        }).join('');

        // Wire click-to-mark
        listEl.querySelectorAll('.notif-list-row').forEach(el => {
            const id = el.dataset.id;
            const href = el.getAttribute('href');
            const isUnread = el.classList.contains('is-unread');
            el.addEventListener('click', (ev) => {
                if (id && isUnread) {
                    try {
                        const body = new URLSearchParams({ id });
                        navigator.sendBeacon
                            ? navigator.sendBeacon('<?php echo $baseURL; ?>api/notifications/mark-read.php', body)
                            : fetch('<?php echo $baseURL; ?>api/notifications/mark-read.php', {
                                method: 'POST', credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: body.toString(), keepalive: true });
                    } catch (e) {}
                }
                if (!href || href === 'javascript:void(0);') ev.preventDefault();
            });
        });

        // Pager
        pagerEl.style.display = 'flex';
        const from = offset + 1;
        const to   = Math.min(offset + items.length, total);
        infoEl.textContent = toBn(from) + '–' + toBn(to) + ' এর মধ্যে ' + toBn(total) + ' টি';
        prevBtn.disabled = offset === 0;
        nextBtn.disabled = offset + items.length >= total;
    }

    function load() {
        listEl.innerHTML = '<div class="notif-empty"><div class="spinner-border text-primary" role="status"></div><div class="mt-3">লোড হচ্ছে...</div></div>';
        const url = '<?php echo $baseURL; ?>api/notifications/fetch.php?scope=' + scope + '&limit=' + PAGE_SIZE + '&offset=' + offset;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(resp => {
                if (!resp || resp.status !== 1) {
                    listEl.innerHTML = '<div class="notif-empty"><i class="ti tabler-alert-circle"></i><div>লোড ব্যর্থ</div></div>';
                    return;
                }
                total = resp.total || 0;
                cntUnread.textContent = toBn(resp.unreadCount || 0);
                render(resp.items || []);
            })
            .catch(() => {
                listEl.innerHTML = '<div class="notif-empty"><i class="ti tabler-alert-circle"></i><div>সার্ভার সংযোগ ব্যর্থ</div></div>';
            });
    }

    document.querySelectorAll('.notif-scope-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.notif-scope-tab').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            scope  = btn.dataset.scope;
            offset = 0;
            load();
        });
    });

    prevBtn.addEventListener('click', () => { if (offset > 0) { offset = Math.max(0, offset - PAGE_SIZE); load(); } });
    nextBtn.addEventListener('click', () => { if (offset + PAGE_SIZE < total) { offset += PAGE_SIZE; load(); } });

    document.getElementById('markAllReadBtn').addEventListener('click', function() {
        this.disabled = true;
        fetch('<?php echo $baseURL; ?>api/notifications/mark-all-read.php', {
            method: 'POST', credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
            this.disabled = false;
            if (res && res.status === 1) load();
        })
        .catch(() => { this.disabled = false; });
    });

    load();
})();
</script>

<?php require_once(__DIR__ . '/../../includes/footer_vuexy.php'); ?>
