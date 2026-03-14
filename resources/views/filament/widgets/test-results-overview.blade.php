<x-filament-widgets::widget>
    <x-filament::section heading="Результаты тестов">
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            @forelse($results as $result)
                @php
                    $iconColor = match(true) {
                        ! $result['is_enabled'] => '#9ca3af',
                        $result['status'] === 'success' => '#22c55e',
                        $result['status'] === 'warning' => '#eab308',
                        $result['status'] === 'failed' => '#ef4444',
                        default => '#9ca3af',
                    };
                    $statusLabel = match($result['status'] ?? null) {
                        'success' => 'Успешно',
                        'warning' => 'Предупреждение',
                        'failed' => 'Ошибка',
                        default => null,
                    };
                    $badgeBg = match($result['status'] ?? null) {
                        'success' => '#dcfce7',
                        'warning' => '#fef9c3',
                        'failed' => '#fee2e2',
                        default => '#f3f4f6',
                    };
                    $badgeColor = match($result['status'] ?? null) {
                        'success' => '#15803d',
                        'warning' => '#a16207',
                        'failed' => '#b91c1c',
                        default => '#4b5563',
                    };
                @endphp
                <div style="border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden;">
                    {{-- Заголовок: иконка + название + бейдж — всё в одну строку --}}
                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <span style="display: inline-flex; flex-shrink: 0; width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};">
                            @if(! $result['is_enabled'])
                                <x-heroicon-o-minus-circle style="width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};" />
                            @elseif($result['status'] === 'success')
                                <x-heroicon-o-check-circle style="width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};" />
                            @elseif($result['status'] === 'warning')
                                <x-heroicon-o-exclamation-triangle style="width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};" />
                            @elseif($result['status'] === 'failed')
                                <x-heroicon-o-x-circle style="width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};" />
                            @else
                                <x-heroicon-o-question-mark-circle style="width: 1.25rem; height: 1.25rem; color: {{ $iconColor }};" />
                            @endif
                        </span>
                        <span style="font-size: 0.875rem; font-weight: 600; flex: 1;">{{ $result['name'] }}</span>
                        @if(! $result['is_enabled'])
                            <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.375rem; background: #f3f4f6; color: #4b5563;">Отключен</span>
                        @elseif($statusLabel)
                            <span style="display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.375rem; background: {{ $badgeBg }}; color: {{ $badgeColor }};">{{ $statusLabel }}</span>
                        @endif
                    </div>

                    {{-- Тело карточки --}}
                    <div style="padding: 0.75rem 1rem;">
                        @if($result['message'])
                            <p style="font-size: 0.875rem; color: #4b5563; margin: 0;">{{ $result['message'] }}</p>
                        @endif

                        @if($result['value'] && is_array($result['value']))
                            <div style="display: flex; flex-wrap: wrap; gap: 0.25rem 1rem; margin-top: 0.5rem;">
                                @foreach($result['value'] as $key => $val)
                                    @if(! is_array($val) && ! is_null($val))
                                        <span style="font-size: 0.75rem; color: #6b7280;">
                                            <span style="font-weight: 500;">{{ str_replace('_', ' ', ucfirst($key)) }}:</span>
                                            @if(is_bool($val))
                                                {{ $val ? 'Да' : 'Нет' }}
                                            @else
                                                {{ $val }}
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        @if($result['checked_at'])
                            <p style="font-size: 0.75rem; color: #9ca3af; margin: 0.375rem 0 0;">
                                Проверено: {{ $result['checked_at']->format('d.m.Y H:i:s') }}
                                ({{ $result['checked_at']->diffForHumans() }})
                            </p>
                        @elseif($result['is_enabled'])
                            <p style="font-size: 0.75rem; color: #9ca3af; margin: 0.375rem 0 0;">Ещё не проверялось</p>
                        @endif
                    </div>
                </div>
            @empty
                <div style="text-align: center; font-size: 0.875rem; color: #6b7280;">
                    Нет настроенных тестов
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

