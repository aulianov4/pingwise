<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;

class SelectTranslationTest extends TestCase
{
    public function test_select_no_options_message_is_translated(): void
    {
        app()->setLocale('ru');

        $this->assertSame(
            'Нет доступных вариантов.',
            __('filament-forms::components.select.no_options_message'),
        );
    }
}
