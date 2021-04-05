<?php

namespace PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Constant\Periodic;

use PhpOffice\PhpSpreadsheet\Calculation\Functions;

class Interest
{
    private const FINANCIAL_MAX_ITERATIONS = 128;

    private const FINANCIAL_PRECISION = 1.0e-08;

    /**
     * IPMT.
     *
     * Returns the interest payment for a given period for an investment based on periodic, constant payments
     *         and a constant interest rate.
     *
     * Excel Function:
     *        IPMT(rate,per,nper,pv[,fv][,type])
     *
     * @param float $interestRate Interest rate per period
     * @param int $period Period for which we want to find the interest
     * @param int $numberOfPeriods Number of periods
     * @param float $presentValue Present Value
     * @param float $futureValue Future Value
     * @param int $type Payment type: 0 = at the end of each period, 1 = at the beginning of each period
     *
     * @return float|string
     */
    public static function IPMT($interestRate, $period, $numberOfPeriods, $presentValue, $futureValue = 0, $type = 0)
    {
        $interestRate = Functions::flattenSingleValue($interestRate);
        $period = (int) Functions::flattenSingleValue($period);
        $numberOfPeriods = (int) Functions::flattenSingleValue($numberOfPeriods);
        $presentValue = Functions::flattenSingleValue($presentValue);
        $futureValue = Functions::flattenSingleValue($futureValue);
        $type = (int) Functions::flattenSingleValue($type);

        // Validate parameters
        if ($type != 0 && $type != 1) {
            return Functions::NAN();
        }
        if ($period <= 0 || $period > $numberOfPeriods) {
            return Functions::NAN();
        }

        // Calculate
        $interestAndPrincipal = new InterestAndPrincipal(
            $interestRate,
            $period,
            $numberOfPeriods,
            $presentValue,
            $futureValue,
            $type
        );

        return $interestAndPrincipal->interest();
    }

    /**
     * ISPMT.
     *
     * Returns the interest payment for an investment based on an interest rate and a constant payment schedule.
     *
     * Excel Function:
     *     =ISPMT(interest_rate, period, number_payments, pv)
     *
     * interest_rate is the interest rate for the investment
     *
     * period is the period to calculate the interest rate.  It must be betweeen 1 and number_payments.
     *
     * number_payments is the number of payments for the annuity
     *
     * pv is the loan amount or present value of the payments
     */
    public static function ISPMT($interestRate, $period, $numberPeriods, $principleRemaining)
    {
        // Return value
        $returnValue = 0;

        // Calculate
        $principlePayment = ($principleRemaining * 1.0) / ($numberPeriods * 1.0);
        for ($i = 0; $i <= $period; ++$i) {
            $returnValue = $interestRate * $principleRemaining * -1;
            $principleRemaining -= $principlePayment;
            // principle needs to be 0 after the last payment, don't let floating point screw it up
            if ($i == $numberPeriods) {
                $returnValue = 0.0;
            }
        }

        return $returnValue;
    }

    /**
     * RATE.
     *
     * Returns the interest rate per period of an annuity.
     * RATE is calculated by iteration and can have zero or more solutions.
     * If the successive results of RATE do not converge to within 0.0000001 after 20 iterations,
     * RATE returns the #NUM! error value.
     *
     * Excel Function:
     *        RATE(nper,pmt,pv[,fv[,type[,guess]]])
     *
     * @param mixed $numberOfPeriods The total number of payment periods in an annuity
     * @param mixed $payment The payment made each period and cannot change over the life of the annuity.
     *                           Typically, pmt includes principal and interest but no other fees or taxes.
     * @param mixed $presentValue The present value - the total amount that a series of future payments is worth now
     * @param mixed $futureValue The future value, or a cash balance you want to attain after the last payment is made.
     *                               If fv is omitted, it is assumed to be 0 (the future value of a loan,
     *                               for example, is 0).
     * @param mixed $type A number 0 or 1 and indicates when payments are due:
     *                      0 or omitted    At the end of the period.
     *                      1               At the beginning of the period.
     * @param mixed $guess Your guess for what the rate will be.
     *                          If you omit guess, it is assumed to be 10 percent.
     *
     * @return float|string
     */
    public static function RATE($numberOfPeriods, $payment, $presentValue, $futureValue = 0.0, $type = 0, $guess = 0.1)
    {
        $numberOfPeriods = (int) Functions::flattenSingleValue($numberOfPeriods);
        $payment = Functions::flattenSingleValue($payment);
        $presentValue = Functions::flattenSingleValue($presentValue);
        $futureValue = ($futureValue === null) ? 0.0 : Functions::flattenSingleValue($futureValue);
        $type = ($type === null) ? 0 : (int) Functions::flattenSingleValue($type);
        $guess = ($guess === null) ? 0.1 : Functions::flattenSingleValue($guess);

        $rate = $guess;
        // rest of code adapted from python/numpy
        $close = false;
        $iter = 0;
        while (!$close && $iter < self::FINANCIAL_MAX_ITERATIONS) {
            $nextdiff = self::rateNextGuess($rate, $numberOfPeriods, $payment, $presentValue, $futureValue, $type);
            if (!is_numeric($nextdiff)) {
                break;
            }
            $rate1 = $rate - $nextdiff;
            $close = abs($rate1 - $rate) < self::FINANCIAL_PRECISION;
            ++$iter;
            $rate = $rate1;
        }

        return $close ? $rate : Functions::NAN();
    }

    private static function rateNextGuess($rate, $nper, $pmt, $pv, $fv, $type)
    {
        if ($rate == 0) {
            return Functions::NAN();
        }
        $tt1 = ($rate + 1) ** $nper;
        $tt2 = ($rate + 1) ** ($nper - 1);
        $numerator = $fv + $tt1 * $pv + $pmt * ($tt1 - 1) * ($rate * $type + 1) / $rate;
        $denominator = $nper * $tt2 * $pv - $pmt * ($tt1 - 1) * ($rate * $type + 1) / ($rate * $rate)
            + $nper * $pmt * $tt2 * ($rate * $type + 1) / $rate
            + $pmt * ($tt1 - 1) * $type / $rate;
        if ($denominator == 0) {
            return Functions::NAN();
        }

        return $numerator / $denominator;
    }
}
