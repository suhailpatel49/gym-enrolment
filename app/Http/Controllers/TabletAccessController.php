<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TabletAccessController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('tablet_authenticated', false)) {
            return redirect()->route('enrollment.create');
        }

        return view('tablet-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits_between:4,8'],
        ]);

        if (! hash_equals((string) config('gym.tablet_pin'), (string) $validated['pin'])) {
            return back()->withErrors(['pin' => 'The access PIN is not correct.'])->onlyInput('pin');
        }

        $request->session()->regenerate();
        $request->session()->put('tablet_authenticated', true);

        return redirect()->route('enrollment.create');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('logout', [
            'pin' => ['required', 'digits_between:4,8'],
        ]);

        if (! hash_equals((string) config('gym.tablet_pin'), (string) $validated['pin'])) {
            return back()->withErrors(['pin' => 'The PIN is not correct.'], 'logout');
        }

        $request->session()->forget(['tablet_authenticated', 'enrollment_reviews']);
        $request->session()->regenerateToken();

        return redirect()->route('tablet.login');
    }
}
