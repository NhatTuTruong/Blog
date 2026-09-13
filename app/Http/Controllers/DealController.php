<?php

namespace App\Http\Controllers;

use App\Models\BlogDeal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DealController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('type')->toString();
        if (! in_array($filter, ['all', 'coupon', 'discount'], true)) {
            $filter = 'all';
        }

        $deals = BlogDeal::query()
            ->with(['blogs'])
            ->whereHas('blogs', fn ($query) => $query->published())
            ->when($filter === 'coupon', fn ($query) => $query->whereNotNull('coupon_code')->where('coupon_code', '!=', ''))
            ->when($filter === 'discount', fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('coupon_code')->orWhere('coupon_code', '');
            }))
            ->orderedForListing()
            ->paginate(24)
            ->withQueryString();

        return view('deals.index', [
            'deals' => $deals,
            'filter' => $filter,
        ]);
    }
}
