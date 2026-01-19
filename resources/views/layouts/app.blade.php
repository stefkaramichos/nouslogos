<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Booking App')</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- ✅ Apply collapsed early to avoid flicker -->
    <script>
    (function(){
        try{
            if (localStorage.getItem('sidebar_mode') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        }catch(e){}
    })();
    </script>

    <style>
        body { min-height: 100vh; overflow-x: hidden; }
        .sidebar { min-height: 100vh; }
        .sidebar .nav-link.active { background-color: #0d6efd; color: #fff !important; }
        .sidebar .nav-link { color: #333; }
        .sidebar{z-index: 200;}

        @media (max-width: 767.98px) {
            .sidebar { min-height: auto; }
        }

        /* small icon polish */
        .icon-actions .btn i { font-size: 1.1rem; }
        .icon-actions .btn:hover i { transform: scale(1.1); transition: 0.15s ease; }

        /* notifications dropdown */
        .notif-dropdown {
            display:none;
            position:fixed;
            left: 30px;
            bottom: 100px;
            width:234px;
            z-index:2000;
        }
        @media (max-width: 767.98px) {
            .notif-dropdown { width: 320px; }
        }
        .notif-list {
            max-height: 260px;
            overflow: auto;
        }

        /* =========================
           2 MODES SIDEBAR (NORMAL / COLLAPSED)
           ========================= */

        /* toggle button */
        .sidebar-toggle {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 10px;
            background: #fff;
        }

        /* icons + labels for desktop sidebar items */
        .nav-icon {
            width: 1.25rem;
            text-align: center;
            display: inline-block;
        }
        .nav-label { display: inline; }

        /* logo */
        .sidebar-logo {
            height: 32px;
            width: auto;
            display: block;
        }

        /* NORMAL MODE (default): keep Bootstrap cols as-is */
        /* Collapsed mode overrides below */

        /* COLLAPSED MODE: make sidebar one column */
        .sidebar-collapsed .sidebar {
            padding-left: .25rem !important;
            padding-right: .25rem !important;
        }

        .sidebar-collapsed .sidebar .nav-link {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0 !important;
            padding-left: .5rem;
            padding-right: .5rem;
        }

        .sidebar-collapsed .sidebar .nav-label { display: none; }

        .sidebar-collapsed .sidebar .logo-img {
            display:flex;
            justify-content:center;
            align-items:center;
            padding-left: .25rem !important;
            padding-right: .25rem !important;
        }
        .sidebar-collapsed .sidebar .sidebar-logo {
            height: 22px;
        }

        /* bottom actions become vertical in collapsed mode */
        .sidebar-collapsed #sidebarBottomActions {
            flex-direction: column !important;
            align-items: center !important;
            gap: .5rem !important;
        }
        .sidebar-collapsed #sidebarBottomActions .btn {
            width: 44px;
            padding-left: 0;
            padding-right: 0;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        /* Bootstrap grid overrides for collapsed widths */
        /* Sidebar col becomes very small; main becomes wider */
        .sidebar-collapsed #desktopSidebarCol {
            width: 72px !important;
            flex: 0 0 72px !important;
            max-width: 72px !important;
        }
        .sidebar-collapsed #desktopMainCol {
            width: calc(100% - 72px) !important;
            flex: 0 0 calc(100% - 72px) !important;
            max-width: calc(100% - 72px) !important;
        }
    </style>
</head>
<body>
@php $user = Auth::user(); @endphp

{{-- MOBILE NAVBAR + OFFCANVAS (visible only on < md) --}}
<nav class="navbar navbar-expand-md navbar-light bg-light d-md-none">
    <div class="container-fluid logo-img ">
        <a class="navbar-brand fw-bold" href="#">
            <img src="{{ asset('images/logo.png') }}" alt="Booking App" height="32">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"
                aria-controls="mobileSidebar" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="mobileSidebarLabel">Πάνελ Διαχείρισης</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column justify-content-between">
        <div>
            <ul class="nav flex-column mb-3">
                @if($user && $user->role !== 'therapist')
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('customers.*')) active @endif"
                           href="{{ route('customers.index') }}">
                            👤 Περιστατικά
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('professionals.*')) active @endif"
                           href="{{ route('professionals.index') }}">
                            💼 Θεραπευτές
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('appointments.*')) active @endif"
                           href="{{ route('appointments.index') }}">
                            📅 Ραντεβού
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('expenses.*')) active @endif"
                           href="{{ route('expenses.index') }}">
                            💸 Έξοδα
                        </a>
                    </li>
                    @if(Auth::check() && Auth::user()->role === 'owner')
                        <li class="nav-item mb-1">
                            <a class="nav-link @if(request()->routeIs('settlements.*')) active @endif"
                               href="{{ route('settlements.index') }}">
                                📑 Εκκαθάριση
                            </a>
                        </li>
                    @endif
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('price_items.*')) active @endif"
                           href="{{ route('price_items.index') }}">
                            🏷️ Τιμοκατάλογος
                        </a>
                    </li>
                @endif

                @if($user && $user->role === 'therapist')
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('therapist_appointments.*')) active @endif"
                           href="{{ route('therapist_appointments.index') }}">
                            🗓 Ραντεβού θεραπευτών
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        {{-- BOTTOM ACTIONS (MOBILE) --}}
        <div class="mt-3 pt-3">
            {{-- Owner: Ραντεβού θεραπευτών --}}
            @if($user && $user->role === 'owner')
                <a class="btn btn-outline-primary w-100 mb-2 @if(request()->routeIs('therapist_appointments.*')) active @endif"
                   href="{{ route('therapist_appointments.index') }}">
                    🗓 Ραντεβού θεραπευτών
                </a>
                <hr class="my-2">
            @endif

            <div class="d-flex justify-content-start gap-3 icon-actions">
                @if($user && in_array($user->role, ['owner', 'grammatia']))
                    <a href="{{ route('appointments.recycle') }}" class="btn btn-outline-secondary" title="Recycle Ραντεβού">
                        <i class="bi bi-trash"></i>
                    </a>

                    <a href="{{ route('documents.index') }}" class="btn btn-outline-success" title="Αρχεία">
                        <i class="bi bi-folder2-open"></i>
                    </a>

                    {{-- Notifications Bell (MOBILE) --}}
                    <div class="position-relative">
                        <button type="button" id="notifBellBtnMobile" class="btn btn-outline-primary position-relative" title="Ειδοποιήσεις">
                            <i class="bi bi-bell"></i>
                            <span id="notifBadgeMobile"
                                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                  style="display:none; font-size:.70rem;">0</span>
                        </button>

                        <div id="notifDropdownMobile" class="card shadow notif-dropdown">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <strong>Ειδοποιήσεις</strong>
                                <a class="small text-decoration-none" href="{{ route('notifications.index') }}">Όλες</a>
                            </div>

                            <div id="notifListMobile" class="list-group list-group-flush notif-list"></div>

                            <div class="card-footer py-2 text-end">
                                <button type="button" id="notifCloseMobile" class="btn btn-sm btn-outline-secondary">Κλείσιμο</button>
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-outline-danger" title="Αποσύνδεση">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">

        {{-- DESKTOP SIDEBAR --}}
        <nav id="desktopSidebarCol" class="col-md-2 col-lg-2 d-none d-md-block bg-light sidebar py-3">
            <div class="position-sticky d-flex flex-column justify-content-between h-100">
                <div>
                    <div class="px-3 logo-img mb-3 d-flex justify-content-between align-items-center">
                        <a class="navbar-brand fw-bold m-0" href="#">
                            <img class="sidebar-logo" src="{{ asset('images/logo.png') }}" alt="Booking App">
                        </a>

                        {{-- ✅ Toggle button (desktop only) --}}
                        <button type="button" id="sidebarToggleBtn" class="sidebar-toggle" title="Μίκρυνε/Άνοιξε">
                            <i id="sidebarToggleIcon" class="bi"></i>
                        </button>
                    </div>
                    <hr class="mt-0">

                    <ul class="nav flex-column px-2">
                        @if($user && $user->role !== 'therapist')
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('customers.*')) active @endif"
                                   href="{{ route('customers.index') }}">
                                    <span class="nav-icon">👤</span>
                                    <span class="nav-label">Περιστατικά</span>
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('professionals.*')) active @endif"
                                   href="{{ route('professionals.index') }}">
                                    <span class="nav-icon">💼</span>
                                    <span class="nav-label">Θεραπευτές</span>
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('appointments.*')) active @endif"
                                   href="{{ route('appointments.index') }}">
                                    <span class="nav-icon">📅</span>
                                    <span class="nav-label">Ραντεβού</span>
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('expenses.*')) active @endif"
                                   href="{{ route('expenses.index') }}">
                                    <span class="nav-icon">💸</span>
                                    <span class="nav-label">Έξοδα</span>
                                </a>
                            </li>
                            @if(Auth::check() && Auth::user()->role === 'owner')
                                <li class="nav-item mb-1">
                                    <a class="nav-link @if(request()->routeIs('settlements.*')) active @endif"
                                       href="{{ route('settlements.index') }}">
                                        <span class="nav-icon">📑</span>
                                        <span class="nav-label">Εκκαθάριση</span>
                                    </a>
                                </li>
                            @endif
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('price_items.*')) active @endif"
                                   href="{{ route('price_items.index') }}">
                                    <span class="nav-icon">🏷️</span>
                                    <span class="nav-label">Τιμοκατάλογος</span>
                                </a>
                            </li>
                        @endif

                        @if($user && $user->role === 'therapist')
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('therapist_appointments.*')) active @endif"
                                   href="{{ route('therapist_appointments.index') }}">
                                    <span class="nav-icon">🗓</span>
                                    <span class="nav-label">Ραντεβού θεραπευτών</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- BOTTOM ACTIONS (DESKTOP) --}}
                <div class="px-2 mt-3 pt-3">
                    @if($user && $user->role === 'owner')
                        <a class="btn btn-outline-primary w-100 mb-2 @if(request()->routeIs('therapist_appointments.*')) active @endif"
                           href="{{ route('therapist_appointments.index') }}">
                            🗓 Ραντεβού θεραπευτών
                        </a>
                        <hr class="my-2">
                    @endif

                    <div class="d-flex justify-content-start gap-3 icon-actions" id="sidebarBottomActions">
                        @if($user && in_array($user->role, ['owner', 'grammatia']))
                            <a href="{{ route('appointments.recycle') }}" class="btn btn-outline-secondary" title="Recycle Ραντεβού">
                                <i class="bi bi-trash"></i>
                            </a>

                            <a href="{{ route('documents.index') }}" class="btn btn-outline-success" title="Αρχεία">
                                <i class="bi bi-folder2-open"></i>
                            </a>

                            {{-- Notifications Bell (DESKTOP) --}}
                            <div class="position-relative">
                                <button type="button" id="notifBellBtn" class="btn btn-outline-primary position-relative" title="Ειδοποιήσεις">
                                    <i class="bi bi-bell"></i>
                                    <span id="notifBadge"
                                          class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                          style="display:none; font-size:.70rem;">0</span>
                                </button>

                                <div id="notifDropdown" class="card shadow notif-dropdown">
                                    <div class="card-header d-flex justify-content-between align-items-center py-2">
                                        <strong>Ειδοποιήσεις</strong>
                                        <a class="small text-decoration-none" href="{{ route('notifications.index') }}">Όλες</a>
                                    </div>

                                    <div id="notifList" class="list-group list-group-flush notif-list"></div>

                                    <div class="card-footer py-2 text-end">
                                        <button type="button" id="notifClose" class="btn btn-sm btn-outline-secondary">Κλείσιμο</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button class="btn btn-outline-danger" title="Αποσύνδεση">
                                <i class="bi bi-box-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        {{-- MAIN CONTENT --}}
        <main id="desktopMainCol" class="col-12 col-md-10 ms-sm-auto col-lg-10 px-3 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                <h2 class="h3 mb-0" style="color:#b21691">@yield('title', 'Επισκόπηση')</h2>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- ✅ Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- Flatpickr --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/el.js"></script>

{{-- View scripts --}}
@stack('scripts')

{{-- ✅ Notifications Badge + Dropdown Script --}}
@if(Auth::check())
<script>
(function () {
    async function fetchDue() {
        try {
            const res = await fetch("{{ route('notifications.due') }}", {
                headers: { "Accept": "application/json" }
            });
            if (!res.ok) return [];
            return await res.json();
        } catch (e) {
            return [];
        }
    }

    async function markRead(id) {
        await fetch("{{ url('/notifications') }}/" + id + "/read", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "Accept": "application/json"
            }
        });
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, m => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    }

    function wireInstance(prefix) {
        const bellBtn  = document.getElementById(prefix + 'BellBtn');
        const badgeEl  = document.getElementById(prefix + 'Badge');
        const dropdown = document.getElementById(prefix + 'Dropdown');
        const listEl   = document.getElementById(prefix + 'List');
        const closeBtn = document.getElementById(prefix + 'Close');

        if (!bellBtn || !badgeEl || !dropdown || !listEl || !closeBtn) return null;

        function renderList(items) {
            if (!items.length) {
                listEl.innerHTML = `<div class="p-3 text-muted small">Δεν υπάρχουν ενεργές ειδοποιήσεις.</div>`;
                return;
            }

            listEl.innerHTML = items.map(n => {
                const note = escapeHtml(n.note);
                const when = escapeHtml(n.notify_at_text || n.notify_at || '');
                return `
                    <button type="button" class="list-group-item list-group-item-action" data-id="${n.id}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2" style="white-space:pre-wrap">${note}</div>
                            <small class="text-muted">${when}</small>
                        </div>
                        <div class="mt-2 text-end">
                            <span class="ms-2 text-primary small">Κλικ για να “διαβαστεί”</span>
                        </div>
                    </button>
                `;
            }).join('');

            listEl.querySelectorAll('[data-id]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    await markRead(id);
                    await refreshAll();
                });
            });
        }

        function close() { dropdown.style.display = 'none'; }
        function toggle() { dropdown.style.display = (dropdown.style.display === 'none' || !dropdown.style.display) ? 'block' : 'none'; }

        bellBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            await refreshAll();
            toggle();
        });

        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            close();
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
                close();
            }
        });

        return {
            setBadge(count) {
                if (count > 0) {
                    badgeEl.textContent = count;
                    badgeEl.style.display = 'inline-block';
                } else {
                    badgeEl.style.display = 'none';
                }
            },
            renderList,
        };
    }

    const desktop = wireInstance('notif');
    const mobile  = wireInstance('notifMobile');

    async function refreshAll() {
        const due = await fetchDue();
        const count = due.length;

        if (desktop) { desktop.setBadge(count); desktop.renderList(due); }
        if (mobile)  { mobile.setBadge(count);  mobile.renderList(due);  }
    }

    refreshAll();
    setInterval(refreshAll, 60000);
})();
</script>
@endif

{{-- ✅ Sidebar 2-mode Toggle Script (desktop) --}}
<script>
(function () {
    const btn = document.getElementById('sidebarToggleBtn');
    const icon = document.getElementById('sidebarToggleIcon');
    if (!btn || !icon) return;

    function applyIcon() {
        const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
        // collapsed => show "chevron right" (to expand), normal => chevron left (to collapse)
        icon.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
    }

    applyIcon();

    btn.addEventListener('click', function () {
        document.documentElement.classList.toggle('sidebar-collapsed');
        const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
        try { localStorage.setItem('sidebar_mode', collapsed ? 'collapsed' : 'normal'); } catch(e) {}
        applyIcon();
    });
})();
</script>

</body>
</html>
