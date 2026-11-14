<?php

declare(strict_types=1);

namespace OpenSpout\Common\Entity;

use OpenSpout\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class HyperlinkTest extends TestCase
{
    public function testToUrl(): void
    {
        $hyperlink = Hyperlink::toUrl('https://example.com/?a=1&b=2');

        self::assertSame('https://example.com/?a=1&b=2', $hyperlink->url);
        self::assertNull($hyperlink->sheetName);
        self::assertNull($hyperlink->reference);
        self::assertSame('https://example.com/?a=1&b=2', (string) $hyperlink);
    }

    public function testWithTooltip(): void
    {
        $hyperlink = Hyperlink::toCell('A42', 'Orders');
        $hyperlinkWithTooltip = $hyperlink->withTooltip('Show order');

        self::assertNull($hyperlink->tooltip);
        self::assertSame('Show order', $hyperlinkWithTooltip->tooltip);
        self::assertSame('Orders', $hyperlinkWithTooltip->sheetName);
        self::assertSame('A42', $hyperlinkWithTooltip->reference);
        self::assertSame("#'Orders'!A42", (string) $hyperlinkWithTooltip);
        self::assertSame('https://example.com', Hyperlink::toUrl('https://example.com')->withTooltip('Open')->url);
    }

    #[DataProvider('provideToCellCases')]
    public function testToCell(string $expected, string $cellReference, ?string $sheetName): void
    {
        $hyperlink = Hyperlink::toCell($cellReference, $sheetName);

        self::assertNull($hyperlink->url);
        self::assertSame($sheetName, $hyperlink->sheetName);
        self::assertSame($cellReference, $hyperlink->reference);
        self::assertSame($expected, (string) $hyperlink);
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function provideToCellCases(): iterable
    {
        yield 'same sheet' => ['#A42', 'A42', null];

        yield 'other sheet' => ["#'Orders'!A42", 'A42', 'Orders'];

        yield 'sheet name with spaces' => ["#'Order Details'!A42", 'A42', 'Order Details'];

        yield 'sheet name with quote' => ["#'O''Brien'!B2", 'B2', "O'Brien"];

        yield 'range' => ["#'Orders'!B2:D10", 'B2:D10', 'Orders'];

        yield 'absolute reference' => ['#$A$1', '$A$1', null];

        yield 'lowercase' => ['#xfd1048576', 'xfd1048576', null];
    }

    #[DataProvider('provideGetQuotedSheetNameCases')]
    public function testGetQuotedSheetName(?string $expected, ?string $sheetName): void
    {
        self::assertSame($expected, Hyperlink::toCell('A1', $sheetName)->getQuotedSheetName());
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideGetQuotedSheetNameCases(): iterable
    {
        yield 'same sheet' => [null, null];

        yield 'simple' => ["'Orders'", 'Orders'];

        yield 'spaces' => ["'Order Details'", 'Order Details'];

        yield 'quote' => ["'O''Brien'", "O'Brien"];

        yield 'exclamation mark' => ["'A!B'", 'A!B'];
    }

    /**
     * @param array{?string, ?string, ?string} $expectedValues url, sheet name and reference
     */
    #[DataProvider('provideFromStringCases')]
    public function testFromString(string $target, array $expectedValues, string $expectedString): void
    {
        $hyperlink = Hyperlink::fromString($target);

        self::assertSame($expectedValues, [$hyperlink->url, $hyperlink->sheetName, $hyperlink->reference]);
        self::assertSame($expectedString, (string) $hyperlink);
        self::assertNull($hyperlink->tooltip);
    }

    /**
     * @return iterable<string, array{string, array{?string, ?string, ?string}, string}>
     */
    public static function provideFromStringCases(): iterable
    {
        yield 'url' => ['https://example.com/#!/page', ['https://example.com/#!/page', null, null], 'https://example.com/#!/page'];

        yield 'cell' => ['#A1', [null, null, 'A1'], '#A1'];

        yield 'defined name' => ['#MyRange', [null, null, 'MyRange'], '#MyRange'];

        yield 'empty location' => ['#', [null, null, ''], '#'];

        yield 'unquoted sheet' => ['#Sheet2!A1', [null, 'Sheet2', 'A1'], "#'Sheet2'!A1"];

        yield 'quoted sheet' => ["#'Order Details'!B2:D10", [null, 'Order Details', 'B2:D10'], "#'Order Details'!B2:D10"];

        yield 'escaped quote' => ["#'O''Brien'!A1", [null, "O'Brien", 'A1'], "#'O''Brien'!A1"];

        yield 'unquoted sheet with quote' => ["#O'Brien!A1", [null, "O'Brien", 'A1'], "#'O''Brien'!A1"];

        yield 'exclamation mark in sheet' => ["#'A!B'!C1", [null, 'A!B', 'C1'], "#'A!B'!C1"];

        yield 'only leading quote' => ["#'Sheet!A1", [null, "'Sheet", 'A1'], "#'''Sheet'!A1"];

        yield 'only trailing quote' => ["#Sheet'!A1", [null, "Sheet'", 'A1'], "#'Sheet'''!A1"];

        yield 'single quote as sheet' => ["#'!A1", [null, "'", 'A1'], "#''''!A1"];

        yield 'empty quoted sheet' => ["#''!A1", [null, '', 'A1'], "#''!A1"];

        yield 'empty reference' => ['#Sheet!', [null, 'Sheet', ''], "#'Sheet'!"];
    }

    public function testToSheet(): void
    {
        self::assertSame("#'Order Details'!A1", (string) Hyperlink::toSheet('Order Details'));
    }

    #[DataProvider('provideToCellShouldThrowOnInvalidCellReferenceCases')]
    public function testToCellShouldThrowOnInvalidCellReference(string $cellReference): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid cell reference: {$cellReference}");

        Hyperlink::toCell($cellReference, 'Orders');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideToCellShouldThrowOnInvalidCellReferenceCases(): iterable
    {
        yield [''];

        yield ['A'];

        yield ['42'];

        yield ['ABCD1'];

        yield ['A1:'];

        yield ['Orders!A1'];

        yield [' A1'];

        yield ['A1 '];

        yield ["A1\n"];

        yield ['A1:B2:C3'];
    }
}
