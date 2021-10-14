<?php
namespace Dm\Base\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\AuthorizationInterface;

class GenericButton
{
    protected $context;
    protected $authorization;

    public function __construct(
        Context $context,
        $authorization = null
    ) {
        $this->context = $context;
        $this->authorization = $authorization
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\AuthorizationInterface::class
            );
    }

    public function getObjectId()
    {
        return $this->context->getRequest()->getParam('id');
    }

    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}