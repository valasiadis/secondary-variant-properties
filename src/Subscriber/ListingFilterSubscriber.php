<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Valasiadis\SecondaryVariantProperties\Service\SecondaryGroupConfig;

/**
 * Removes secondary property groups from the property filter of category listings and
 * search results. The core builds the filter before these events, so only the displayed
 * groups are removed; filtering itself is unchanged.
 */
class ListingFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SecondaryGroupConfig $config)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => 'removeSecondaryGroups',
            ProductEvents::PRODUCT_SEARCH_RESULT => 'removeSecondaryGroups',
        ];
    }

    public function removeSecondaryGroups(ProductListingResultEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->config->hideInListingFilter($salesChannelId)) {
            return;
        }

        $properties = $event->getResult()->getAggregations()->get('properties');
        if (!$properties instanceof EntityResult) {
            return;
        }

        $groups = $properties->getEntities();
        foreach ($this->config->getSecondaryGroupIds($salesChannelId) as $groupId) {
            $groups->remove($groupId);
        }
    }
}
