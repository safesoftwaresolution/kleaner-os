<?php

namespace Tests\Unit;

use App\Support\Whatsapp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsappTest extends TestCase
{
    /** @return array<string, array{0: string, 1: ?string}> */
    public static function phones(): array
    {
        return [
            'plain mobile' => ['3001234567', '573001234567'],
            'with spaces' => ['300 123 4567', '573001234567'],
            'with country code and plus' => ['+57 300 123 4567', '573001234567'],
            'with country code' => ['573001234567', '573001234567'],
            'with brackets and dashes' => ['(300) 123-4567', '573001234567'],
            'landline' => ['6012345678', null],
            'too short' => ['30012345', null],
            'empty' => ['', null],
            'foreign number' => ['+1 555 123 4567', null],
            'letters' => ['abc', null],
        ];
    }

    #[DataProvider('phones')]
    public function test_it_normalizes_colombian_mobile_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Whatsapp::normalizePhone($input));
    }

    public function test_it_builds_a_click_to_chat_link_with_an_encoded_message(): void
    {
        $link = Whatsapp::link('300 123 4567', 'Hola, tu carro está listo. ¡Gracias!');

        $this->assertSame(
            'https://wa.me/573001234567?text='.rawurlencode('Hola, tu carro está listo. ¡Gracias!'),
            $link,
        );
    }

    public function test_it_returns_null_when_the_phone_cannot_receive_whatsapp(): void
    {
        $this->assertNull(Whatsapp::link('6012345678', 'Hola'));
        $this->assertNull(Whatsapp::link(null, 'Hola'));
    }
}
