@extends('layouts.app')

@section('title', \App\Support\SiteSeo::pageTitle('deals'))
@section('description', \App\Support\SiteSeo::pageDescription('deals'))
@section('canonical', route('deals.index'))

@push('styles')
@include('partials.deal-cards-styles')
<style>
    .deal-page {
        background: #fff;
        color: #0f172a;
    }
    .deal-pagination {
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }
</style>
@endpush

@section('content')
<div class="deal-page">
    <section class="deal-page__hero">
        <div class="deal-page__hero-inner">
            <h1 class="deal-page__title">Coupons & Discount Deals</h1>
            <p class="deal-page__subtitle">Browse the latest coupon codes and discount offers from our reviews.</p>
        </div>
    </section>

    <div class="deal-page__body">
        @if($deals->isEmpty())
            <div class="deal-page__empty">
                <p>No deals available yet.</p>
            </div>
        @else
            @include('partials.deal-cards', [
                'deals' => $deals,
                'filter' => $filter,
                'showFilters' => true,
                'sectionTitle' => null,
            ])

            @if($deals->hasPages())
            <div class="deal-pagination">
                {{ $deals->links('vendor.pagination.simple') }}
            </div>
            @endif
        @endif
    </div>
</div>
@endsection
