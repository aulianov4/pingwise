<div>
    @if ($channel === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            После сохранения появится код для привязки группы.
        </p>
    @elseif ($channel->isConnected())
        <div class="rounded-xl border border-success-600/30 bg-success-500/10 px-4 py-3">
            <div class="text-xs font-medium uppercase tracking-wide text-success-600 dark:text-success-400">Подключено</div>
            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                {{ $channel->connectedChatTitle() ?? ('чат '.$channel->telegram_chat_id) }}
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 md:items-start">
            <div class="flex flex-col gap-3">
                <div class="text-sm font-medium text-gray-950 dark:text-white">
                    @if ($channel->hasActiveConnectToken())
                        Ожидает команду /connect в группе
                    @else
                        Код истёк — выпустите новый
                    @endif
                </div>

                @if (filled($channel->connect_token))
                    <div
                        x-data="{ copied: false }"
                        class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <code class="font-mono text-xl tracking-[0.2em] text-gray-950 dark:text-white">{{ $channel->connect_token }}</code>
                        <button
                            type="button"
                            class="fi-btn fi-size-sm fi-btn-color-gray fi-outlined rounded-lg px-3 py-1.5 text-sm font-medium"
                            x-on:click="
                                window.navigator.clipboard.writeText(@js($channel->connect_token));
                                copied = true;
                                setTimeout(() => copied = false, 1500);
                            "
                        >
                            <span x-show="! copied">Копировать</span>
                            <span x-show="copied" x-cloak>Скопировано</span>
                        </button>
                    </div>
                @endif
            </div>

            <ol class="flex list-decimal flex-col gap-1.5 ps-5 text-sm text-gray-500 dark:text-gray-400">
                <li>Добавьте бота <span class="font-medium text-primary-600 dark:text-primary-400">@pingwise_bot</span> в группу. Админом делать не нужно.</li>
                <li>Отправьте в группу <span class="font-mono">/connect@pingwise_bot {{ $channel->connect_token }}</span> — код обязателен, без него бот не привяжет канал.</li>
                <li>Бот ответит, что канал привязан к проекту.</li>
            </ol>
        </div>
    @endif
</div>
