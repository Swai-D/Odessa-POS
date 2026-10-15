<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Support\Money;
use App\Support\Sms\PhoneNumber;
use Carbon\CarbonInterface;

/**
 * Builds the <RCT> block of a TRA receipt from a sale (field names and rules from the TRA VFD API documentation).
 * Amounts are decimals in major units, always tax-inclusive per item, with one VAT line per tax group.
 */
class FiscalXmlBuilder
{
    /** TAXCODE => VATRATE letter. */
    private const LETTERS = [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E'];

    /** Our payment method => TRA PMTTYPE. */
    private const PAYMENT_TYPES = [
        Payment::CASH => 'CASH',
        Payment::CHEQUE => 'CHEQUE',
        Payment::CARD => 'CCARD',
        Payment::MOBILE_MONEY => 'EMONEY',
        Payment::BANK_TRANSFER => 'EMONEY',
    ];

    public function receipt(Sale $sale, FiscalIdentity $identity, int $gc, int $dc, CarbonInterface $at): string
    {
        if (preg_match('/^\d{9}$/', $identity->tin) !== 1) {
            throw new FiscalException('The shop TIN must be exactly 9 digits.');
        }

        $sale->loadMissing(['items', 'payments', 'customer']);

        $items = '';
        $groups = [];

        foreach ($sale->items as $item) {
            $code = $this->taxCode((float) $item->tax_rate, $identity);
            $letter = self::LETTERS[$code];
            $groups[$letter] ??= ['net' => 0, 'tax' => 0];
            $groups[$letter]['net'] += $item->total - $item->tax;
            $groups[$letter]['tax'] += $item->tax;

            $items .= '<ITEM><ID>'.$this->esc((string) ($item->sku !== '' ? $item->sku : $item->product_id)).'</ID>'
                .'<DESC>'.$this->esc($item->product_name).'</DESC>'
                .'<QTY>'.$this->qty((string) $item->quantity).'</QTY>'
                .'<TAXCODE>'.$code.'</TAXCODE>'
                .'<AMT>'.Money::toMajor($item->total).'</AMT></ITEM>';
        }

        ksort($groups);
        $vat = '';
        foreach ($groups as $letter => $group) {
            $vat .= '<VATRATE>'.$letter.'</VATRATE><NETTAMOUNT>'.Money::toMajor($group['net']).'</NETTAMOUNT><TAXAMOUNT>'.Money::toMajor($group['tax']).'</TAXAMOUNT>';
        }

        $customer = $sale->customer;
        $phone = PhoneNumber::normalize($customer->phone ?? null);

        return '<RCT>'
            .'<DATE>'.$at->format('Y-m-d').'</DATE><TIME>'.$at->format('H:i:s').'</TIME>'
            .'<TIN>'.$identity->tin.'</TIN><REGID>'.$this->esc($identity->regId).'</REGID><EFDSERIAL>'.$this->esc($identity->efdSerial).'</EFDSERIAL>'
            .'<CUSTIDTYPE>6</CUSTIDTYPE><CUSTID></CUSTID>'
            .'<CUSTNAME>'.$this->esc($customer->name ?? '').'</CUSTNAME><MOBILENUM>'.($phone ?? '').'</MOBILENUM>'
            .'<RCTNUM>'.$gc.'</RCTNUM><DC>'.$dc.'</DC><GC>'.$gc.'</GC><ZNUM>'.$at->format('Ymd').'</ZNUM>'
            .'<RCTVNUM>'.$this->esc($identity->receiptCode.$gc).'</RCTVNUM>'
            .'<ITEMS>'.$items.'</ITEMS>'
            .'<TOTALS><TOTALTAXEXCL>'.Money::toMajor($sale->total - $sale->tax_total).'</TOTALTAXEXCL>'
            .'<TOTALTAXINCL>'.Money::toMajor($sale->total).'</TOTALTAXINCL>'
            .'<DISCOUNT>'.Money::toMajor($sale->discount_total).'</DISCOUNT></TOTALS>'
            .'<PAYMENTS>'.$this->payments($sale).'</PAYMENTS>'
            .'<VATTOTALS>'.$vat.'</VATTOTALS>'
            .'</RCT>';
    }

    /** Which TAXCODE a line is reported under: the standard rate is code 1, 0% uses the shop's chosen code. */
    private function taxCode(float $rate, FiscalIdentity $identity): int
    {
        if ($rate <= 0) {
            return $identity->zeroTaxCode;
        }

        if (abs($rate - $identity->standardRate) < 0.001) {
            return 1;
        }

        throw new FiscalException("A product has a tax rate of {$rate}%, which TRA does not accept. Use the standard rate or 0%.");
    }

    private function payments(Sale $sale): string
    {
        $byType = [];

        foreach ($sale->payments as $payment) {
            $type = self::PAYMENT_TYPES[$payment->method] ?? 'CASH';
            $byType[$type] = ($byType[$type] ?? 0) + $payment->amount;
        }

        // What the customer still owes is an invoice amount; TRA wants paid and unpaid parts reported separately.
        if ($sale->balance_due > 0) {
            $byType['INVOICE'] = $sale->balance_due;
        }

        $xml = '';
        foreach ($byType as $type => $amount) {
            $xml .= '<PMTTYPE>'.$type.'</PMTTYPE><PMTAMOUNT>'.Money::toMajor($amount).'</PMTAMOUNT>';
        }

        return $xml;
    }

    private function qty(string $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
    }

    /** Escapes text for XML and drops control characters that TRA's parser rejects. */
    private function esc(string $value): string
    {
        $clean = preg_replace('/[^\P{C}\n\t]/u', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
