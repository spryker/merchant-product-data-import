<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Zed\MerchantProductDataImport\Business\CombinedMerchantProduct\Step;

use Generated\Shared\Transfer\StoreTransfer;
use Orm\Zed\Availability\Persistence\Map\SpyAvailabilityTableMap;
use Orm\Zed\Availability\Persistence\SpyAvailabilityAbstract;
use Orm\Zed\Availability\Persistence\SpyAvailabilityAbstractQuery;
use Orm\Zed\Availability\Persistence\SpyAvailabilityQuery;
use Orm\Zed\Oms\Persistence\Map\SpyOmsProductReservationTableMap;
use Orm\Zed\Oms\Persistence\SpyOmsProductReservationQuery;
use Orm\Zed\Oms\Persistence\SpyOmsProductReservationStoreQuery;
use Orm\Zed\Stock\Persistence\Map\SpyStockProductTableMap;
use Orm\Zed\Stock\Persistence\SpyStockProductQuery;
use Spryker\DecimalObject\Decimal;
use Spryker\Zed\Availability\Dependency\AvailabilityEvents;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use Spryker\Zed\DataImport\Business\Model\Publisher\DataImporterPublisher;
use Spryker\Zed\MerchantProductDataImport\Business\CombinedMerchantProduct\DataSet\MerchantCombinedProductDataSetInterface;
use Spryker\Zed\MerchantProductDataImport\Business\CombinedMerchantProduct\Repository\MerchantCombinedProductRepositoryInterface;
use Spryker\Zed\MerchantProductDataImport\Dependency\Facade\MerchantProductDataImportToStoreFacadeInterface;
use Spryker\Zed\MerchantProductDataImport\MerchantProductDataImportConfig;
use Spryker\Zed\PropelOrm\Business\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Stock\Business\StockFacadeInterface;

class MerchantCombinedProductStockWriterStep implements DataImportStepInterface
{
    use AssignedProductTypeSupportTrait;

    protected const string KEY_AVAILABILITY_SKU = 'KEY_AVAILABILITY_SKU';

    protected const string KEY_AVAILABILITY_QUANTITY = 'KEY_AVAILABILITY_QUANTITY';

    protected const string KEY_AVAILABILITY_ID_STORE = 'KEY_AVAILABILITY_ID_STORE';

    protected const string KEY_AVAILABILITY_IS_NEVER_OUT_OF_STOCK = 'KEY_AVAILABILITY_IS_NEVER_OUT_OF_STOCK';

    protected const string KEY_AVAILABILITY_ID_AVAILABILITY_ABSTRACT = 'KEY_AVAILABILITY_ID_AVAILABILITY_ABSTRACT';

    protected const string COL_AVAILABILITY_TOTAL_QUANTITY = 'availabilityTotalQuantity';

    protected const string COL_STOCK_PRODUCT_TOTAL_QUANTITY = 'stockProductTotalQuantity';

    /**
     * @var array<string, array<int, \Orm\Zed\Availability\Persistence\SpyAvailabilityAbstract>>
     */
    protected static array $availabilityAbstractEntitiesIndexedByAbstractSkuAndIdStore = [];

    public function __construct(
        protected MerchantCombinedProductRepositoryInterface $merchantCombinedProductRepository,
        protected MerchantProductDataImportToStoreFacadeInterface $storeFacade,
        protected StockFacadeInterface $stockFacade
    ) {
    }

    public function execute(DataSetInterface $dataSet): void
    {
        if (!$this->isAssignedProductTypeSupported($dataSet)) {
            return;
        }

        $this->createOrUpdateProductStock($dataSet);
        $this->updateAvailability($dataSet);
    }

    protected function createOrUpdateProductStock(DataSetInterface $dataSet): void
    {
        $productStockEntityTransfers = $this->getProductStockEntityTransfers($dataSet);
        $sku = $dataSet[MerchantCombinedProductDataSetInterface::KEY_CONCRETE_SKU];
        $idProduct = $this->merchantCombinedProductRepository->getIdProductBySku($sku);

        foreach ($productStockEntityTransfers as $productStockEntityTransfer) {
            $spyStockProduct = SpyStockProductQuery::create()
                ->filterByFkProduct($idProduct)
                ->filterByFkStock($productStockEntityTransfer->getFkStock())
                ->findOneOrCreate();

            $spyStockProduct->fromArray($productStockEntityTransfer->modifiedToArray());

            if (!$spyStockProduct->isNew() && !$spyStockProduct->isModified()) {
                continue;
            }

            $spyStockProduct->save();
        }
    }

    /**
     * @return array<\Generated\Shared\Transfer\SpyStockProductEntityTransfer>
     */
    protected function getProductStockEntityTransfers(DataSetInterface $dataSet): array
    {
        return $dataSet[MerchantCombinedProductStockHydratorStep::DATA_PRODUCT_STOCK_TRANSFER];
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

    protected function updateAvailability(DataSetInterface $dataSet): void
    {
        foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
            $this->updateAvailabilityForStore($dataSet, $storeTransfer);
        }
    }

    protected function updateAvailabilityForStore(DataSetInterface $dataSet, StoreTransfer $storeTransfer): void
    {
        $concreteSku = $dataSet[MerchantCombinedProductDataSetInterface::KEY_CONCRETE_SKU];
        $abstractSku = $this->merchantCombinedProductRepository->getAbstractSkuByConcreteSku($concreteSku);
        $idStore = $this->getIdStore($storeTransfer);

        $availabilityQuantity = $this->getProductAvailabilityForStore($concreteSku, $storeTransfer);
        $availabilityAbstractEntity = $this->getAvailabilityAbstract($abstractSku, $idStore);
        $this->persistAvailabilityData([
            static::KEY_AVAILABILITY_SKU => $concreteSku,
            static::KEY_AVAILABILITY_QUANTITY => $availabilityQuantity,
            static::KEY_AVAILABILITY_ID_AVAILABILITY_ABSTRACT => $availabilityAbstractEntity->getIdAvailabilityAbstract(),
            static::KEY_AVAILABILITY_ID_STORE => $idStore,
            static::KEY_AVAILABILITY_IS_NEVER_OUT_OF_STOCK => $dataSet[MerchantCombinedProductStockHydratorStep::IS_NEVER_OUT_OF_STOCK] ?? false,
        ]);

        $this->updateAbstractAvailabilityQuantity($availabilityAbstractEntity, $idStore);

        DataImporterPublisher::addEvent(AvailabilityEvents::AVAILABILITY_ABSTRACT_PUBLISH, $availabilityAbstractEntity->getIdAvailabilityAbstract());
    }

    protected function getProductAvailabilityForStore(string $concreteSku, StoreTransfer $storeTransfer): Decimal
    {
        $physicalItems = $this->calculateProductStockForSkuAndStore($concreteSku, $storeTransfer);
        $reservedItems = $this->getReservationQuantityForStore($concreteSku, $storeTransfer);
        $stockProductQuantity = $physicalItems->subtract($reservedItems);

        return $stockProductQuantity->greaterThanOrEquals(0) ? $stockProductQuantity : new Decimal(0);
    }

    protected function calculateProductStockForSkuAndStore(string $concreteSku, StoreTransfer $storeTransfer): Decimal
    {
        $idProductConcrete = $this->merchantCombinedProductRepository->getIdProductBySku($concreteSku);
        $stockNames = $this->getStoreWarehouses($storeTransfer->getNameOrFail());

        return $this->getStockProductQuantityByIdProductAndStockNames($idProductConcrete, $stockNames);
    }

    /**
     * @return array<string>
     */
    protected function getStoreWarehouses(string $storeName): array
    {
        return $this->stockFacade->getStoreToWarehouseMapping()[$storeName] ?? [];
    }

    /**
     * @param array<string> $stockNames
     */
    protected function getStockProductQuantityByIdProductAndStockNames(
        int $idProductConcrete,
        array $stockNames,
    ): Decimal {
        $stockProductTotalQuantity = SpyStockProductQuery::create()
            ->filterByFkProduct($idProductConcrete)
            ->useStockQuery()
                ->filterByName($stockNames, Criteria::IN)
            ->endUse()
            ->withColumn(sprintf('SUM(%s)', SpyStockProductTableMap::COL_QUANTITY), static::COL_STOCK_PRODUCT_TOTAL_QUANTITY)
            ->select([static::COL_STOCK_PRODUCT_TOTAL_QUANTITY])
            ->findOne();

        return new Decimal($stockProductTotalQuantity ?? 0);
    }

    protected function getReservationQuantityForStore(string $sku, StoreTransfer $storeTransfer): Decimal
    {
        $idStore = $this->getIdStore($storeTransfer);

        /**
         * @var \Propel\Runtime\Collection\ObjectCollection $productReservationsCollection
         */
        $productReservationsCollection = SpyOmsProductReservationQuery::create()
            ->filterBySku($sku)
            ->filterByFkStore($idStore)
            ->select([
                SpyOmsProductReservationTableMap::COL_RESERVATION_QUANTITY,
            ])
            ->find();

        $productReservations = $productReservationsCollection->toArray();

        $reservationQuantity = new Decimal(0);

        foreach ($productReservations as $productReservationQuantity) {
            /**
             * @var string $productReservationQuantity
             */
            $reservationQuantity = $reservationQuantity->add($productReservationQuantity);
        }

        return $reservationQuantity->add($this->getReservationsFromOtherStores($sku, $storeTransfer));
    }

    protected function getReservationsFromOtherStores(string $sku, StoreTransfer $currentStoreTransfer): Decimal
    {
        $reservationQuantity = new Decimal(0);
        $reservationStores = SpyOmsProductReservationStoreQuery::create()
            ->filterBySku($sku)
            ->find();

        foreach ($reservationStores as $omsProductReservationStoreEntity) {
            if ($omsProductReservationStoreEntity->getStore() === $currentStoreTransfer->getName()) {
                continue;
            }

            $reservationQuantity = $reservationQuantity->add($omsProductReservationStoreEntity->getReservationQuantity());
        }

        return $reservationQuantity;
    }

    protected function getIdStore(StoreTransfer $storeTransfer): int
    {
        if (!$storeTransfer->getIdStore()) {
            $idStore = $this->storeFacade
                ->getStoreByName($storeTransfer->getNameOrFail())
                ->getIdStoreOrFail();
            $storeTransfer->setIdStore($idStore);
        }

        return $storeTransfer->getIdStoreOrFail();
    }

    /**
     * @param array<string, mixed> $availabilityData
     */
    protected function persistAvailabilityData(array $availabilityData): void
    {
        $spyAvailabilityEntity = SpyAvailabilityQuery::create()
            ->filterByFkStore($availabilityData[static::KEY_AVAILABILITY_ID_STORE])
            ->filterBySku($availabilityData[static::KEY_AVAILABILITY_SKU])
            ->findOneOrCreate();

        $spyAvailabilityEntity->setFkAvailabilityAbstract($availabilityData[static::KEY_AVAILABILITY_ID_AVAILABILITY_ABSTRACT]);
        $spyAvailabilityEntity->setQuantity($availabilityData[static::KEY_AVAILABILITY_QUANTITY] ?? 0);
        $spyAvailabilityEntity->setIsNeverOutOfStock($availabilityData[static::KEY_AVAILABILITY_IS_NEVER_OUT_OF_STOCK]);

        $spyAvailabilityEntity->save();
    }

    protected function getAvailabilityAbstract(string $abstractSku, int $idStore): SpyAvailabilityAbstract
    {
        if (!empty(static::$availabilityAbstractEntitiesIndexedByAbstractSkuAndIdStore[$abstractSku][$idStore])) {
            return static::$availabilityAbstractEntitiesIndexedByAbstractSkuAndIdStore[$abstractSku][$idStore];
        }

        $availabilityAbstractEntity = SpyAvailabilityAbstractQuery::create()
            ->filterByAbstractSku($abstractSku)
            ->filterByFkStore($idStore)
            ->findOne();

        if (!$availabilityAbstractEntity) {
            $availabilityAbstractEntity = $this->createAvailabilityAbstract($abstractSku, $idStore);
        }

        static::$availabilityAbstractEntitiesIndexedByAbstractSkuAndIdStore[$abstractSku][$idStore] = $availabilityAbstractEntity;

        return $availabilityAbstractEntity;
    }

    protected function createAvailabilityAbstract(string $abstractSku, int $idStore): SpyAvailabilityAbstract
    {
        $availableAbstractEntity = (new SpyAvailabilityAbstract())
            ->setAbstractSku($abstractSku)
            ->setFkStore($idStore);

        $availableAbstractEntity->save();

        return $availableAbstractEntity;
    }

    protected function updateAbstractAvailabilityQuantity(
        SpyAvailabilityAbstract $availabilityAbstractEntity,
        int $idStore,
    ): SpyAvailabilityAbstract {
        /** @var string|null $sumQuantity */
        $sumQuantity = SpyAvailabilityQuery::create()
            ->filterByFkAvailabilityAbstract($availabilityAbstractEntity->getIdAvailabilityAbstract())
            ->filterByFkStore($idStore)
            ->withColumn(sprintf('SUM(%s)', SpyAvailabilityTableMap::COL_QUANTITY), static::COL_AVAILABILITY_TOTAL_QUANTITY)
            ->select([static::COL_AVAILABILITY_TOTAL_QUANTITY])
            ->findOne();

        $availabilityAbstractEntity->setFkStore($idStore);
        $availabilityAbstractEntity->setQuantity($sumQuantity ?? '0');
        $availabilityAbstractEntity->save();

        return $availabilityAbstractEntity;
    }
}
