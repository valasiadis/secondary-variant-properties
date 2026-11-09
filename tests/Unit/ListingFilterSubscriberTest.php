<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Valasiadis\SecondaryVariantProperties\Subscriber\ListingFilterSubscriber;

final class ListingFilterSubscriberTest extends TestCase
{
    public function testSecondaryGroupsAreRemovedFromTheFilter(): void
    {
        static::assertSame(['S', 'P'], $this->filterGroups(hide: true));
    }

    public function testFilterIsUnchangedWhenDisabled(): void
    {
        static::assertSame(['S', 'P', 'W'], $this->filterGroups(hide: false));
    }

    /**
     * @return list<string>
     */
    private function filterGroups(bool $hide): array
    {
        $groups = Fixture::groups();
        $context = Fixture::context();
        $result = new ProductListingResult(
            'product',
            0,
            new ProductCollection(),
            new AggregationResultCollection([new EntityResult('properties', $groups)]),
            new Criteria(),
            $context->getContext(),
        );

        (new ListingFilterSubscriber(Fixture::config(hideInFilter: $hide)))
            ->removeSecondaryGroups(new ProductListingResultEvent(new Request(), $result, $context));

        $properties = $result->getAggregations()->get('properties');
        static::assertInstanceOf(EntityResult::class, $properties);
        static::assertInstanceOf(PropertyGroupCollection::class, $properties->getEntities());

        return $properties->getEntities()->getKeys();
    }
}
