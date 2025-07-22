<?php
/*
 * Copyright (c) 2020 Payout One
 *
 * Author: Web Technology Codes Software Services LLP
 *
 * Released under the GNU General Public License
 */

namespace Payout\Payment\Controller\Redirect;

use Magento\Framework\View\Result\Page;
use Payout\Payment\Controller\AbstractPayout;

/**
 * Responsible for loading page content.
 *
 * This is a basic controller that only loads the corresponding layout file. It may duplicate other such
 * controllers, and thus it is considered tech debt. This code duplication will be resolved in future releases.
 */
class Index extends AbstractPayout
{
    /**
     * Execute
     */
    public function execute(): Page
    {
        return $this->createCheckout($this->_checkoutSession->getLastRealOrder());
    }
}
