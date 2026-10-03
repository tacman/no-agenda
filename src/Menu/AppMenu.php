<?php

declare(strict_types=1);

namespace App\Menu;

use Survos\TablerBundle\Event\MenuEvent;
use Survos\TablerBundle\Traits\KnpMenuHelperInterface;
use Survos\TablerBundle\Traits\KnpMenuHelperTrait;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AppMenu implements KnpMenuHelperInterface
{
    use KnpMenuHelperTrait;

    #[AsEventListener(event: MenuEvent::NAVBAR_MENU)]
    public function navigation(MenuEvent $event): void
    {
        $this->add($event->getMenu(), 'episode_admin', label: 'Episode AI Lab', icon: 'brain');
        $this->add($event->getMenu(), 'episode_search', label: 'Search episodes', icon: 'search');
        $this->add($event->getMenu(), 'podcast', label: 'Podcast', icon: 'headphones');
        $this->add($event->getMenu(), 'admin', label: 'EasyAdmin', icon: 'settings');
    }
}
