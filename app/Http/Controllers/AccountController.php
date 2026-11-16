<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** A signed-in player's page: the listings they run and the claims they've made. */
class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'roles' => $user->listingRoles()->with('listing')->latest()->get()->filter(fn ($role) => $role->listing),
            'claims' => $user->listingClaims()->with('claimable')->latest()->get()->filter(fn ($claim) => $claim->claimable),
        ]);
    }
}
