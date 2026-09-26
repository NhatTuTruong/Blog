<style>
    .deal-section {
        margin-top: 2.5rem;
    }
    .deal-section__head {
        margin-bottom: 1.5rem;
    }
    .deal-section__title {
        font-family: 'Poppins', sans-serif;
        font-size: clamp(1.35rem, 3vw, 1.75rem);
        font-weight: 700;
        color: #1a2332;
        margin: 0 0 1rem;
    }
    .deal-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
    }
    .deal-filter {
        display: inline-flex;
        align-items: center;
        padding: 0.55rem 1.15rem;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        font-size: 0.88rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .deal-filter:hover {
        border-color: rgba(37, 99, 235, 0.45);
        color: #1d4ed8;
    }
    .deal-filter.is-active {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: #fff;
        box-shadow: 0 4px 14px rgba(239, 108, 0, 0.28);
    }
    .deal-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
    @media (max-width: 1024px) {
        .deal-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .deal-grid { grid-template-columns: 1fr; }
    }
    .deal-card {
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid rgba(37, 99, 235, 0.14);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 14px rgba(15, 23, 42, 0.05);
        transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        height: 100%;
        position: relative;
    }
    .deal-card--clickable {
        cursor: pointer;
    }
    .deal-card__stretched-link {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: inherit;
    }
    .deal-card--clickable .deal-card__top,
    .deal-card--clickable .deal-card__body {
        pointer-events: none;
    }
    .deal-card--clickable .deal-card__code,
    .deal-card--clickable .deal-card__shop {
        pointer-events: auto;
        position: relative;
        z-index: 2;
    }
    .deal-card:hover {
        transform: translateY(-4px);
        border-color: rgba(37, 99, 235, 0.38);
        box-shadow: 0 14px 32px rgba(37, 99, 235, 0.12);
    }
    .deal-card__top {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        padding: 1rem 1.1rem 0.95rem;
        border-bottom: 1px solid #eef2f6;
    }
    .deal-card__media {
        width: 72px;
        height: 72px;
        flex-shrink: 0;
        border-radius: 12px;
        border: 1px solid rgba(37, 99, 235, 0.14);
        background: linear-gradient(135deg, #f0f5ff 0%, #eff6ff 100%);
        overflow: hidden;
    }
    .deal-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.35s ease;
    }
    .deal-card:hover .deal-card__media img {
        transform: scale(1.05);
    }
    .deal-card__media-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        font-family: 'Poppins', sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        color: rgba(239, 108, 0, 0.45);
    }
    .deal-card__meta {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.35rem;
        padding-top: 0.1rem;
    }
    .deal-card__badge {
        display: inline-flex;
        align-items: center;
        padding: 0.24rem 0.55rem;
        border-radius: 6px;
        background: #1d4ed8;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        box-shadow: 0 2px 8px rgba(239, 108, 0, 0.25);
    }
    .deal-card__body {
        display: flex;
        flex-direction: column;
        flex: 1;
        gap: 0.45rem;
        padding: 0.95rem 1.1rem 1.1rem;
    }
    .deal-card__type {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #94a3b8;
        text-align: right;
    }
    .deal-card__title {
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        font-weight: 700;
        line-height: 1.4;
        color: #1a2332;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .deal-card__desc {
        margin: 0;
        font-size: 0.82rem;
        line-height: 1.5;
        color: #64748b;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .deal-card__footer {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin-top: auto;
        padding-top: 0.65rem;
    }
    .deal-card__code {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex: 1;
        min-width: 0;
        padding: 0.5rem 0.65rem;
        border-radius: 8px;
        background: #FFFBF5;
        border: 1px dashed rgba(37, 99, 235, 0.5);
        color: #1d4ed8;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
    }
    .deal-card__code:hover,
    .deal-card__code.is-copied {
        background: #eff6ff;
        border-color: #1d4ed8;
    }
    .deal-card__code-label {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        flex-shrink: 0;
    }
    .deal-card__code-value {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        letter-spacing: 0.04em;
    }
    .deal-card__shop {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        min-width: 108px;
        padding: 0.55rem 1rem;
        border-radius: 9px;
        border: 1px solid #1a2332;
        background: #fff;
        color: #1a2332;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .deal-card__shop:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: #fff;
    }
    .deal-card:not(:has(.deal-card__code)) .deal-card__shop {
        flex: 1;
    }
    .deal-page__hero {
        background: #fff;
        border-bottom: 2px solid rgba(37, 99, 235, 0.2);
        padding: 2.25rem 1.5rem;
    }
    .deal-page__hero-inner {
        position: relative;
        max-width: 1280px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid rgba(37, 99, 235, 0.22);
        border-left: 4px solid #2563eb;
        border-radius: 16px;
        padding: 2rem 2.25rem;
        box-shadow: 0 8px 32px rgba(37, 99, 235, 0.07);
        overflow: hidden;
    }
    .deal-page__hero-inner::before {
        content: '';
        position: absolute;
        top: -30%;
        right: -5%;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(37, 99, 235, 0.07) 0%, transparent 70%);
        pointer-events: none;
    }
    .deal-page__label {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #1d4ed8;
        background: rgba(37, 99, 235, 0.08);
        border: 1px solid rgba(37, 99, 235, 0.35);
        border-radius: 999px;
        padding: 0.38rem 0.9rem;
        margin-bottom: 1rem;
    }
    .deal-page__label::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }
    .deal-page__title {
        position: relative;
        z-index: 1;
        font-family: 'Poppins', sans-serif;
        font-size: clamp(1.85rem, 4vw, 2.75rem);
        font-weight: 700;
        line-height: 1.12;
        letter-spacing: -0.025em;
        margin: 0 0 0.65rem;
        color: #1a2332;
    }
    .deal-page__subtitle {
        position: relative;
        z-index: 1;
        margin: 0;
        max-width: 40rem;
        color: #64748b;
        font-size: 1.02rem;
        line-height: 1.65;
    }
    @media (max-width: 640px) {
        .deal-page__hero { padding: 1.5rem 1rem; }
        .deal-page__hero-inner { padding: 1.5rem 1.25rem; border-radius: 12px; }
    }
    .deal-page__body {
        max-width: 1280px;
        margin: 0 auto;
        padding: 2rem 1.5rem 4rem;
    }
    .deal-page__empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #64748b;
        border: 1px dashed rgba(37, 99, 235, 0.25);
        border-radius: 14px;
        background: #FFFBF5;
    }
</style>
