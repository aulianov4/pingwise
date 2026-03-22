@php
    $statusColor = match($record->status) {
        'success' => '#22c55e',
        'warning' => '#eab308',
        'failed' => '#ef4444',
        default => '#6b7280',
    };
    $statusBg = match($record->status) {
        'success' => '#dcfce7',
        'warning' => '#fef9c3',
        'failed' => '#fee2e2',
        default => '#f3f4f6',
    };
    $statusLabel = match($record->status) {
        'success' => 'УСПЕШНО',
        'warning' => 'ПРЕДУПРЕЖДЕНИЕ',
        'failed' => 'ОШИБКА',
        default => $record->status,
    };
    $statusEmoji = match($record->status) {
        'success' => '✅',
        'warning' => '🟡',
        'failed' => '🔴',
        default => 'ℹ️',
    };
    $coverageColor = match($coverageLevel) {
        'success' => '#22c55e',
        'warning' => '#eab308',
        'danger' => '#ef4444',
        default => '#6b7280',
    };
    $healthColor = match($healthLevel) {
        'success' => '#22c55e',
        'warning' => '#eab308',
        'danger' => '#ef4444',
        default => '#6b7280',
    };
@endphp

<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    {{-- ===== HEADER ===== --}}
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-radius: 0.75rem; background: {{ $statusBg }}; border: 1px solid {{ $statusColor }}40;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.5rem;">{{ $statusEmoji }}</span>
            <div>
                <div style="font-size: 1.125rem; font-weight: 700; color: {{ $statusColor }};">Аудит карты сайта — {{ $statusLabel }}</div>
                <div style="font-size: 0.8125rem; color: #6b7280;">{{ $site->name }} · {{ $record->checked_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 2rem; font-weight: 800; color: {{ $healthColor }};">{{ $healthScore }}</div>
            <div style="font-size: 0.6875rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Health Score</div>
        </div>
    </div>

    {{-- ===== STATS GRID ===== --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
        {{-- Coverage --}}
        <div style="padding: 1rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 800; color: {{ $coverageColor }};">{{ $coverage }}%</div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Покрытие</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">{{ $crawledCount }} из {{ $sitemapCount }}</div>
        </div>

        {{-- Broken --}}
        <div style="padding: 1rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 800; color: {{ count($brokenPages) > 0 ? '#ef4444' : '#22c55e' }};">{{ count($brokenPages) }}</div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Битые страницы</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">❌ dead + non-200</div>
        </div>

        {{-- Missing from sitemap --}}
        <div style="padding: 1rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 800; color: {{ count($missingFromSitemap) > 0 ? '#eab308' : '#22c55e' }};">{{ count($missingFromSitemap) }}</div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Нет в sitemap</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">⚠️ SEO-пробелы</div>
        </div>

        {{-- Redirects --}}
        <div style="padding: 1rem; border-radius: 0.75rem; border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 800; color: {{ count($redirecting) > 0 ? '#eab308' : '#22c55e' }};">{{ count($redirecting) }}</div>
            <div style="font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Редиректы</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">🔁 в sitemap</div>
        </div>
    </div>

    {{-- ===== CRAWL LIMITED WARNING ===== --}}
    @if($crawlLimited)
        <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.875rem 1rem; border-radius: 0.75rem; background: #fef3c7; border: 1px solid #f59e0b40;">
            <span style="font-size: 1.25rem; flex-shrink: 0;">⚠️</span>
            <div>
                <div style="font-size: 0.875rem; font-weight: 600; color: #92400e;">Обход ограничен лимитом страниц</div>
                <div style="font-size: 0.8125rem; color: #a16207;">Результаты могут быть неполными. Увеличьте <code style="background: #fde68a; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem;">max_crawl_pages</code> в настройках теста.</div>
            </div>
        </div>
    @endif

    {{-- ===== INSIGHTS ===== --}}
    @if(count($insights) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="padding: 0.75rem 1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; font-weight: 600;">
                🧠 Анализ и рекомендации
            </div>
            <div style="display: flex; flex-direction: column; gap: 0;">
                @foreach($insights as $insight)
                    @php
                        $insightIcon = match($insight['type']) {
                            'danger' => '🚨',
                            'warning' => '💡',
                            'success' => '✅',
                            default => 'ℹ️',
                        };
                        $insightBg = match($insight['type']) {
                            'danger' => '#fef2f2',
                            'warning' => '#fffbeb',
                            'success' => '#f0fdf4',
                            default => '#f9fafb',
                        };
                    @endphp
                    <div style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.625rem 1rem; background: {{ $insightBg }}; border-bottom: 1px solid #e5e7eb;">
                        <span style="flex-shrink: 0;">{{ $insightIcon }}</span>
                        <span style="font-size: 0.8125rem; color: #374151;">{{ $insight['message'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== BROKEN PAGES ===== --}}
    @if(count($brokenPages) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #fef2f2; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #991b1b;">❌ Битые страницы ({{ count($brokenPages) }})</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                @foreach(array_slice($brokenPages, 0, 10) as $page)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem;">
                        <span style="color: #374151; word-break: break-all;">{{ $page['url'] }}</span>
                        <span style="flex-shrink: 0; margin-left: 0.75rem; padding: 0.125rem 0.5rem; border-radius: 0.25rem; background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 500;">{{ $page['status'] }}</span>
                    </div>
                @endforeach
                @if(count($brokenPages) > 10)
                    <div style="padding: 0.625rem 1rem; font-size: 0.8125rem; color: #6b7280; text-align: center; background: #f9fafb;">
                        + ещё {{ count($brokenPages) - 10 }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== MISSING FROM SITEMAP ===== --}}
    @if(count($missingFromSitemap) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #fffbeb; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #92400e;">⚠️ Нет в sitemap ({{ count($missingFromSitemap) }})</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                @foreach(array_slice($missingFromSitemap, 0, 10) as $url)
                    <div style="padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem; color: #374151; word-break: break-all;">
                        {{ $url }}
                    </div>
                @endforeach
                @if(count($missingFromSitemap) > 10)
                    <div style="padding: 0.625rem 1rem; font-size: 0.8125rem; color: #6b7280; text-align: center; background: #f9fafb;">
                        + ещё {{ count($missingFromSitemap) - 10 }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== REDIRECTS ===== --}}
    @if(count($redirecting) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #eff6ff; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">🔁 Редиректы в sitemap ({{ count($redirecting) }})</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                @foreach(array_slice($redirecting, 0, 10) as $url)
                    <div style="padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem; color: #374151; word-break: break-all;">
                        {{ $url }}
                    </div>
                @endforeach
                @if(count($redirecting) > 10)
                    <div style="padding: 0.625rem 1rem; font-size: 0.8125rem; color: #6b7280; text-align: center; background: #f9fafb;">
                        + ещё {{ count($redirecting) - 10 }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== CANONICAL ISSUES ===== --}}
    @if(count($canonicalIssues) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #faf5ff; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #6b21a8;">⚠️ Canonical-проблемы ({{ count($canonicalIssues) }})</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                @foreach(array_slice($canonicalIssues, 0, 10) as $url)
                    <div style="padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem; color: #374151; word-break: break-all;">
                        {{ $url }}
                    </div>
                @endforeach
                @if(count($canonicalIssues) > 10)
                    <div style="padding: 0.625rem 1rem; font-size: 0.8125rem; color: #6b7280; text-align: center; background: #f9fafb;">
                        + ещё {{ count($canonicalIssues) - 10 }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== SITEMAP PARSE ERRORS ===== --}}
    @if(count($data['sitemap_parse_errors'] ?? []) > 0)
        <div style="border-radius: 0.75rem; border: 1px solid #e5e7eb; overflow: hidden;">
            <div style="padding: 0.75rem 1rem; background: #fef2f2; border-bottom: 1px solid #e5e7eb;">
                <span style="font-size: 0.875rem; font-weight: 600; color: #991b1b;">🚨 Ошибки парсинга sitemap</span>
            </div>
            <div style="display: flex; flex-direction: column;">
                @foreach($data['sitemap_parse_errors'] as $error)
                    <div style="padding: 0.5rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem; color: #991b1b;">
                        {{ $error }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== FOOTER META ===== --}}
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 0.75rem; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 0.75rem; color: #9ca3af;">
        <span>📊 Sitemap: {{ $sitemapCount }} URL · 🌐 Обход: {{ $crawledCount }} страниц</span>
        <span>🕐 {{ $record->checked_at->format('d.m.Y H:i:s') }}</span>
    </div>

</div>

