<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Booking App')</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
        }
        .sidebar {
            min-height: 100vh;
        }
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: #fff !important;
        }
        .sidebar .nav-link {
            color: #333;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        {{-- Sidebar --}}
        <nav class="col-md-2 col-lg-2 d-md-block bg-light sidebar py-3">
            <div class="position-sticky">
                <div class="px-3 mb-4">
                    <h4 class="fw-bold">Booking App</h4>
                    <small class="text-muted">Πάνελ Διαχείρισης</small>
                </div>

                <ul class="nav flex-column px-2">
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('customers.*')) active @endif"
                            href="{{ route('customers.index') }}">
                            👤 Πελάτες
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('appointments.*')) active @endif"
                           href="{{ route('appointments.index') }}">
                            📅 Ραντεβού
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        <a class="nav-link @if(request()->routeIs('professionals.*')) active @endif"
                           href="{{ route('professionals.index') }}">
                            💼 Επαγγελματίες
                        </a>
                    </li>
                    <li class="nav-item mb-1">
                        @if(Auth::check() && Auth::user()->role === 'owner')
                            <a class="nav-link @if(request()->routeIs('settlements.*')) active @endif"
                            href="{{ route('settlements.index') }}">
                                📑 Εκκαθάριση
                            </a>
                        @endif
                    </li>
                </ul>
            </div>
            <div class="nav-item mt-3 logout-button">
                <form action="{{ route('logout') }}" method="POST" class="d-flex align-items-center">
                    @csrf
                    <button class="btn w-100 d-flex align-items-center justify-content-center">
                        <i class="bi bi-box-arrow-right me-2"></i> Αποσύνδεση
                    </button>
                </form>
            </div>
        </nav>

        {{-- Main Content --}}
        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                <h2 class="h3">@yield('title', 'Επισκόπηση')</h2>
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
