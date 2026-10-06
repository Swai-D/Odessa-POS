<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Sales\Models\Sale;
use App\Support\Money;

/** Builds the raw ESC/POS bytes of a sale receipt for a 58 mm (32 column) or 80 mm (48 column) thermal printer. */
class EscPosReceipt
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    public function build(Sale $sale, string $business, string $footer, int $paperWidth = 80, bool $cut = true): string
    {
        $cols = $paperWidth === 58 ? 32 : 48;
        $fmt = fn (int $amount): string => Money::format($amount, $sale->currency);
        $rule = str_repeat('-', $cols)."\n";

        $out = self::ESC.'@'; // initialise
        $out .= self::ESC.'a'."\x01"; // centre
        $out .= self::ESC.'E'."\x01".self::GS.'!'."\x11".$this->text($business)."\n"; // bold, double size
        $out .= self::GS.'!'."\x00".self::ESC.'E'."\x00";
        $out .= $this->text($sale->number)."\n".date('Y-m-d H:i', (int) strtotime((string) $sale->sold_at))."\n";
        $out .= self::ESC.'a'."\x00"; // left
        $out .= $rule;

        foreach ($sale->items as $item) {
            $qty = rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.');
            $out .= $this->text(mb_strimwidth($item->product_name, 0, $cols, '', 'UTF-8'))."\n";
            $out .= $this->row("  {$qty} x ".$fmt($item->unit_price), $fmt($item->total), $cols);
        }

        $out .= $rule;
        $out .= $this->row(__('pos.sales.subtotal'), $fmt($sale->subtotal), $cols);
        if ($sale->discount_total > 0) {
            $out .= $this->row(__('pos.sales.discount'), '-'.$fmt($sale->discount_total), $cols);
        }
        $out .= $this->row(__('pos.sales.tax'), $fmt($sale->tax_total), $cols);
        $out .= self::ESC.'E'."\x01".$this->row(__('pos.sales.total'), $fmt($sale->total), $cols).self::ESC.'E'."\x00";
        $out .= $this->row(__('pos.sales.paid'), $fmt($sale->amount_paid), $cols);
        if ($sale->change_given > 0) {
            $out .= $this->row(__('pos.sales.change'), $fmt($sale->change_given), $cols);
        }
        if ($sale->returned_total > 0) {
            $out .= $this->row(__('pos.sales.returned'), '-'.$fmt($sale->returned_total), $cols);
        }
        if ($sale->balance_due > 0) {
            $out .= $this->row(__('pos.sales.balance'), $fmt($sale->balance_due), $cols);
        }

        $out .= $rule;
        $out .= self::ESC.'a'."\x01".$this->text($footer !== '' ? $footer : __('pos.sales.thank_you'))."\n";
        $out .= "\n\n\n";

        if ($cut) {
            $out .= self::GS.'V'."\x42\x00"; // feed and partial cut
        }

        return $out;
    }

    private function row(string $left, string $right, int $cols): string
    {
        $left = $this->text($left);
        $right = $this->text($right);
        $gap = max(1, $cols - strlen($left) - strlen($right));

        return $left.str_repeat(' ', $gap).$right."\n";
    }

    /** Printers use single-byte code pages; keep to plain ASCII so nothing prints as garbage. */
    private function text(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return preg_replace('/[^\x20-\x7E]/', '', $ascii === false ? $value : $ascii) ?? '';
    }
}
