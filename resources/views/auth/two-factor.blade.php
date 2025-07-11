<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Authentication - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Two-Factor Authentication
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Secure your account with 2FA
                </p>
            </div>

            @if (session('status'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                @if (!$user->two_factor_enabled)
                    <!-- Setup 2FA -->
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Setup Two-Factor Authentication</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.)
                            </p>
                        </div>

                        <div class="flex justify-center">
                            <div class="bg-white p-4 rounded-lg border">
                                {!! $qrCode !!}
                            </div>
                        </div>

                        <form method="POST" action="{{ route('two-factor.enable') }}">
                            @csrf
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700">
                                    Verification Code
                                </label>
                                <input type="text" 
                                       name="code" 
                                       id="code" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Enter 6-digit code"
                                       maxlength="6"
                                       required>
                            </div>
                            <button type="submit" 
                                    class="mt-4 w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Enable 2FA
                            </button>
                        </form>
                    </div>
                @else
                    <!-- 2FA Enabled -->
                    <div class="space-y-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900">2FA is Enabled</h3>
                                <p class="text-sm text-gray-600">Your account is protected with two-factor authentication</p>
                            </div>
                        </div>

                        @if (session('recovery_codes'))
                            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-yellow-800">Recovery Codes</h4>
                                <p class="mt-1 text-sm text-yellow-700">
                                    Store these recovery codes in a safe place. They can be used to access your account if you lose your authenticator device.
                                </p>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    @foreach (session('recovery_codes') as $code)
                                        <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ $code }}</code>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex space-x-3">
                            <form method="POST" action="{{ route('two-factor.recovery-codes') }}" class="flex-1">
                                @csrf
                                <input type="password" 
                                       name="password" 
                                       placeholder="Enter password to regenerate codes"
                                       class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                       required>
                                <button type="submit" 
                                        class="mt-2 w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Regenerate Recovery Codes
                                </button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('two-factor.disable') }}">
                            @csrf
                            <input type="password" 
                                   name="password" 
                                   placeholder="Enter password to disable 2FA"
                                   class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                   required>
                            <button type="submit" 
                                    class="mt-2 w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                    onclick="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.')">
                                Disable 2FA
                            </button>
                        </form>
                    </div>
                @endif

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="/admin" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>