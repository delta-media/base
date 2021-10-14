<?php
namespace Dm\Base\Logger;
use Monolog\Logger;
class Handler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/dm.import.log';
}