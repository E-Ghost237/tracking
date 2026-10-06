<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\Content\MarkdownRenderer;
use App\Services\Files\HeuristicScanner;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Pricing\QuoteCalculator;
use App\Services\SequenceGenerator;
use App\Services\Settings;
use App\Support\Geo;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainRulesTest extends TestCase
{
    #[Test]
    public function tracking_numbers_carry_a_valid_luhn_check_digit(): void
    {
        $this->assertSame(3, SequenceGenerator::luhnCheckDigit('7992739871'));
        $this->assertTrue(SequenceGenerator::isValidOwnTrackingNumber('CV-AIR-100016'));
        $this->assertFalse(SequenceGenerator::isValidOwnTrackingNumber('CV-AIR-100017'));
        $this->assertFalse(SequenceGenerator::isValidOwnTrackingNumber('CV-BUS-100016'));
    }

    #[Test]
    public function chargeable_weight_is_the_larger_of_actual_and_volumetric(): void
    {
        $calculator = new QuoteCalculator($this->createMock(Settings::class));

        [$actual, $volumetric, $chargeable] = $calculator->weights([
            ['weight_kg' => 2, 'length_cm' => 50, 'width_cm' => 40, 'height_cm' => 40],
            ['weight_kg' => 10, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10],
        ]);

        $this->assertSame(12.0, $actual);
        $this->assertSame(16.2, $volumetric);
        $this->assertSame(26.0, $chargeable);
    }

    #[Test]
    public function only_documented_order_transitions_are_allowed(): void
    {
        $this->assertTrue(OrderStatus::UnderReview->canTransitionTo(OrderStatus::Paid));
        $this->assertFalse(OrderStatus::AwaitingPayment->canTransitionTo(OrderStatus::Paid));
        $this->assertFalse(OrderStatus::Expired->canTransitionTo(OrderStatus::MethodSelected));
        $this->assertFalse(OrderStatus::Refunded->canTransitionTo(OrderStatus::Paid));
        $this->assertFalse(OrderStatus::UnderReview->acceptsMethodSelection());
    }

    #[Test]
    public function template_variables_cannot_inject_html_or_links(): void
    {
        $renderer = new TemplateRenderer(new MarkdownRenderer);

        $html = $renderer->render('Hi {{name}}', "Hello {{name}}\n\n[Pay]({{pay_url}})", [
            'name' => '<script>alert(1)</script> [click](javascript:alert(1))',
            'pay_url' => 'https://example.test/pay',
        ])['html'];

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('href="https://example.test/pay"', $html);
    }

    #[Test]
    public function cms_markdown_escapes_raw_html_and_unsafe_links(): void
    {
        $html = (new MarkdownRenderer)->toHtml("<img src=x onerror=alert(1)>\n\n[x](javascript:alert(1))");

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uploads(): array
    {
        return [
            'windows executable' => ["MZ\x90\x00binary", false],
            'elf binary' => ["\x7fELF\x02\x01", false],
            'php script' => ['GIF89a<?php echo 1; ?>', false],
            'eicar' => ['X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*', false],
            'pdf with javascript' => ["%PDF-1.4\n1 0 obj << /OpenAction << /JS (app.alert(1)) >> >>", false],
            'plain pdf' => ["%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\nstream\n/JS inside a stream body\nendstream", true],
            'plain image bytes' => ["\x89PNG\r\n\x1a\nrandom pixel data", true],
        ];
    }

    #[Test]
    #[DataProvider('uploads')]
    public function the_fallback_scanner_rejects_active_content(string $bytes, bool $clean): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scan');
        file_put_contents($path, $bytes);

        $this->assertSame($clean, (new HeuristicScanner)->scan($path)['clean']);
        unlink($path);
    }

    #[Test]
    public function money_and_geography_helpers_are_exact(): void
    {
        $this->assertSame(1150, Money::convert(1250, 'EUR', 0.92));
        $this->assertSame(605, Money::convert(100, 'XAF', 605));
        $this->assertSame(1234, Money::fromMajor('12.34'));
        $this->assertTrue(Geo::sameLandmass('CM', 'FR'));
        $this->assertFalse(Geo::sameLandmass('CM', 'US'));
        $this->assertSame('europe', Geo::networkRegion('BE'));
        $this->assertSame('us', Geo::networkRegion('US'));
        $this->assertEqualsWithDelta(5030, Geo::distanceKm(4.0511, 9.7679, 48.8566, 2.3522), 30);
    }
}
