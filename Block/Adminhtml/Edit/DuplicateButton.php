<?php
namespace Dm\Base\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DuplicateButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        $data = [];
        if ($this->getObjectId()) {
            $data = [
                'label' => __('Duplicate'),
                'class' => 'duplicate',
                'on_click' => 'window.location=\'' . $this->getDuplicateUrl() . '\'',
                'sort_order' => 40,
            ];
        }
        return $data;
    }

    public function getDuplicateUrl()
    {
        return $this->getUrl('*/*/duplicate', ['id' => $this->getObjectId()]);
    }
}