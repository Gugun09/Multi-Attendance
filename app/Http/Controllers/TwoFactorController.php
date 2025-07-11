<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Show 2FA setup page
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        if (!$user->two_factor_secret) {
            $this->twoFactorService->generateSecret($user);
        }

        $qrCode = $this->twoFactorService->generateQrCode($user);
        $recoveryCodes = $user->two_factor_enabled 
            ? $this->twoFactorService->getRecoveryCodes($user)
            : collect();

        return view('auth.two-factor', compact('qrCode', 'recoveryCodes', 'user'));
    }

    /**
     * Enable 2FA
     */
    public function enable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = $request->user();
        
        if ($this->twoFactorService->enable($user, $request->code)) {
            $recoveryCodes = $this->twoFactorService->getRecoveryCodes($user);
            
            return back()->with([
                'status' => '2FA has been enabled successfully!',
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        return back()->withErrors(['code' => 'The provided code is invalid.']);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|current_password',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $this->twoFactorService->disable($request->user());

        return back()->with('status', '2FA has been disabled successfully!');
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|current_password',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = $request->user();
        
        if (!$user->two_factor_enabled) {
            return back()->withErrors(['error' => '2FA must be enabled first.']);
        }

        $recoveryCodes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return back()->with([
            'status' => 'Recovery codes have been regenerated!',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}