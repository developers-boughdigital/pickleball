<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Support\Area;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Popular locations: the cities and regions with the most live venues.
        $popular = Venue::query()->visible()->whereNotNull('city')
            ->groupBy('state', 'city')
            ->selectRaw('state, city, count(*) as total')
            ->orderByDesc('total')->limit(8)->toBase()->get()
            ->map(fn (object $row) => (object) ['area' => Area::make($row->state, $row->city), 'venues' => (int) $row->total]);

        return view('home', ['popular' => $popular]);
    }
}
