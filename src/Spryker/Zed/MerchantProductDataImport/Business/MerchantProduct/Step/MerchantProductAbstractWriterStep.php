<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Zed\MerchantProductDataImport\Business\MerchantProduct\Step;

use Orm\Zed\MerchantProduct\Persistence\SpyMerchantProductAbstractQuery;
use Spryker\Zed\DataImport\Business\Exception\InvalidDataException;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\DataImportStepInterface;
use Spryker\Zed\DataImport\Business\Model\DataImportStep\PublishAwareStep;
use Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface;
use Spryker\Zed\MerchantProductDataImport\Business\MerchantProduct\DataSet\MerchantProductDataSetInterface;

class MerchantProductAbstractWriterStep extends PublishAwareStep implements DataImportStepInterface
{
    /**
     * @uses \Spryker\Shared\MerchantProductSearch\MerchantProductSearchConfig::MERCHANT_PRODUCT_ABSTRACT_PUBLISH
     *
     * @var string
     */
    protected const MERCHANT_PRODUCT_ABSTRACT_PUBLISH = 'MerchantProduct.merchant_product_abstract.publish';

    /**
     * @uses \Spryker\Shared\MerchantProductStorage\MerchantProductStorageConfig::MERCHANT_PRODUCT_ABSTRACT_PUBLISH
     *
     * @var string
     */
    protected const EVENT_MERCHANT_PRODUCT_ABSTRACT_PUBLISH = 'MerchantProductAbstract.publish';

    /**
     * @var array
     */
    protected const REQUIRED_DATA_SET_KEYS = [
        MerchantProductDataSetInterface::FK_MERCHANT,
        MerchantProductDataSetInterface::FK_PRODUCT_ABSTRACT,
    ];

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @return void
     */
    public function execute(DataSetInterface $dataSet): void
    {
        $this->validateDataSet($dataSet);

        $merchantProductAbstractEntity = $this->createMerchantProductAbstractPropelQuery()
            ->filterByFkMerchant($dataSet[MerchantProductDataSetInterface::FK_MERCHANT])
            ->filterByFkProductAbstract($dataSet[MerchantProductDataSetInterface::FK_PRODUCT_ABSTRACT])
            ->findOneOrCreate();

        $merchantProductAbstractEntity->fromArray($dataSet->getArrayCopy());
        $merchantProductAbstractEntity->save();

        $this->addPublishEvents(static::MERCHANT_PRODUCT_ABSTRACT_PUBLISH, $merchantProductAbstractEntity->getIdMerchantProductAbstract());

        $this->addPublishEvents(static::EVENT_MERCHANT_PRODUCT_ABSTRACT_PUBLISH, $merchantProductAbstractEntity->getIdMerchantProductAbstract());
    }

    /**
     * @param \Spryker\Zed\DataImport\Business\Model\DataSet\DataSetInterface $dataSet
     *
     * @throws \Spryker\Zed\DataImport\Business\Exception\InvalidDataException
     *
     * @return void
     */
    protected function validateDataSet(DataSetInterface $dataSet): void
    {
        foreach (static::REQUIRED_DATA_SET_KEYS as $requiredDataSetKey) {
            if (!$dataSet[$requiredDataSetKey]) {
                throw new InvalidDataException(sprintf('"%s" is required.', $requiredDataSetKey));
            }
        }
    }

    /**
     * @return \Orm\Zed\MerchantProduct\Persistence\SpyMerchantProductAbstractQuery
     */
    protected function createMerchantProductAbstractPropelQuery(): SpyMerchantProductAbstractQuery
    {
        return SpyMerchantProductAbstractQuery::create();
    }
}
