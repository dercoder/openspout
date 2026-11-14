<?php

declare(strict_types=1);

namespace OpenSpout\Common\Entity;

use OpenSpout\Common\Exception\InvalidArgumentException;
use Stringable;

/**
 * Target of a cell hyperlink: an external URL, or a cell or sheet within the workbook.
 *
 * As a string, links within the workbook are represented as "#" followed by the location,
 * e.g. "#'Order Details'!A42", the same format as used by Excel's HYPERLINK() function.
 */
final readonly class Hyperlink implements Stringable
{
    /**
     * @param null|string $url       External URL, null for a link within the workbook
     * @param null|string $sheetName Sheet of a link within the workbook, null for the sheet of the linking cell
     * @param null|string $reference Cell, range or defined name of a link within the workbook
     * @param null|string $tooltip   Text shown when hovering the link
     */
    private function __construct(
        public ?string $url = null,
        public ?string $sheetName = null,
        public ?string $reference = null,
        public ?string $tooltip = null,
    ) {}

    public function __toString(): string
    {
        if (null !== $this->url) {
            return $this->url;
        }

        $quotedSheetName = $this->getQuotedSheetName();

        return null === $quotedSheetName ? "#{$this->reference}" : "#{$quotedSheetName}!{$this->reference}";
    }

    public function withTooltip(string $tooltip): self
    {
        return new self($this->url, $this->sheetName, $this->reference, $tooltip);
    }

    /**
     * Sheet name as written in Excel and OpenDocument references, e.g. 'O''Brien'.
     * Quoting is always valid, a quote inside the name is escaped by doubling it.
     */
    public function getQuotedSheetName(): ?string
    {
        if (null === $this->sheetName) {
            return null;
        }

        $escapedSheetName = str_replace("'", "''", $this->sheetName);

        return "'{$escapedSheetName}'";
    }

    public static function toUrl(string $url): self
    {
        return new self(url: $url);
    }

    /**
     * @param string      $cellReference Cell or range to link to, e.g. "A42" or "B2:D10"
     * @param null|string $sheetName     Sheet to link to, null for the sheet of the linking cell
     *
     * @throws InvalidArgumentException If the cell reference is invalid
     */
    public static function toCell(string $cellReference, ?string $sheetName = null): self
    {
        if (1 !== preg_match('/^\$?[A-Z]{1,3}\$?\d+(:\$?[A-Z]{1,3}\$?\d+)?$/iD', $cellReference)) {
            throw new InvalidArgumentException("Invalid cell reference: {$cellReference}");
        }

        return new self(sheetName: $sheetName, reference: $cellReference);
    }

    public static function toSheet(string $sheetName): self
    {
        return self::toCell('A1', $sheetName);
    }

    /**
     * Creates a hyperlink from its string representation: a URL, or "#" followed by a location
     * within the workbook, e.g. "#A42", "#Orders!A42", "#'Order Details'!A42" or "#MyRange".
     */
    public static function fromString(string $target): self
    {
        if (!str_starts_with($target, '#')) {
            return new self(url: $target);
        }

        $location = substr($target, 1);

        // sheet names may contain "!" too, so the last one separates the sheet from the reference
        $separatorPosition = strrpos($location, '!');
        if (false === $separatorPosition) {
            return new self(reference: $location);
        }

        $sheetName = substr($location, 0, $separatorPosition);
        if (\strlen($sheetName) > 1 && str_starts_with($sheetName, "'") && str_ends_with($sheetName, "'")) {
            $sheetName = str_replace("''", "'", substr($sheetName, 1, -1));
        }

        return new self(sheetName: $sheetName, reference: substr($location, $separatorPosition + 1));
    }
}
