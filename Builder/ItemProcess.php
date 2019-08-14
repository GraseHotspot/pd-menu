<?php

/**
 * This file is part of the pd-admin pd-menu package.
 *
 * @package     pd-menu
 * @license     LICENSE
 * @author      Kerem APAYDIN <kerem@apaydin.me>
 * @link        https://github.com/appaydin/pd-menu
 */

namespace Pd\MenuBundle\Builder;

use Pd\MenuBundle\Event\PdMenuEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Menu Item Processor.
 *
 * @author Kerem APAYDIN <kerem@apaydin.me>
 */
class ItemProcess implements ItemProcessInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $security;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var string
     */
    protected $currentUri;

    /**
     * ItemProcess constructor.
     *
     * @param RouterInterface               $router
     * @param AuthorizationCheckerInterface $security
     * @param EventDispatcherInterface      $eventDispatcher
     */
    public function __construct(RouterInterface $router, AuthorizationCheckerInterface $security, EventDispatcherInterface $eventDispatcher)
    {
        $this->router = $router;
        $this->security = $security;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Menu Processor.
     *
     * @param ItemInterface $menu
     * @param array         $options
     *
     * @return ItemInterface
     */
    public function processMenu(ItemInterface $menu, array $options = []): ItemInterface
    {
        // Dispatch Event
        if ($menu->isEvent()) {
            $this->eventDispatcher->dispatch(new PdMenuEvent($menu), $menu->getId().'.event');
        }

        // Set Current URI
        $this->currentUri = $this->router->getContext()->getPathInfo();

        // Process Menu
        $this->recursiveProcess($menu, $options);

        return $menu;
    }

    /**
     * Process Menu Item.
     *
     * @param ItemInterface $menu
     * @param $options
     *
     * @return bool
     */
    protected function recursiveProcess(ItemInterface $menu, $options)
    {
        // Get Child Menus
        $childs = $menu->getChild();
        $listActive = false;

        // Parent Menu Route
        if (isset($menu->getChildAttr()['data-parent'])) {
            $menu->setChildAttr(['data-parent' => $this->router->generate($menu->getChildAttr()['data-parent'])]);
        }

        // Sort Current Child
        foreach ($childs as $child) {
            $childActive = false;

            // Set Child Process
            if ($child->getChild()) {
                // Set Menu Depth
                if (null !== $options['depth'] && ($child->getLevel() >= $options['depth'])) {
                    $child->setChild([]);
                    break;
                }

                // Set Child List Class
                $child->setChildAttr(array_merge_recursive($child->getChildAttr(), ['class' => 'menu_level_'.$child->getLevel()]));

                $childActive = $this->recursiveProcess($child, $options);
            }

            // Generate Route Link
            if ($child->getRoute()) {
                $child->setLink($this->router->generate($child->getRoute()['name'], $child->getRoute()['params']));
            }

                // Link & List Active Class
            if ($this->currentUri === $child->getLink() || $childActive) {
                $listActive = true;
                $child->setListAttr(array_merge_recursive($child->getListAttr(), ['class' => $options['currentClass']]));
                $child->setLinkAttr(array_merge_recursive($child->getLinkAttr(), ['class' => $options['currentClass']]));
            }

            // Item Security
            if ($child->getRoles()) {
                if (!$this->security->isGranted($child->getRoles())) {
                    unset($childs[$child->getId()]);
                }
            }
        }

        // Sort Item
        usort($childs, static function ($a, $b) {
            return $a->getOrder() > $b->getOrder();
        });

        // Set Childs
        $menu->setChild($childs);

        return $listActive;
    }
}
