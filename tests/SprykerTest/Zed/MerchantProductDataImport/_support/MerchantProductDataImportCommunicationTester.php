<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace SprykerTest\Zed\MerchantProductDataImport;

use Codeception\Actor;
use Orm\Zed\MerchantProduct\Persistence\SpyMerchantProductAbstractQuery;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class MerchantProductDataImportCommunicationTester extends Actor
{
    use _generated\MerchantProductDataImportCommunicationTesterActions;

    /**
     * @return void
     */
    public function ensureMerchantProductAbstractTablesIsEmpty(): void
    {
        $this->createMerchantProductAbstractPropelQuery()->deleteAll();
    }

    /**
     * @return \Orm\Zed\MerchantProduct\Persistence\SpyMerchantProductAbstractQuery
     */
    public function createMerchantProductAbstractPropelQuery(): SpyMerchantProductAbstractQuery
    {
        return SpyMerchantProductAbstractQuery::create();
    }
}
