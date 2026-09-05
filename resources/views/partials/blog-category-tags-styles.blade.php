<style>
    .bh-card__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: flex-start;
    }
    .bh-card__tags--overlay {
        position: absolute;
        top: 0.75rem;
        left: 0.75rem;
        z-index: 2;
        max-width: calc(100% - 1.5rem);
        pointer-events: none;
    }
    .bh-card__tags--inline {
        margin-bottom: 0.5rem;
    }
    .bh-card__tags--text {
        margin-bottom: 0.35rem;
    }
    .bh-card__tag {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 0.32rem 0.7rem;
        border-radius: 999px;
        background: var(--bh-accent, #2563eb);
        color: #fff;
        font-size: 0.62rem;
        font-weight: 700;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.18);
    }
    .bh-card__tags--inline .bh-card__tag,
    .bh-card__tags--text .bh-card__tag {
        box-shadow: none;
    }
    .bh-card__tag:nth-child(4n + 1) { background: #ec4899; }
    .bh-card__tag:nth-child(4n + 2) { background: #2563eb; }
    .bh-card__tag:nth-child(4n + 3) { background: #d97706; }
    .bh-card__tag:nth-child(4n + 4) { background: #dc2626; }
    .bh-card__tags--compact .bh-card__tag {
        font-size: 0.55rem;
        padding: 0.22rem 0.48rem;
    }
    .bh-card__tags--text .bh-card__tag {
        background: transparent;
        color: rgba(255, 255, 255, 0.92);
        padding: 0.15rem 0;
        font-size: 0.68rem;
        letter-spacing: 0.06em;
        box-shadow: none;
    }
    .bh-cat-list__item-thumb,
    .bh-cat-zigzag-item__media {
        position: relative;
        flex-shrink: 0;
        overflow: hidden;
        border-radius: 10px;
    }
    .bh-cat-list__item-thumb {
        width: 100px;
        height: 80px;
    }
    .bh-cat-list__item-thumb img,
    .bh-cat-zigzag-item__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .bh-cat-zigzag-item__media {
        border-radius: 16px;
    }
    .bh-hero__card .bh-card__tags--overlay,
    .blog-hero__card .bh-card__tags--overlay {
        top: 0.45rem;
        left: 0.45rem;
        max-width: calc(100% - 0.9rem);
    }
    @media (max-width: 768px) {
        .bh-card__tags--overlay {
            top: 0.5rem;
            left: 0.5rem;
            max-width: calc(100% - 1rem);
        }
        .bh-card__tag {
            font-size: 0.58rem;
            padding: 0.26rem 0.55rem;
        }
        .bh-featured__carousel-body .bh-card__tags,
        .bh-featured__sidebar-item-body .bh-card__tags,
        .bh-cat-list__featured-overlay .bh-card__tags,
        .bh-cat-zigzag-item-body .bh-card__tags,
        .bh-cat-list__item-body .bh-card__tags {
            display: none;
        }
    }
</style>
