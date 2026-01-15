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
    


    <style>
        body { min-height: 100vh; overflow-x: hidden; }
        .sidebar { min-height: 100vh; }
        .sidebar .nav-link.active { background-color: #0d6efd; color: #fff !important; }
        .sidebar .nav-link { color: #333; }

        @media (max-width: 767.98px) {
            .sidebar { min-height: auto; }
        }

        /* small icon polish */
        .icon-actions .btn i { font-size: 1.1rem; }
        .icon-actions .btn:hover i { transform: scale(1.1); transition: 0.15s ease; }
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
                            👤 Πελάτες
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
                    
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-primary" title="Ειδοποιήσεις">
                        <i class="bi bi-bell"></i>
                    </a>
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
        <nav class="col-md-2 col-lg-2 d-none d-md-block bg-light sidebar py-3">
            <div class="position-sticky d-flex flex-column justify-content-between h-100">
                <div>
                    <div class="px-3 logo-img mb-4">
                        <a class="navbar-brand fw-bold" href="#">
                            <img src="{{ asset('images/logo.png') }}" alt="Booking App" height="32">
                        </a>
                    </div>
                    <hr>

                    <ul class="nav flex-column px-2">
                        @if($user && $user->role !== 'therapist')
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('customers.*')) active @endif"
                                   href="{{ route('customers.index') }}">👤 Πελάτες</a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('professionals.*')) active @endif"
                                   href="{{ route('professionals.index') }}">💼 Θεραπευτές</a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('appointments.*')) active @endif"
                                   href="{{ route('appointments.index') }}">📅 Ραντεβού</a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('expenses.*')) active @endif"
                                   href="{{ route('expenses.index') }}">💸 Έξοδα</a>
                            </li>
                            @if(Auth::check() && Auth::user()->role === 'owner')
                                <li class="nav-item mb-1">
                                    <a class="nav-link @if(request()->routeIs('settlements.*')) active @endif"
                                       href="{{ route('settlements.index') }}">📑 Εκκαθάριση</a>
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
                                   href="{{ route('therapist_appointments.index') }}">🗓 Ραντεβού θεραπευτών</a>
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

                    <div class="d-flex justify-content-start gap-3 icon-actions">
                        @if($user && in_array($user->role, ['owner', 'grammatia']))
                            <a href="{{ route('appointments.recycle') }}" class="btn btn-outline-secondary" title="Recycle Ραντεβού">
                                <i class="bi bi-trash"></i>
                            </a>

                            <a href="{{ route('documents.index') }}" class="btn btn-outline-success" title="Αρχεία">
                                <i class="bi bi-folder2-open"></i>
                            </a>

                            <a href="{{ route('notifications.index') }}" class="btn btn-outline-primary" title="Ειδοποιήσεις">
                                <i class="bi bi-bell"></i>
                            </a>
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
        <main class="col-12 col-md-10 ms-sm-auto col-lg-10 px-3 px-md-4 py-4">
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

{{-- ✅ Notifications Modal --}}
@if(Auth::check())
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="notificationsToasts" class="d-grid gap-2"></div>
</div>
@endif


{{-- ✅ 1) Bootstrap JS ΠΡΩΤΑ --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/el.js"></script>

{{-- ✅ 2) Μετά φορτώνουμε scripts από views --}}
@stack('scripts')

{{-- ✅ 3) Και ΤΕΛΟΣ το global notifications script (χωρίς push/stack για να μην μπερδεύεσαι) --}}
@if(Auth::check())
<script>
(function () {
    let shownIds = new Set();

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
        return String(str ?? '').replace(/[&<>"']/g, function (m) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
        });
    }

    function renderToast(n) {
        const container = document.getElementById('notificationsToasts');
        if (!container) return;

        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-start';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.setAttribute('style', 'background-color: #e9f5ff;');

        const note = escapeHtml(n.note);
        const when = escapeHtml(n.notify_at_text || n.notify_at || '');

        toastEl.innerHTML = `
            <div class="toast-header">
                <i class="bi bi-bell-fill me-2"></i>
                <strong class="me-auto">Ειδοποίηση</strong>
                <small class="text-muted">${when}</small>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <div>${note}</div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-primary js-ok">Μην εμφανιστεί ξανά</button>
                </div>
            </div>
        `;

        container.prepend(toastEl);

        const toast = new bootstrap.Toast(toastEl, {
            autohide: false // να μένει μέχρι να πατήσει ΟΚ/close
        });
        toast.show();

        // Mark read on OK (και κλείσιμο)
        toastEl.querySelector('.js-ok').addEventListener('click', async () => {
            await markRead(n.id);
            toast.hide();
        });

        // Mark read και αν το κλείσει με το X
        toastEl.querySelector('.js-ok').addEventListener('click', async () => {
            await markRead(n.id);
            toast.hide();
            toastEl.remove();
        });
    }

    async function checkAndShow() {
        const due = await fetchDue();
        if (!due.length) return;

        const newOnes = due.filter(n => !shownIds.has(n.id));
        if (!newOnes.length) return;

        newOnes.forEach(n => {
            shownIds.add(n.id);
            renderToast(n);
        });
    }

    // run on load
    checkAndShow();
    // every 60 sec
    setInterval(checkAndShow, 60000);
})();
</script>
@endif


</body>
</html>
