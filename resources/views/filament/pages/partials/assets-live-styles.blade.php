{{-- Inlined so production works without rebuilding public/css/filament/admin/theme.css --}}
<style>
    #assets-live-chart-preview .assets-live-chart-header,
    #assets-live-chart-preview .assets-live-ohlc-row,
    #assets-live-chart-preview .assets-live-chart-footer {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
    #assets-live-chart-preview .assets-live-chart-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding-top: 1.25rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid rgb(243 244 246);
    }
    #assets-live-chart-preview .assets-live-header-copy { min-width: 0; flex: 1 1 auto; }
    #assets-live-chart-preview .assets-live-header-controls {
        display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.75rem;
    }
    #assets-live-chart-preview .assets-live-ohlc-row {
        padding-top: 1rem; padding-bottom: 1rem;
        border-bottom: 1px solid rgb(243 244 246);
    }
    #assets-live-chart-preview .assets-live-ohlc-pills {
        display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;
    }
    #assets-live-chart-preview .assets-live-ohlc-pill {
        display: inline-flex; align-items: center; gap: 0.5rem;
        border-radius: 0.5rem; padding: 0.375rem 0.75rem;
        font-size: 0.875rem; line-height: 1.25rem; white-space: nowrap;
    }
    #assets-live-chart-preview .assets-live-ohlc-pill--open {
        background: rgb(241 245 249); color: rgb(15 23 42);
    }
    #assets-live-chart-preview .assets-live-ohlc-pill--high {
        background: rgb(240 253 244); color: rgb(21 128 61);
    }
    #assets-live-chart-preview .assets-live-ohlc-pill--low {
        background: rgb(254 242 242); color: rgb(185 28 28);
    }
    #assets-live-chart-preview .assets-live-ohlc-pill--close-up {
        background: rgb(240 253 244); color: rgb(21 128 61);
    }
    #assets-live-chart-preview .assets-live-ohlc-pill--close-down {
        background: rgb(254 242 242); color: rgb(185 28 28);
    }
    #assets-live-chart-preview .assets-live-ohlc-label {
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
    }
    #assets-live-chart-preview .assets-live-ohlc-value {
        font-weight: 600; font-variant-numeric: tabular-nums;
    }
    #assets-live-chart-preview .assets-live-chart-footer {
        padding-top: 0.75rem; padding-bottom: 0.75rem;
        font-size: 0.75rem; color: rgb(107 114 128);
        border-top: 1px solid rgb(243 244 246);
    }
    .assets-live-trade-chart-panel,
    .assets-live-info-panel {
        margin-bottom: 1rem; border-radius: 0.75rem;
        border: 1px solid rgb(229 231 235); background: #fff;
        padding: 1rem 1.5rem;
    }
    .assets-live-trade-chart-panel-inner,
    .assets-live-info-panel-inner {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: 1rem;
    }
    .assets-live-trade-chart-copy { min-width: 0; flex: 1 1 auto; }
    .assets-live-trade-chart-title,
    .assets-live-info-title {
        font-size: 0.875rem; font-weight: 500; color: rgb(17 24 39); margin: 0;
    }
    .assets-live-trade-chart-desc,
    .assets-live-info-desc {
        margin: 0.25rem 0 0; font-size: 0.875rem; color: rgb(75 85 99);
    }
    .assets-live-trade-chart-actions {
        display: flex; flex-shrink: 0; flex-wrap: wrap;
        align-items: center; gap: 0.75rem;
    }
    .assets-live-status-badge {
        display: inline-block; border-radius: 9999px;
        padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .assets-live-status-badge--live {
        background: rgb(240 253 244); color: rgb(21 128 61);
    }
    .assets-live-status-badge--paused {
        background: rgb(243 244 246); color: rgb(75 85 99);
    }
    .assets-live-toggle-btn {
        display: inline-flex !important; align-items: center; gap: 0.5rem;
        border: none; border-radius: 0.5rem; padding: 0.5rem 1rem;
        font-size: 0.875rem; font-weight: 600; color: #fff !important;
        cursor: pointer; box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
    }
    .assets-live-toggle-btn svg {
        width: 1.25rem !important; height: 1.25rem !important;
        flex-shrink: 0; display: block;
    }
    .assets-live-toggle-btn--start { background: rgb(22 163 74) !important; }
    .assets-live-toggle-btn--start:hover { background: rgb(34 197 94) !important; }
    .assets-live-toggle-btn--stop { background: rgb(217 119 6) !important; }
    .assets-live-toggle-btn--stop:hover { background: rgb(245 158 11) !important; }
    .assets-live-rate-limit {
        margin-top: 0.5rem; border-radius: 0.5rem;
        background: rgb(255 251 235); padding: 0.5rem 0.75rem; color: rgb(180 83 9);
    }
    .assets-live-updated-badge {
        display: inline-block; border-radius: 9999px;
        background: rgb(243 244 246); padding: 0.25rem 0.75rem;
        font-size: 0.75rem; font-weight: 500; color: rgb(55 65 81);
    }
</style>
