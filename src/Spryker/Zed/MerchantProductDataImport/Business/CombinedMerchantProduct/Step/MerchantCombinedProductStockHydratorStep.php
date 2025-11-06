<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace Spryker\Zed\MerchantProductDataImport\Business\CombinedMerchantProduct\Step;

use Generated\Shared\Transfer\SpyStockProductEntityTransfer;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use Spryker\Zed\MerchantProductDataImport\MerchantProductDataImportConfig;

class MerchantCombinedProductStockHydratorStep implements DataImportStepInterface
{
    use AssignedProductTypeSupportTrait;

    public const string DATA_PRODUCT_STOCK_TRANSFER = 'DATA_PRODUCT_STOCK_TRANSFER';

    public const string IS_NEVER_OUT_OF_STOCK = 'DATA_PRODUCT_STOCK_TRANSFER';

    protected const bool DEFAULT_IS_NEVER_OUT_OF_STOCK = false;

    protected const int DEFAULT_STOCK_QUANTITY = 0;

    public function execute(DataSetInterface $dataSet): void
    {
        if (!$this->isAssignedProductTypeSupported($dataSet)) {
            return;
        }

        $this->importProductStocks($dataSet);
    }

    protected function importProductStocks(DataSetInterface $dataSet): void
    {
        $productStocks = $this->getProductStocks($dataSet);
        $dataSet[static::IS_NEVER_OUT_OF_STOCK] = false;

        $productStockTransfers = [];
        foreach ($productStocks as $productStock) {
            $warehouseName = $productStock[MerchantCombinedProductStockExtractorStep::KEY_WAREHOUSE_NAME];
            $idStock = $dataSet[AddMerchantStockStep::KEY_MERCHANT_STOCKS][$warehouseName];
            $isNeverOutOfStock = filter_var($productStock[MerchantCombinedProductStockExtractorStep::KEY_IS_NEVER_OUT_OF_STOCK], FILTER_VALIDATE_BOOLEAN);
            $spyStockProductEntityTransfer = (new SpyStockProductEntityTransfer())
                ->setFkStock($idStock)
                ->setIsNeverOutOfStock($isNeverOutOfStock)
                ->setQuantity((int)$productStock[MerchantCombinedProductStockExtractorStep::KEY_QUANTITY]);

            $productStockTransfers[] = $spyStockProductEntityTransfer;

            if ($isNeverOutOfStock) {
                $dataSet[static::IS_NEVER_OUT_OF_STOCK] = true;
            }
        }

        if (!count($productStockTransfers) && $this->getIsNewProduct($dataSet)) {
            $stockIds = array_values($this->getMerchantStocks($dataSet));
            $productStockTransfers = $this->getDefaultStockProductEntityTransfers($stockIds);
        }

        $dataSet[static::DATA_PRODUCT_STOCK_TRANSFER] = $productStockTransfers;
    }

    /**
     * @param array<int> $stockIds
     *
     * @return array<\Generated\Shared\Transfer\SpyStockProductEntityTransfer>
     */
    protected function getDefaultStockProductEntityTransfers(array $stockIds): array
    {
        return array_map(
            static fn (int $idStock): SpyStockProductEntityTransfer => (new SpyStockProductEntityTransfer())
                ->setFkStock($idStock)
                ->setIsNeverOutOfStock(static::DEFAULT_IS_NEVER_OUT_OF_STOCK)
                ->setQuantity(static::DEFAULT_STOCK_QUANTITY),
            $stockIds,
        );
    }

    /**
     * @return array<\Generated\Shared\Transfer\SpyStockProductEntityTransfer>
     */
    protected function getProductStocks(DataSetInterface $dataSet): array
    {
        return $dataSet[MerchantCombinedProductStockExtractorStep::KEY_PRODUCT_STOCKS];
    }

    /**
     * @return array<string, int>
     */
    protected function getMerchantStocks(DataSetInterface $dataSet): array
    {
        return $dataSet[AddMerchantStockStep::KEY_MERCHANT_STOCKS];
    }

    protected function getIsNewProduct(DataSetInterface $dataSet): bool
    {
        return $dataSet[DefineIsNewProductStep::DATA_KEY_IS_NEW_PRODUCT];
    }

    /**
     * @return array<string>
     */
    protected function getSupportedAssignedProductTypes(): array
    {
        return [
            MerchantProductDataImportConfig::ASSIGNABLE_PRODUCT_TYPE_CONCRETE,
            MerchantProductDataImportConfig::ASSIGNABLE_PRODUCT_TYPE_BOTH,
        ];
    }
}
