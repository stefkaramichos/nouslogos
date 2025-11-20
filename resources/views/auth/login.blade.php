@extends('layouts.app')

@section('title', 'Σύνδεση')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-4">

            <div class="card">
                <div class="card-header">
                    Σύνδεση στο σύστημα
                </div>

                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('login.post') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email"
                                   name="email"
                                   class="form-control"
                                   value="{{ old('email') }}"
                                   required
                                   autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Κωδικός</label>
                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   required>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="remember"
                                   name="remember">
                            <label class="form-check-label" for="remember">
                                Να με θυμάσαι
                            </label>
                        </div>

                        <button class="btn btn-primary w-100">
                            Σύνδεση
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
@endsection
