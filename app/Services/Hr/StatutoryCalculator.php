<?php

namespace App\Services\Hr;

use App\Exceptions\BusinessRuleException;

/**
 * Pension, NHF and PAYE for one employee for one month. Pure arithmetic over a rule set, no database access.
 *
 * PAYE uses the cumulative (year-to-date) method, which suits pay that varies month to month (commission):
 *
 *   taxable to date = gross to date - employee pension to date - NHF to date - rent relief for the months elapsed
 *   tax to date     = the annual bands scaled to the months elapsed (band width x months/12) applied to that
 *   PAYE this month = tax to date - PAYE already withheld this tax year   (never below zero)
 *
 * so an uneven month corrects itself within the year instead of being taxed as if it repeated 12 times.
 * Money is integer kobo; percentages are basis points, rounded half up.
 *
 * Rule shape (see StatutoryRuleSet):
 *   paye:    bands [{width: kobo|null (rest), rate_bps}], rent_relief_bps, rent_relief_cap (kobo, per year)
 *   pension: employee_bps, employer_bps          (on basic + housing + transport)
 *   nhf:     employee_bps                        (on basic)
 *   remit:   paye_day, nhf_day (of the following month), pension_working_days (after payday)
 */
class StatutoryCalculator
{
    /**
     * @param  array{basic: int, housing: int, transport: int, allowances: int, commission: int, pension_exempt: bool, nhf_applies: bool, paye_exempt: bool, annual_rent: int, months: int, ytd: array{gross: int, pension: int, nhf: int, paye: int}}  $in
     * @return array{gross: int, pensionable: int, pension_employee: int, pension_employer: int, nhf: int, taxable: int, paye: int}
     */
    public function calculate(array $rules, array $in): array
    {
        $gross = $in['basic'] + $in['housing'] + $in['transport'] + $in['allowances'] + $in['commission'];
        $pensionable = $in['basic'] + $in['housing'] + $in['transport'];

        $pensionEmployee = $in['pension_exempt'] ? 0 : self::bps($pensionable, $rules['pension']['employee_bps']);
        $pensionEmployer = $in['pension_exempt'] ? 0 : self::bps($pensionable, $rules['pension']['employer_bps']);
        $nhf = $in['nhf_applies'] ? self::bps($in['basic'], $rules['nhf']['employee_bps']) : 0;

        $paye = 0;
        if (! $in['paye_exempt']) {
            $months = max(1, min(12, $in['months']));
            $rentRelief = $in['annual_rent'] > 0
                ? intdiv(min(self::bps($in['annual_rent'], $rules['paye']['rent_relief_bps']), $rules['paye']['rent_relief_cap']) * $months, 12)
                : 0;
            $taxableToDate = max(0, $in['ytd']['gross'] + $gross - $in['ytd']['pension'] - $pensionEmployee - $in['ytd']['nhf'] - $nhf - $rentRelief);
            $paye = max(0, $this->taxOn($taxableToDate, $rules['paye']['bands'], $months) - $in['ytd']['paye']);
        }

        return [
            'gross' => $gross,
            'pensionable' => $pensionable,
            'pension_employee' => $pensionEmployee,
            'pension_employer' => $pensionEmployer,
            'nhf' => $nhf,
            'taxable' => max(0, $gross - $pensionEmployee - $nhf),
            'paye' => $paye,
        ];
    }

    /** Tax on an amount accumulated over $months of the year, with every band narrowed to months/12 of its annual width. */
    public function taxOn(int $amount, array $bands, int $months): int
    {
        $tax = 0;
        $remaining = $amount;
        foreach ($bands as $band) {
            if ($remaining <= 0) {
                break;
            }
            $width = $band['width'] === null ? $remaining : intdiv($band['width'] * $months, 12);
            $slice = min($remaining, $width);
            $tax += self::bps($slice, $band['rate_bps']);
            $remaining -= $slice;
        }

        return $tax;
    }

    public static function bps(int $amount, int $bps): int
    {
        return intdiv($amount * $bps + 5_000, 10_000);
    }

    /** Throws a message a person can act on when a rule set is not safe to use. */
    public static function assertValid(array $rules): void
    {
        $bands = $rules['paye']['bands'] ?? [];
        if (count($bands) < 1 || count($bands) > 10) {
            throw new BusinessRuleException('PAYE needs between 1 and 10 tax bands.');
        }
        foreach ($bands as $i => $band) {
            $last = $i === count($bands) - 1;
            $width = $band['width'] ?? null;
            if ($last && $width !== null) {
                throw new BusinessRuleException('The last tax band must have no upper limit ("the rest").');
            }
            if (! $last && (! is_int($width) || $width <= 0)) {
                throw new BusinessRuleException('Every band except the last needs a width above zero.');
            }
            if (! is_int($band['rate_bps'] ?? null) || $band['rate_bps'] < 0 || $band['rate_bps'] > 10_000) {
                throw new BusinessRuleException('Tax rates must be between 0% and 100%.');
            }
        }
        foreach ([['paye', 'rent_relief_bps', 10_000], ['pension', 'employee_bps', 5_000], ['pension', 'employer_bps', 5_000], ['nhf', 'employee_bps', 5_000]] as [$group, $key, $max]) {
            $v = $rules[$group][$key] ?? null;
            if (! is_int($v) || $v < 0 || $v > $max) {
                throw new BusinessRuleException("Rule {$group}.{$key} is missing or out of range.");
            }
        }
        if (! is_int($rules['paye']['rent_relief_cap'] ?? null) || $rules['paye']['rent_relief_cap'] < 0) {
            throw new BusinessRuleException('The rent relief cap must be zero or more.');
        }
        foreach ([['paye_day', 1, 28], ['nhf_day', 1, 31], ['pension_working_days', 0, 31]] as [$key, $min, $max]) {
            $v = $rules['remit'][$key] ?? null;
            if (! is_int($v) || $v < $min || $v > $max) {
                throw new BusinessRuleException("Remittance setting {$key} must be between {$min} and {$max}.");
            }
        }
    }
}
