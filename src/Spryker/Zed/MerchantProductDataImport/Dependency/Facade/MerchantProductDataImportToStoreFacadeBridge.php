<?php

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spryker\Zed\MerchantProductDataImport\Dependency\Facade;

use Generated\Shared\Transfer\StoreTransfer;

class MerchantProductDataImportToStoreFacadeBridge implements MerchantProductDataImportToStoreFacadeInterface
{
    /**
     * @var \Spryker\Zed\Store\Business\StoreFacadeInterface
     */
    protected $storeFacade;

    /**
     * @param \Spryker\Zed\Store\Business\StoreFacadeInterface $storeFacade
     */
    public function __construct($storeFacade)
    {
        $this->storeFacade = $storeFacade;
    }

    /**
     * @return list<\Generated\Shared\Transfer\StoreTransfer>
     */
    public function getAllStores(): array
    {
        return $this->storeFacade->getAllStores();
    }

    public function getStoreByName(string $name): StoreTransfer
    {
        return $this->storeFacade->getStoreByName($name);
    }
}
