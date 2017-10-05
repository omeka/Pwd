<?php
namespace Pwd;

use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return [
            'view_manager' => [
                'template_path_stack' => [
                    OMEKA_PATH . '/modules/Pwd/view',
                ],
            ],
        ];
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if (!$this->isClass('pwd:Document', $item)) {
                    return;
                }
                $sectionNav = $event->getParam('section_nav');
                $sectionNav['pwd-document-images'] = 'Images';
                $event->setParam('section_nav', $sectionNav);
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if (!$this->isClass('pwd:Document', $item)) {
                    return;
                }
                echo $view->partial('pwd/document-images', [
                    'documentImages' => $this->getDocumentImages($item),
                ]);
            }
        );
    }

    protected function isClass($className, $item)
    {
        $class = $item->resourceClass();
        if (!$class) {
            return false;
        }
        if ($className !== $class->term()) {
            return false;
        }
        return true;
    }

    protected function getDocumentImages($item)
    {
        $conn = $this->getServiceLocator()->get('Omeka\Connection');

        $linkedResources = [];
        $tables = ['pwd_document_collection', 'pwd_document_microfilm', 'pwd_document_publication'];
        foreach ($tables as $table) {
            $sql = "SELECT * FROM $table WHERE document_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$item->id()]);
            $linkedResources[$table] = $stmt->fetchAll();
        }
        return $linkedResources;
    }
}
