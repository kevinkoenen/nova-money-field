<?php

namespace Vyuldashev\NovaMoneyField;

use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\BitcoinCurrencies;
use Money\Currencies\ISOCurrencies;
use Money\Currency;

class Money extends Number
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-money-field';

    public $inMinorUnits;

    public function __construct($name, $currency = 'USD', $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        $this->withMeta([
            'currency' => $currency,
            'subUnits' => $this->subunits($currency),
        ]);

        $this->step(1 / $this->minorUnit($currency));

        $this
            ->resolveUsing(function ($value) use ($currency, $resolveCallback) {
                if($this->nullable) {
                    return null;
                }

                if ($resolveCallback !== null) {
                    $value = call_user_func_array($resolveCallback, func_get_args());
                }

                return $this->inMinorUnits ? $value / $this->minorUnit($currency) : (float) $value;
            })
            ->fillUsing(function (NovaRequest $request, $model, $attribute, $requestAttribute) use ($currency) {
                $value = $request[$requestAttribute];

                if ($this->inMinorUnits) {
                    $value *= $this->minorUnit($currency);
                }

                if($this->nullable) {
                    $value = null;
                }

                $model->{$attribute} = $value;
            });
    }

    /**
     * The value in database is store in minor units (cents for dollars).
     */
    public function storedInMinorUnits()
    {
        $this->inMinorUnits = true;

        return $this;
    }

    public function locale($locale)
    {
        return $this->withMeta(['locale' => $locale]);
    }

    public function subUnits(string $currency)
    {
        return (new AggregateCurrencies([
            new ISOCurrencies(),
            new BitcoinCurrencies(),
        ]))->subunitFor(new Currency($currency));
    }

    public function minorUnit($currency)
    {
        return 10 ** $this->subUnits($currency);
    }
}
