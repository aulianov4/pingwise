@php
    $statusColor = match($record->status) { 'success' => '#22c55e', 'warning' => '#eab308', 'failed' => '#ef4444', default => '#6b7280' };
    $statusBg    = match($record->status) { 'success' => '#dcfce7', 'warning' => '#fef9c3', 'failed' => '#fee2e2', default => '#f3f4f6' };
    $statusLabel = match($record->status) { 'success' => 'УСПЕШНО', 'warning' => 'ПРЕДУПРЕЖДЕНИЕ', 'failed' => 'ОШИБКА', default => $record->status };
    $statusEmoji = match($record->status) { 'success' => '✅', 'warning' => '🟡', 'failed' => '🔴', default => 'ℹ️' };
    $healthColor = match($healthLevel) { 'success' => '#22c55e', 'warning' => '#eab308', 'danger' => '#ef4444', default => '#6b7280' };
    $coverageColor = match($coverageLevel) { 'success' => '#22c55e', 'warning' => '#eab308', 'danger' => '#ef4444', default => '#6b7280' };
@endphp

<div x-data="sitemapAudit({{ json_encode($trendData) }})" style="display:flex;flex-direction:column;gap:1.25rem;">

    {{-- ===== HEADER ===== --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-radius:.75rem;background:{{ $statusBg }};border:1px solid {{ $statusColor }}40;">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <span style="font-size:1.5rem;">{{ $statusEmoji }}</span>
            <div>
                <div style="font-size:1.125rem;font-weight:700;color:{{ $statusColor }};">Аудит карты сайта — {{ $statusLabel }}</div>
                <div style="font-size:.8125rem;color:#6b7280;">{{ $site->name }} · {{ $record->checked_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:2rem;font-weight:800;color:{{ $healthColor }};">{{ $healthScore }}</div>
            <div style="font-size:.6875rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;">Health Score</div>
        </div>
    </div>

    {{-- ===== TABS ===== --}}
    <div style="border-radius:.75rem;border:1px solid #e5e7eb;overflow:hidden;">

        {{-- Tab nav --}}
        <div style="display:flex;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    @click="activeTab = tab.id"
                    :style="activeTab === tab.id
                        ? 'padding:.625rem 1.25rem;font-size:.875rem;font-weight:600;color:#1d4ed8;border-bottom:2px solid #1d4ed8;background:#fff;cursor:pointer;border-top:none;border-left:none;border-right:none;'
                        : 'padding:.625rem 1.25rem;font-size:.875rem;font-weight:500;color:#6b7280;border:none;background:transparent;cursor:pointer;'"
                    x-text="tab.label">
                </button>
            </template>
        </div>

        {{-- ══════════════════ TAB: SITEMAP ══════════════════ --}}
        <div x-show="activeTab === 'sitemap'" style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">

            @if(!$isFirstRun)
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;">
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:#16a34a;">+{{ $newSitemapCount }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Новых URL</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ $removedSitemapCount > 0 ? '#dc2626' : '#6b7280' }};">−{{ $removedSitemapCount }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Исчезло</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ count($deadPages) > 0 ? '#dc2626' : '#22c55e' }};">{{ count($deadPages) }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Мёртвых</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ count($redirectingPages) > 0 ? '#d97706' : '#22c55e' }};">{{ count($redirectingPages) }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Редиректов</div>
                </div>
            </div>
            @endif

            <div>
                <div style="font-size:.8125rem;font-weight:600;color:#374151;margin-bottom:.5rem;">Страниц в sitemap: <strong>{{ $sitemapCount }}</strong></div>
                @if(count($sitemapPages) > 0)
                <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                        <thead>
                            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                                <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#374151;">URL</th>
                                <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#374151;width:80px;">Статус</th>
                                <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#374151;width:70px;">Глубина</th>
                                <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#374151;">Редирект / Canonical</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($sitemapPages, 0, 50) as $page)
                            @php
                                $sc = $page['status_code'];
                                $rowBg = match(true) { $sc === 0 => '#fef2f2', $sc >= 400 => '#fef2f2', $sc >= 300 => '#fffbeb', default => 'transparent' };
                                $badge = match(true) {
                                    $sc === 0    => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'dead'],
                                    $sc >= 400   => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>(string)$sc],
                                    $sc >= 300   => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>(string)$sc],
                                    $sc === 200  => ['bg'=>'#dcfce7','text'=>'#166534','label'=>'200'],
                                    default      => ['bg'=>'#f3f4f6','text'=>'#374151','label'=>(string)$sc],
                                };
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;background:{{ $rowBg }};">
                                <td style="padding:.4375rem .75rem;word-break:break-all;color:#374151;">{{ $page['url'] }}</td>
                                <td style="padding:.4375rem .75rem;text-align:center;"><span style="padding:.125rem .4rem;border-radius:.25rem;background:{{ $badge['bg'] }};color:{{ $badge['text'] }};font-size:.75rem;font-weight:500;">{{ $badge['label'] }}</span></td>
                                <td style="padding:.4375rem .75rem;text-align:center;color:#6b7280;">{{ $page['crawl_depth'] ?? '—' }}</td>
                                <td style="padding:.4375rem .75rem;font-size:.75rem;color:#6b7280;word-break:break-all;">
                                    @if($page['redirect_target'])➡ {{ $page['redirect_target'] }}@endif
                                    @if($page['canonical'] && $page['canonical'] !== $page['url'])<span style="color:#7c3aed;">⚓ {{ $page['canonical'] }}</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(count($sitemapPages) > 50)<div style="padding:.625rem 1rem;font-size:.8125rem;color:#6b7280;text-align:center;background:#f9fafb;">+ ещё {{ count($sitemapPages) - 50 }}</div>@endif
                </div>
                @else
                <div style="padding:1.5rem;text-align:center;color:#9ca3af;">Страниц не найдено</div>
                @endif
            </div>

            @if(count($data['sitemap_parse_errors'] ?? []) > 0)
            <div style="border-radius:.5rem;border:1px solid #fca5a5;background:#fef2f2;padding:.75rem 1rem;">
                <div style="font-size:.875rem;font-weight:600;color:#991b1b;margin-bottom:.375rem;">🚨 Ошибки парсинга sitemap</div>
                @foreach($data['sitemap_parse_errors'] as $err)<div style="font-size:.8125rem;color:#b91c1c;">{{ $err }}</div>@endforeach
            </div>
            @endif
        </div>

        {{-- ══════════════════ TAB: CRAWL ══════════════════ --}}
        <div x-show="activeTab === 'crawl'" style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">

            @if(!$isFirstRun)
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;">
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:#16a34a;">+{{ $newCrawlCount }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Новых страниц</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ $removedCrawlCount > 0 ? '#dc2626' : '#6b7280' }};">−{{ $removedCrawlCount }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Исчезло</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ count($orphanPages) > 0 ? '#d97706' : '#22c55e' }};">{{ count($orphanPages) }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Нет в sitemap</div>
                </div>
                <div style="padding:.75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.5rem;font-weight:700;color:{{ $hasDeepPages ? '#d97706' : '#22c55e' }};">{{ $maxCrawlDepth }}</div>
                    <div style="font-size:.6875rem;color:#9ca3af;text-transform:uppercase;">Макс. глубина</div>
                </div>
            </div>
            @endif

            @if($hasDeepPages)
            <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.875rem 1rem;border-radius:.75rem;background:#fef3c7;border:1px solid #f59e0b40;">
                <span style="font-size:1.25rem;flex-shrink:0;">⚠️</span>
                <div>
                    <div style="font-size:.875rem;font-weight:600;color:#92400e;">Обнаружены страницы глубже {{ $maxCrawlDepth }} уровней</div>
                    <div style="font-size:.8125rem;color:#a16207;">Поисковые роботы хуже индексируют глубоко вложенные страницы. Улучшите внутреннюю перелинковку или добавьте их в sitemap.</div>
                </div>
            </div>
            @endif

            @if(count($depthDistribution) > 0)
            <div>
                <div style="font-size:.8125rem;font-weight:600;color:#374151;margin-bottom:.5rem;">Распределение по глубинам</div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                    @foreach($depthDistribution as $depth => $cnt)
                    <div style="padding:.375rem .75rem;border-radius:.375rem;background:{{ $depth >= 4 ? '#fef3c7' : '#f0f9ff' }};border:1px solid {{ $depth >= 4 ? '#fbbf24' : '#bae6fd' }};font-size:.8125rem;">
                        <span style="font-weight:600;color:{{ $depth >= 4 ? '#92400e' : '#0369a1' }};">Уровень {{ $depth }}</span>
                        <span style="color:#6b7280;margin-left:.375rem;">{{ $cnt }} стр.</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div>
                <div style="font-size:.8125rem;font-weight:600;color:#374151;margin-bottom:.5rem;">
                    Найдено краулером: <strong>{{ count($crawlPages) }}</strong>
                    @if(count($orphanPages) > 0) · <span style="color:#d97706;">{{ count($orphanPages) }} orphan</span>@endif
                </div>
                @if(count($crawlPages) > 0)
                <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                        <thead>
                            <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                                <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#374151;">URL</th>
                                <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#374151;width:80px;">Статус</th>
                                <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#374151;width:70px;">Глубина</th>
                                <th style="padding:.5rem .75rem;text-align:center;font-weight:600;color:#374151;width:80px;">Sitemap</th>
                                <th style="padding:.5rem .75rem;text-align:left;font-weight:600;color:#374151;">Canonical</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($crawlPages, 0, 50) as $page)
                            @php
                                $sc = $page['status_code'];
                                $rowBg = $page['in_sitemap'] ? 'transparent' : '#fffbeb';
                                $badge = match(true) {
                                    $sc === 0   => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>'dead'],
                                    $sc >= 400  => ['bg'=>'#fee2e2','text'=>'#991b1b','label'=>(string)$sc],
                                    $sc >= 300  => ['bg'=>'#fef3c7','text'=>'#92400e','label'=>(string)$sc],
                                    $sc === 200 => ['bg'=>'#dcfce7','text'=>'#166534','label'=>'200'],
                                    default     => ['bg'=>'#f3f4f6','text'=>'#374151','label'=>(string)$sc],
                                };
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;background:{{ $rowBg }};">
                                <td style="padding:.4375rem .75rem;word-break:break-all;color:#374151;">{{ $page['url'] }}</td>
                                <td style="padding:.4375rem .75rem;text-align:center;"><span style="padding:.125rem .4rem;border-radius:.25rem;background:{{ $badge['bg'] }};color:{{ $badge['text'] }};font-size:.75rem;font-weight:500;">{{ $badge['label'] }}</span></td>
                                <td style="padding:.4375rem .75rem;text-align:center;color:#6b7280;">{{ $page['crawl_depth'] ?? '—' }}</td>
                                <td style="padding:.4375rem .75rem;text-align:center;">@if($page['in_sitemap'])<span style="color:#16a34a;">✓</span>@else<span style="color:#d97706;">✗</span>@endif</td>
                                <td style="padding:.4375rem .75rem;font-size:.75rem;color:#7c3aed;word-break:break-all;">
                                    @if($page['canonical'] && $page['canonical'] !== $page['url']){{ $page['canonical'] }}@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if(count($crawlPages) > 50)<div style="padding:.625rem 1rem;font-size:.8125rem;color:#6b7280;text-align:center;background:#f9fafb;">+ ещё {{ count($crawlPages) - 50 }}</div>@endif
                </div>
                @endif
            </div>
        </div>

        {{-- ══════════════════ TAB: COMPARE ══════════════════ --}}
        <div x-show="activeTab === 'compare'" style="padding:1.25rem;display:flex;flex-direction:column;gap:1rem;">

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;">
                <div style="padding:1rem;border-radius:.75rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.75rem;font-weight:800;color:{{ $coverageColor }};">{{ $coverage }}%</div>
                    <div style="font-size:.75rem;font-weight:600;color:#9ca3af;text-transform:uppercase;">Покрытие</div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:.25rem;">sitemap ∩ crawl</div>
                </div>
                <div style="padding:1rem;border-radius:.75rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.75rem;font-weight:800;color:{{ count($brokenPages) > 0 ? '#ef4444' : '#22c55e' }};">{{ count($brokenPages) }}</div>
                    <div style="font-size:.75rem;font-weight:600;color:#9ca3af;text-transform:uppercase;">Битых</div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:.25rem;">dead + non-200</div>
                </div>
                <div style="padding:1rem;border-radius:.75rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.75rem;font-weight:800;color:{{ count($orphanPages) > 0 ? '#eab308' : '#22c55e' }};">{{ count($orphanPages) }}</div>
                    <div style="font-size:.75rem;font-weight:600;color:#9ca3af;text-transform:uppercase;">Orphans</div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:.25rem;">crawl − sitemap</div>
                </div>
                @php $notReachable = max(0, count(array_filter($sitemapPages, fn($p) => $p['crawl_depth'] === null))); @endphp
                <div style="padding:1rem;border-radius:.75rem;border:1px solid #e5e7eb;text-align:center;">
                    <div style="font-size:1.75rem;font-weight:800;color:{{ $notReachable > 0 ? '#eab308' : '#22c55e' }};">{{ $notReachable }}</div>
                    <div style="font-size:.75rem;font-weight:600;color:#9ca3af;text-transform:uppercase;">Недостижимо</div>
                    <div style="font-size:.75rem;color:#6b7280;margin-top:.25rem;">sitemap − crawl</div>
                </div>
            </div>

            @if(count($insights) > 0)
            <div style="border-radius:.75rem;border:1px solid #e5e7eb;overflow:hidden;">
                <div style="padding:.75rem 1rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:.875rem;font-weight:600;">🧠 Анализ и рекомендации</div>
                @foreach($insights as $insight)
                @php $iIcon = match($insight['type']) { 'danger'=>'🚨','warning'=>'💡','success'=>'✅',default=>'ℹ️' }; $iBg = match($insight['type']) { 'danger'=>'#fef2f2','warning'=>'#fffbeb','success'=>'#f0fdf4',default=>'#f9fafb' }; @endphp
                <div style="display:flex;align-items:flex-start;gap:.625rem;padding:.625rem 1rem;background:{{ $iBg }};border-bottom:1px solid #e5e7eb;">
                    <span style="flex-shrink:0;">{{ $iIcon }}</span>
                    <span style="font-size:.8125rem;color:#374151;">{{ $insight['message'] }}</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- График трендов --}}
            @if(count($trendData) > 1)
            <div style="border-radius:.75rem;border:1px solid #e5e7eb;overflow:hidden;">
                <div style="padding:.75rem 1rem;background:#f9fafb;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:.875rem;font-weight:600;">📈 Тренды ({{ count($trendData) }} прогонов)</span>
                    <div style="display:flex;gap:.375rem;">
                        <button @click="chartMode='issues'" :style="chartMode==='issues'?'padding:.25rem .625rem;border-radius:.375rem;background:#1d4ed8;color:#fff;font-size:.75rem;border:none;cursor:pointer;':'padding:.25rem .625rem;border-radius:.375rem;background:#e5e7eb;color:#374151;font-size:.75rem;border:none;cursor:pointer;'">Проблемы</button>
                        <button @click="chartMode='coverage'" :style="chartMode==='coverage'?'padding:.25rem .625rem;border-radius:.375rem;background:#1d4ed8;color:#fff;font-size:.75rem;border:none;cursor:pointer;':'padding:.25rem .625rem;border-radius:.375rem;background:#e5e7eb;color:#374151;font-size:.75rem;border:none;cursor:pointer;'">Покрытие</button>
                        <button @click="chartMode='pages'" :style="chartMode==='pages'?'padding:.25rem .625rem;border-radius:.375rem;background:#1d4ed8;color:#fff;font-size:.75rem;border:none;cursor:pointer;':'padding:.25rem .625rem;border-radius:.375rem;background:#e5e7eb;color:#374151;font-size:.75rem;border:none;cursor:pointer;'">Страницы</button>
                    </div>
                </div>
                <div style="padding:1rem;"><canvas x-ref="chart" height="120"></canvas></div>
            </div>
            @else
            <div style="padding:2rem;text-align:center;color:#9ca3af;border-radius:.75rem;border:1px solid #e5e7eb;">📊 Для отображения трендов нужно больше прогонов</div>
            @endif

            {{-- Сравнительная таблица --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                @php $notCrawled = array_values(array_filter($sitemapPages, fn($p) => $p['crawl_depth'] === null)); @endphp
                <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden;">
                    <div style="padding:.625rem .875rem;background:#fef2f2;border-bottom:1px solid #e5e7eb;font-size:.8125rem;font-weight:600;color:#991b1b;">📋 В sitemap, но недостижимы ({{ count($notCrawled) }})</div>
                    @if(count($notCrawled) > 0)
                        @foreach(array_slice($notCrawled, 0, 10) as $p)<div style="padding:.375rem .875rem;border-bottom:1px solid #f3f4f6;font-size:.75rem;color:#374151;word-break:break-all;">{{ $p['url'] }}</div>@endforeach
                        @if(count($notCrawled) > 10)<div style="padding:.375rem .875rem;font-size:.75rem;color:#9ca3af;background:#f9fafb;">+ ещё {{ count($notCrawled) - 10 }}</div>@endif
                    @else<div style="padding:1rem;font-size:.8125rem;color:#9ca3af;text-align:center;">Все URL достижимы ✓</div>@endif
                </div>
                <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden;">
                    <div style="padding:.625rem .875rem;background:#fffbeb;border-bottom:1px solid #e5e7eb;font-size:.8125rem;font-weight:600;color:#92400e;">🌐 Только в краулинге, нет в sitemap ({{ count($orphanPages) }})</div>
                    @if(count($orphanPages) > 0)
                        @foreach(array_slice($orphanPages, 0, 10) as $p)<div style="padding:.375rem .875rem;border-bottom:1px solid #f3f4f6;font-size:.75rem;color:#374151;word-break:break-all;">{{ $p['url'] }} <span style="color:#6b7280;">(ур. {{ $p['crawl_depth'] ?? '?' }})</span></div>@endforeach
                        @if(count($orphanPages) > 10)<div style="padding:.375rem .875rem;font-size:.75rem;color:#9ca3af;background:#f9fafb;">+ ещё {{ count($orphanPages) - 10 }}</div>@endif
                    @else<div style="padding:1rem;font-size:.8125rem;color:#9ca3af;text-align:center;">Orphan-страниц нет ✓</div>@endif
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;border-radius:.75rem;background:#f9fafb;border:1px solid #e5e7eb;font-size:.75rem;color:#9ca3af;">
        <span>📊 Sitemap: {{ $sitemapCount }} URL · 🌐 Обход: {{ $crawledCount }} стр. · Глубина: {{ $maxCrawlDepth }}</span>
        <span>🕐 {{ $record->checked_at->format('d.m.Y H:i:s') }}</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
function sitemapAudit(trendData) {
    return {
        activeTab: 'sitemap',
        chartMode: 'issues',
        chart: null,
        tabs: [
            { id: 'sitemap', label: '📋 Sitemap' },
            { id: 'crawl',   label: '🌐 Краулинг' },
            { id: 'compare', label: '🔍 Сравнение и тренды' },
        ],
        trendData,
        init() {
            this.$watch('activeTab', v => { if (v === 'compare') this.$nextTick(() => this.renderChart()); });
            this.$watch('chartMode', () => this.renderChart());
        },
        renderChart() {
            if (!this.$refs.chart || !this.trendData.length) return;
            if (this.chart) { this.chart.destroy(); this.chart = null; }
            const labels = this.trendData.map(d => d.date);
            const datasets = this.chartMode === 'issues' ? [
                { label: 'Мёртвые',   data: this.trendData.map(d => d.dead),    borderColor: '#ef4444', tension: .3, fill: false },
                { label: 'Не-200',    data: this.trendData.map(d => d.non_200), borderColor: '#f97316', tension: .3, fill: false },
                { label: 'Редиректы',data: this.trendData.map(d => d.redirect), borderColor: '#eab308', tension: .3, fill: false },
                { label: 'Orphans',   data: this.trendData.map(d => d.missing), borderColor: '#8b5cf6', tension: .3, fill: false },
            ] : this.chartMode === 'coverage' ? [
                { label: 'Покрытие %', data: this.trendData.map(d => d.coverage), borderColor: '#22c55e', backgroundColor: '#22c55e20', tension: .3, fill: true },
            ] : [
                { label: 'В sitemap',  data: this.trendData.map(d => d.sitemap_count), borderColor: '#3b82f6', tension: .3, fill: false },
                { label: 'Краулинг',   data: this.trendData.map(d => d.crawl_count),   borderColor: '#10b981', tension: .3, fill: false },
            ];
            this.chart = new Chart(this.$refs.chart, {
                type: 'line',
                data: { labels, datasets },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } },
            });
        },
    };
}
</script>
