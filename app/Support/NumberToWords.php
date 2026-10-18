<?php

namespace App\Support;

/** Amounts in words for legal documents: 1250050 kobo is "Twelve Thousand, Five Hundred Naira and Fifty Kobo Only". */
class NumberToWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];

    private const SCALES = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    public static function naira(int $kobo): string
    {
        $naira = intdiv(max(0, $kobo), 100);
        $rest = max(0, $kobo) % 100;

        $parts = [];
        if ($naira > 0 || $rest === 0) {
            $parts[] = self::integer($naira).' Naira';
        }
        if ($rest > 0) {
            $parts[] = self::integer($rest).' Kobo';
        }

        return implode(' and ', $parts).' Only';
    }

    public static function integer(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $chunks = [];
        for ($scale = 0; $n > 0; $scale++, $n = intdiv($n, 1000)) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                array_unshift($chunks, ['text' => self::belowThousand($chunk).(self::SCALES[$scale] !== '' ? ' '.self::SCALES[$scale] : ''), 'value' => $chunk, 'scale' => $scale]);
            }
        }

        $out = '';
        foreach ($chunks as $i => $chunk) {
            if ($i === 0) {
                $out = $chunk['text'];
            } else {
                // "One Thousand and Five", "One Million, Two Hundred Thousand"
                $out .= ($chunk['scale'] === 0 && $chunk['value'] < 100 ? ' and ' : ', ').$chunk['text'];
            }
        }

        return $out;
    }

    private static function belowThousand(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
            if ($n > 0) {
                $words[] = 'and';
            }
        }
        if ($n >= 20) {
            $ten = self::TENS[intdiv($n, 10)];
            $words[] = $n % 10 ? $ten.'-'.self::ONES[$n % 10] : $ten;
        } elseif ($n > 0) {
            $words[] = self::ONES[$n];
        }

        return implode(' ', $words);
    }
}
