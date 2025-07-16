<?php

namespace Payout\Payment\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Level;

class Handler extends Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Level::Info;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/payout.log';
}
