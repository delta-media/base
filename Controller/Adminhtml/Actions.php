<?php
namespace Dm\Base\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Backend\App\Action\Context;

abstract class Actions extends \Magento\Backend\App\Action
{
    protected $_formSessionKey;
    protected $_allowedKey;
    protected $_modelClass;
    protected $_activeMenu;
    protected $_configSection;
    protected $_idKey = 'id';
    protected $_statusField     = 'status';
    protected $_paramsHolder;
    protected $_model;
    protected $_coreRegistry = null;
    protected $dataPersistor;

    public function __construct(
        Context $context,
        DataPersistorInterface $dataPersistor
    ) {
        $this->dataPersistor = $dataPersistor;
        parent::__construct($context);
    }

    public function execute()
    {
        $_preparedActions = ['index', 'grid', 'new', 'edit', 'save', 'duplicate', 'delete', 'config', 'massStatus'];
        $_action = $this->getRequest()->getActionName();
        if (in_array($_action, $_preparedActions)) {
            $method = '_'.$_action.'Action';

            $this->_beforeAction();
            $this->$method();
            $this->_afterAction();
        }
    }

    protected function _indexAction()
    {
        if ($this->getRequest()->getParam('ajax')) {
            $this->_forward('grid');
            return;
        }

        $this->_view->loadLayout();
        $this->_setActiveMenu($this->_activeMenu);
        $title = __('Manage %1', $this->_getModel(false)->getOwnTitle(true));
        $this->_view->getPage()->getConfig()->getTitle()->prepend($title);
        $this->_addBreadcrumb($title, $title);
        $this->_view->renderLayout();
    }

    protected function _gridAction()
    {
        $this->_view->loadLayout(false);
        $this->_view->renderLayout();
    }

    protected function _newAction()
    {
        $this->_forward('edit');
    }

    public function _editAction()
    {

        try {
            $model = $this->_getModel();
            $id = $this->getRequest()->getParam('id');
            if (!$model->getId() && $id) {
                throw new \Exception("Item is not longer exist.", 1);
            }
            $this->_getRegistry()->register('current_model', $model);

            $this->_view->loadLayout();
            $this->_setActiveMenu($this->_activeMenu);

            $title = $model->getOwnTitle();
            if ($model->getId()) {
                $breadcrumbTitle = __('Edit %1', $title);
                $breadcrumbLabel = $breadcrumbTitle;
            } else {
                $breadcrumbTitle = __('New %1', $title);
                $breadcrumbLabel = __('Create %1', $title);
            }
            $this->_view->getPage()->getConfig()->getTitle()->prepend(__($title));
            $this->_view->getPage()->getConfig()->getTitle()->prepend(
                $model->getId() ? $this->_getModelName($model) : __('New %1', $title)
            );

            $this->_addBreadcrumb($breadcrumbLabel, $breadcrumbTitle);

            // restore data
            $values = $this->_getSession()->getData($this->_formSessionKey, true);
            if ($this->_paramsHolder) {
                $values = isset($values[$this->_paramsHolder]) ? $values[$this->_paramsHolder] : null;
            }

            if ($values) {
                $model->addData($values);
            }

            $this->_view->renderLayout();
        } catch (\Exception $e) {
            $this->messageManager->addException(
                $e,
                __(
                    'Something went wrong: %1',
                    $e->getMessage()
                )
            );
            $this->_redirect('*/*/');
        }
    }

    protected function _getModelName(\Magento\Framework\Model\AbstractModel $model)
    {
        return $model->getName() ?: $model->getTitle();
    }

    public function _saveAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->getResponse()->setRedirect($this->getUrl('*/*'));
        }
        $model = $this->_getModel();

        try {
            $params = $this->_paramsHolder ? $request->getParam($this->_paramsHolder) : $request->getParams();
            $params = $this->filterParams($params);

            $idFieldName = $model->getResource()->getIdFieldName();
            if (isset($params[$idFieldName]) && empty($params[$idFieldName])) {
                unset($params[$idFieldName]);
            }
            $model->addData($params);

            $this->_beforeSave($model, $request);
            $model->save();
            $this->_afterSave($model, $request);

            $this->messageManager->addSuccess(__('%1 has been saved.', $model->getOwnTitle()));
            $this->_setFormData(false);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addError(nl2br($e->getMessage()));
            $this->_setFormData($params);
        } catch (\Exception $e) {
            $this->messageManager->addException(
                $e,
                __(
                    'Something went wrong while saving this %1. %2',
                    strtolower($model->getOwnTitle()),
                    $e->getMessage()
                )
            );
            $this->_setFormData($params);
        }

        $hasError = (bool)$this->messageManager->getMessages()->getCountByType(
            \Magento\Framework\Message\MessageInterface::TYPE_ERROR
        );

        if ($request->getParam('isAjax')) {
            $block = $this->_objectManager->create(\Magento\Framework\View\Layout::class)->getMessagesBlock();
            $block->setMessages($this->messageManager->getMessages(true));

            $this->getResponse()->setBody(json_encode(
                [
                    'messages' => $block->getGroupedHtml(),
                    'error' => $hasError,
                    'model' => $model->toArray(),
                ]
            ));
        } else {
            if ($hasError || $request->getParam('back')) {
                $this->_redirect('*/*/edit', [$this->_idKey => $model->getId()]);
            } else {
                $this->_redirect('*/*');
            }
        }
    }

    protected function _duplicateAction()
    {
        try {
            $originModel = $this->_getModel();
            if (!$originModel->getId()) {
                throw new \Exception("Item is not longer exist.", 1);
            }

            $model = $originModel->duplicate();

            $this->messageManager->addSuccess(__('%1 has been duplicated.', $model->getOwnTitle()));
            $this->_redirect('*/*/edit', [$this->_idKey => $model->getId()]);
        } catch (\Exception $e) {
            $this->messageManager->addException(
                $e,
                __(
                    'Something went wrong while saving this %1. %2',
                    strtolower(isset($model) ? $model->getOwnTitle() : 'item'),
                    $e->getMessage()
                )
            );
            $this->_redirect('*/*/edit', [$this->_idKey => $originModel->getId()]);
        }
    }

    protected function _beforeSave($model, $request)
    {
    }

    protected function _afterSave($model, $request)
    {
    }

    protected function _beforeAction()
    {
    }

    protected function _afterAction()
    {
    }

    protected function _deleteAction()
    {
        $ids = $this->getRequest()->getParam($this->_idKey);

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $error = false;
        try {
            foreach ($ids as $id) {
                $this->_objectManager->create($this->_modelClass)->load($id)->delete();
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $error = true;
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $error = true;
            $this->messageManager->addException(
                $e,
                __(
                    "We can't delete %1 right now. %2",
                    strtolower($this->_getModel(false)->getOwnTitle()),
                    $e->getMessage()
                )
            );
        }

        if (!$error) {
            $this->messageManager->addSuccess(
                __('%1 have been deleted.', $this->_getModel(false)->getOwnTitle(count($ids) > 1))
            );
        }

        $this->_redirect('*/*');
    }

    protected function _massStatusAction()
    {
        $ids = $this->getRequest()->getParam($this->_idKey);

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $model = $this->_getModel(false);

        $error = false;

        try {
            $status = $this->getRequest()->getParam('status');
            $statusFieldName = $this->_statusField;

            if (is_null($status)) {
                throw new \Exception(__('Parameter "Status" missing in request data.'));
            }

            if (is_null($statusFieldName)) {
                throw new \Exception(__('Status Field Name is not specified.'));
            }

            foreach ($ids as $id) {
                $this->_objectManager->create($this->_modelClass)
                    ->load($id)
                    ->setData($this->_statusField, $status)
                    ->save();
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $error = true;
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $error = true;
            $this->messageManager->addException(
                $e,
                __(
                    "We can't change status of %1 right now. %2",
                    strtolower($model->getOwnTitle()),
                    $e->getMessage()
                )
            );
        }

        if (!$error) {
            $this->messageManager->addSuccess(
                __('%1 status have been changed.', $model->getOwnTitle(count($ids) > 1))
            );
        }

        $this->_redirect('*/*');
    }

    protected function _configAction()
    {
        $this->_redirect('admin/system_config/edit', ['section' => $this->_configSection()]);
    }

    protected function _setFormData($data = null)
    {
        if (null === $data) {
            $data = $this->getRequest()->getParams();
        }

        if (false === $data) {
            $this->dataPersistor->clear($this->_formSessionKey);
        } else {
            $this->dataPersistor->set($this->_formSessionKey, $data);
        }

        /* deprecated save in session */
        $this->_getSession()->setData($this->_formSessionKey, $data);

        return $this;
    }

    protected function filterParams($data)
    {
        return $data;
    }

    protected function _getRegistry()
    {
        if (is_null($this->_coreRegistry)) {
            $this->_coreRegistry = $this->_objectManager->get(\Magento\Framework\Registry::class);
        }
        return $this->_coreRegistry;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed($this->_allowedKey);
    }

    protected function _getModel($load = true)
    {
        if (is_null($this->_model)) {
            $this->_model = $this->_objectManager->create($this->_modelClass);

            $id = (int)$this->getRequest()->getParam($this->_idKey);
            $idFieldName = $this->_model->getResource()->getIdFieldName();
            if (!$id && $this->_idKey !== $idFieldName) {
                $id = (int)$this->getRequest()->getParam($idFieldName);
            }

            if ($id && $load) {
                $this->_model->load($id);
            }
        }
        return $this->_model;
    }
}