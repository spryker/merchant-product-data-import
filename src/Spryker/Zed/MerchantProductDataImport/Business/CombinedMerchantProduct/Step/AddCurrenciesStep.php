<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Spryker\Zed\MerchantProductDataImport\Business\CombinedMerchantProduct\Step;

use Orm\Zed\Currency\Persistence\SpyCurrencyQuery;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use Spryker\Zed\MerchantProductDataImport\Dependency\Facade\MerchantProductDataImportToStoreFacadeInterface;

class AddCurrenciesStep implements DataImportStepInterface
{
    public function __construct(protected MerchantProductDataImportToStoreFacadeInterface $storeFacade)
    {
    }

    /**
     * @var string
     */
    public const KEY_CURRENCIES = 'DATA_CURRENCIES';

    /**
     * @var array<string, int>
     */
    protected static array $currencies = [];

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @return void
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $dataSet[static::KEY_CURRENCIES] = $this->getCurrencyCodeToIdMap();
    }

    /**
     * @return array<string, int>
     */
    protected function getCurrencyCodeToIdMap(): array
    {
        if (!static::$currencies) {
            $currencyIsoCodes = $this->getAvailableCurrencyIsoCodes();
            $spyCurrencies = SpyCurrencyQuery::create()
                ->filterByCode_In($currencyIsoCodes)
                ->find();

            foreach ($spyCurrencies as $spyCurrency) {
                /** @phpstan-var string $code */
                $code = $spyCurrency->getCode();

                /** @phpstan-var int $idCurrency */
                $idCurrency = $spyCurrency->getIdCurrency();

                static::$currencies[$code] = $idCurrency;
            }
        }

        return static::$currencies;
    }

    /**
     * @return array<string, string>
     */
    protected function getAvailableCurrencyIsoCodes(): array
    {
        $availableCurrencyIsoCodes = [];

        foreach ($this->storeFacade->getAllStores() as $store) {
            foreach ($store->getAvailableCurrencyIsoCodes() as $isoCode) {
                $availableCurrencyIsoCodes[$isoCode] = $isoCode;
            }
        }

        return $availableCurrencyIsoCodes;
    }
}
