<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Payout\Payment\Setup\Patch\Data;

use Exception;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResourceModel;

class AddPayoutOrderStatus implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private StatusFactory $statusFactory;
    private StatusResourceModel $statusResourceModel;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param StatusFactory $statusFactory
     * @param StatusResourceModel $statusResourceModel
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        StatusFactory            $statusFactory,
        StatusResourceModel      $statusResourceModel,
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->statusFactory = $statusFactory;
        $this->statusResourceModel = $statusResourceModel;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $pendingStatus = "pending_payout";
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => $pendingStatus,
            'label' => __('Pending Payout Payment')
        ]);
        $this->statusResourceModel->save($status);

        $status->assignState($pendingStatus, true, true);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
