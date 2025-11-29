<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\QrAuthService;
use Illuminate\Http\Request;

class AuthApproveController extends Controller
{
    public function __construct(
        private QrAuthService $qrAuthService
    ) {}

    /**
     * Show approve page (from email link)
     */
    public function show(Request $request)
    {
        $token = $request->query('token');

        if (!$token || strlen($token) !== 64) {
            return $this->renderPage([
                'error' => 'Ongeldige of verlopen link',
                'success' => false,
            ]);
        }

        return $this->renderPage([
            'token' => $token,
            'success' => false,
            'error' => null,
        ]);
    }

    /**
     * Process approve (POST from approve page)
     */
    public function process(Request $request)
    {
        $token = $request->input('token');

        if (!$token || strlen($token) !== 64) {
            return $this->renderPage([
                'error' => 'Ongeldige of verlopen link',
                'success' => false,
            ]);
        }

        $result = $this->qrAuthService->approveViaEmailToken($token, $request->ip());

        if ($result['success']) {
            return $this->renderPage([
                'success' => true,
                'user_name' => $result['user']['name'] ?? 'Gebruiker',
                'device_name' => $result['device_name'] ?? 'Onbekend apparaat',
            ]);
        }

        return $this->renderPage([
            'error' => $result['message'] ?? 'Kon login niet goedkeuren',
            'success' => false,
        ]);
    }

    /**
     * Render the approve page HTML (no Blade needed)
     */
    private function renderPage(array $data): \Illuminate\Http\Response
    {
        $success = $data['success'] ?? false;
        $error = $data['error'] ?? null;
        $token = $data['token'] ?? '';
        $userName = $data['user_name'] ?? '';
        $deviceName = $data['device_name'] ?? '';

        if ($success) {
            $content = $this->successHtml($userName, $deviceName);
        } elseif ($error) {
            $content = $this->errorHtml($error);
        } else {
            $content = $this->formHtml($token);
        }

        return response($this->wrapHtml($content));
    }

    private function wrapHtml(string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Goedkeuren - HavunCore</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            {$content}
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function successHtml(string $userName, string $deviceName): string
    {
        return <<<HTML
<div class="text-center">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Ingelogd!</h2>
    <p class="text-gray-600 mb-4">
        Hallo {$userName}, je bent nu ingelogd op:<br>
        <strong>{$deviceName}</strong>
    </p>
    <p class="text-sm text-gray-500">Je kunt dit venster sluiten.</p>
</div>
HTML;
    }

    private function errorHtml(string $error): string
    {
        return <<<HTML
<div class="text-center">
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </div>
    <h2 class="text-2xl font-bold text-gray-900 mb-2">Oeps!</h2>
    <p class="text-gray-600 mb-4">{$error}</p>
</div>
HTML;
    }

    private function formHtml(string $token): string
    {
        $csrfToken = csrf_token();
        return <<<HTML
<div class="text-center mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Login Goedkeuren</h2>
    <p class="text-gray-600 mt-2">Wil je inloggen?</p>
</div>

<form method="POST" action="/approve">
    <input type="hidden" name="_token" value="{$csrfToken}">
    <input type="hidden" name="token" value="{$token}">

    <button type="submit" class="w-full py-4 px-6 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium text-lg mb-4">
        Ja, log mij in
    </button>
</form>

<p class="text-center text-gray-500 text-sm">
    Was jij dit niet? Sluit dan dit venster.
</p>
HTML;
    }
}
