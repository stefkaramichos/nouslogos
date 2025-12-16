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
                            💼 Επαγγελματίες
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
        <div class="mt-3 border-top pt-3">

            {{-- Owner: Ραντεβού θεραπευτών --}}
            @if($user && $user->role === 'owner')
                <a class="btn btn-outline-primary w-100 mb-2 @if(request()->routeIs('therapist_appointments.*')) active @endif"
                   href="{{ route('therapist_appointments.index') }}">
                    🗓 Ραντεβού θεραπευτών
                </a>
                {{-- ✅ γραμμή κάτω από το Ραντεβού θεραπευτών --}}
                <hr class="my-2">
            @endif

            {{-- ✅ Logout + Recycle: icons only --}}
            <div class="d-flex justify-content-center gap-3 icon-actions">
                @if($user && in_array($user->role, ['owner', 'grammatia']))
                    <a href="{{ route('appointments.recycle') }}"
                       class="btn btn-outline-secondary"
                       title="Recycle Ραντεβού">
                        <i class="bi bi-arrow-counterclockwise"></i>
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

        {{-- DESKTOP SIDEBAR (visible only on >= md) --}}
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
                                   href="{{ route('customers.index') }}">
                                    👤 Πελάτες
                                </a>
                            </li>
                            <li class="nav-item mb-1">
                                <a class="nav-link @if(request()->routeIs('professionals.*')) active @endif"
                                   href="{{ route('professionals.index') }}">
                                    💼 Επαγγελματίες
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

                {{-- BOTTOM ACTIONS (DESKTOP) --}}
                <div class="px-2 mt-3 pt-3">

                    {{-- Owner: Ραντεβού θεραπευτών --}}
                    @if($user && $user->role === 'owner')
                        <a class="btn btn-outline-primary w-100 mb-2 @if(request()->routeIs('therapist_appointments.*')) active @endif"
                           href="{{ route('therapist_appointments.index') }}">
                            🗓 Ραντεβού θεραπευτών
                        </a>
                        {{-- ✅ γραμμή κάτω από το Ραντεβού θεραπευτών --}}
                        <hr class="my-2">
                    @endif

                    {{-- ✅ Logout + Recycle: icons only --}}
                    <div class="d-flex justify-content-center gap-3 icon-actions">
                        @if($user && in_array($user->role, ['owner', 'grammatia']))
                            <a href="{{ route('appointments.recycle') }}"
                               class="btn btn-outline-secondary"
                               title="Recycle Ραντεβού">
                                <i class="bi bi-arrow-counterclockwise"></i>
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

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Validation errors --}}
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

@stack('scripts')
</body>
</html>
