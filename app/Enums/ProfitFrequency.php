<?php

namespace App\Enums;

/** How often an investor's share of the profit is paid out. Chosen when subscribing; fixed for the life of the policy. */
enum ProfitFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Annually = 'annually';
    case AtMaturity = 'at_maturity';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half-yearly',
            self::Annually => 'Yearly',
            self::AtMaturity => 'One-Off Payment',
        };
    }

    /** What the investor is told when choosing. */
    public function description(): string
    {
        return match ($this) {
            self::Monthly => 'Your share is calculated every month and paid by the end of that month.',
            self::Quarterly => 'Your share builds up and is paid every 3 months from your start date, with anything left paid at maturity.',
            self::HalfYearly => 'Your share builds up and is paid every 6 months from your start date, with anything left paid at maturity.',
            self::Annually => 'Your share builds up and is paid once a year from your start date, with anything left paid at maturity.',
            self::AtMaturity => 'Your whole profit share is paid in one payment, at the end of the term.',
        };
    }

    /**
     * The options offered on a plan of this length: the interval must be shorter than the plan (at least one payment
     * before maturity) and must divide it evenly, so every payment date lands exactly on a payment month and the last
     * one is maturity itself, with no short final period. One-Off is always offered.
     * 3 months: monthly, one-off. 6: + quarterly. 9: monthly, quarterly, one-off. 12: + half-yearly.
     *
     * @return list<self>
     */
    public static function availableFor(int $months): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->intervalMonths() === null || ($f->intervalMonths() < $months && $months % $f->intervalMonths() === 0)));
    }

    public function isAvailableFor(int $months): bool
    {
        return in_array($this, self::availableFor($months), true);
    }

    /** Months between payments; null for a single payment at maturity. */
    public function intervalMonths(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Annually => 12,
            self::AtMaturity => null,
        };
    }
}
