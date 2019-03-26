<?php
namespace Pwd;

use Omeka\Api\Representation\ItemRepresentation;
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
            'controllers' => [
                'factories' => [
                    'Pwd\Controller\Admin\Index' => 'Pwd\Controller\Admin\IndexControllerFactory',
                    'Pwd\Controller\Site\Index' => 'Pwd\Controller\Site\IndexControllerFactory',
                ],
            ],
            'router' => [
                'routes' => [
                    'admin' => [
                        'child_routes' => [
                            'pwd' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/pwd',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Pwd\Controller\Admin',
                                        'controller' => 'Index',
                                        'action' => 'index',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'viewer' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/viewer/:document-instance-id',
                                            'constraints' => [
                                                'document-instance-id' => '\d+',
                                            ],
                                            'defaults' => [
                                                'action' => 'viewer',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'site' => [
                        'child_routes' => [
                            'pwd' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/pwd',
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Pwd\Controller\Site',
                                        'controller' => 'Index',
                                        'action' => 'index',
                                    ],
                                ],
                                'may_terminate' => true,
                                'child_routes' => [
                                    'viewer' => [
                                        'type' => 'Segment',
                                        'options' => [
                                            'route' => '/viewer/:document-instance-id',
                                            'constraints' => [
                                                'document-instance-id' => '\d+',
                                            ],
                                            'defaults' => [
                                                'action' => 'viewer',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add section navigation to items.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if ($this->isClass('pwd:Document', $item)) {
                    $sectionNav = $event->getParam('section_nav');
                    $sectionNav['pwd-document-instances'] = 'Document instances';
                    $sectionNav['pwd-document-names'] = 'Document names';
                    $event->setParam('section_nav', $sectionNav);
                }
                if ($this->isClass('pwd:Image', $item)) {
                    $sectionNav = $event->getParam('section_nav');
                    $sectionNav['pwd-image-documents'] = 'Documents in image';
                    $event->setParam('section_nav', $sectionNav);
                }
            }
        );
        // Add section content to items.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if ($this->isClass('pwd:Document', $item)) {
                    echo $view->partial('pwd/document-instances', [
                        'documentInstances' => $this->getDocumentInstances($item),
                    ]);
                    echo $view->partial('pwd/document-names', [
                        'documentNames' => $this->getDocumentNames($item),
                    ]);
                }
                if ($this->isClass('pwd:Image', $item)) {
                    echo $view->partial('pwd/image-documents', [
                        'imageDocuments' => $this->getImageDocuments($item),
                    ]);
                }
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            function (Event $event) {
                $view = $event->getTarget();
                $item = $view->item;
                if ($this->isClass('pwd:Document', $item)) {
                    echo sprintf('<h3>%s</h3>', $view->translate('Document instances'));
                    echo $view->partial('pwd/document-instances', [
                        'documentInstances' => $this->getDocumentInstances($item),
                    ]);
                    echo sprintf('<h3>%s</h3>', $view->translate('Document names'));
                    echo $view->partial('pwd/document-names', [
                        'documentNames' => $this->getDocumentNames($item),
                    ]);
                }
                if ($this->isClass('pwd:Image', $item)) {
                    echo sprintf('<h3>%s</h3>', $view->translate('Documents in image'));
                    echo $view->partial('pwd/image-documents', [
                        'imageDocuments' => $this->getImageDocuments($item),
                    ]);
                }
            }
        );
    }

    /**
     * Check whether the passed item is an instance of the passed class.
     *
     * @param string $className
     * @param ItemRepresentation $item
     * @return bool
     */
    protected function isClass($className, ItemRepresentation $item)
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

    /**
     * Get all instances (copies, citations, etc.) of the passed pwd:Document item.
     *
     * @param ItemRepresentation $item
     * @return array
     */
    protected function getDocumentInstances(ItemRepresentation $item)
    {
        $conn = $this->getServiceLocator()->get('Omeka\Connection');
        $stmt = $conn->prepare('SELECT * FROM pwd_document_instance WHERE document_id = ? ORDER BY is_primary DESC');
        $stmt->execute([$item->id()]);
        return $stmt->fetchAll();
    }

    /**
     * Get all names of the passed pwd:Document item.
     *
     * @param ItemRepresentation $item
     * @return array
     */
    protected function getDocumentNames(ItemRepresentation $item)
    {
        $conn = $this->getServiceLocator()->get('Omeka\Connection');
        $stmt = $conn->prepare('SELECT * FROM pwd_document_name WHERE document_id = ? ORDER BY is_author DESC, is_primary DESC');
        $stmt->execute([$item->id()]);
        return $stmt->fetchAll();
    }

    /**
     * Get all pwd:Document items represented in the passed pwd:Image item.
     *
     * @param ItemRepresentation $item
     * @return array
     */
    protected function getImageDocuments(ItemRepresentation $item)
    {
        $conn = $this->getServiceLocator()->get('Omeka\Connection');
        $stmt = $conn->prepare('SELECT * FROM pwd_document_instance WHERE image_id = ? ORDER BY is_primary DESC');
        $stmt->execute([$item->id()]);
        return $stmt->fetchAll();
    }
}
