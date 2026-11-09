<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Valasiadis\SecondaryVariantProperties\Service\ConfiguratorLoaderDecorator;

final class ConfiguratorLoaderDecoratorTest extends TestCase
{
    public function testPrimaryValuesIgnoreSecondarySelection(): void
    {
        $groups = $this->load(['s1', 'p1', 'w1']);

        // p2 has no variant with w1, but must not be greyed out
        static::assertSame(['p1' => true, 'p2' => true], $this->state($groups, 'P'));
        // s2 only exists sold out
        static::assertSame(['s1' => true, 's2' => false], $this->state($groups, 'S'));
    }

    public function testSecondaryValuesAreFilteredByPrimarySelection(): void
    {
        $groups = $this->load(['s1', 'p1', 'w1']);

        static::assertSame(['w1' => true, 'w2' => true], $this->state($groups, 'W'));
    }

    public function testSecondaryValuesAreGreyedOutWhenHidingIsDisabled(): void
    {
        $groups = $this->load(['s1', 'p1', 'w1'], hide: false);

        static::assertSame(['w1' => true, 'w2' => true, 'w3' => false, 'w4' => false], $this->state($groups, 'W'));
    }

    public function testSelectedSoldOutSecondaryValueStaysVisible(): void
    {
        $groups = $this->load(['s1', 'p2', 'w3']);

        static::assertSame(['w3' => false, 'w4' => true], $this->state($groups, 'W'));
    }

    public function testProductWithoutSecondaryGroupIsUntouched(): void
    {
        $groups = $this->load(['s1', 'p1', 'w1'], secondary: ['OTHER']);

        static::assertSame(['w1' => true, 'w2' => true, 'w3' => true, 'w4' => true], $this->state($groups, 'W'));
    }

    public function testLoadFromCombinationsIsAdjustedToo(): void
    {
        $inner = $this->inner();
        $decorator = new ConfiguratorLoaderDecorator($inner, Fixture::config(), Fixture::combinationLoader());

        $groups = $decorator->loadFromCombinations(
            Fixture::product('current', ['s1', 'p1', 'w1']),
            Fixture::combinations(),
            Fixture::context()
        );

        static::assertSame(['p1' => true, 'p2' => true], $this->state($groups, 'P'));
        static::assertSame(['w1' => true, 'w2' => true], $this->state($groups, 'W'));
    }

    /**
     * @param list<string> $current
     * @param list<string> $secondary
     */
    private function load(array $current, bool $hide = true, array $secondary = ['W']): PropertyGroupCollection
    {
        $decorator = new ConfiguratorLoaderDecorator($this->inner(), Fixture::config($secondary, $hide), Fixture::combinationLoader());

        return $decorator->load(Fixture::product('current', $current), Fixture::context());
    }

    /**
     * Inner loader returning all options as combinable, like an unfiltered core result.
     */
    private function inner(): ProductConfiguratorLoader
    {
        return new class extends ProductConfiguratorLoader {
            public function __construct()
            {
            }

            public function load(SalesChannelProductEntity $product, SalesChannelContext $context): PropertyGroupCollection
            {
                $groups = Fixture::groups();
                foreach ($groups as $group) {
                    foreach ($group->getOptions() ?? [] as $option) {
                        $option->setCombinable(true);
                    }
                }

                return $groups;
            }

            public function loadFromCombinations(SalesChannelProductEntity $product, mixed $combinations, SalesChannelContext $context): PropertyGroupCollection
            {
                return $this->load($product, $context);
            }
        };
    }

    /**
     * @return array<string, bool> option id => combinable
     */
    private function state(PropertyGroupCollection $groups, string $groupId): array
    {
        $result = [];
        foreach ($groups->get($groupId)?->getOptions() ?? [] as $option) {
            $result[$option->getId()] = $option->getCombinable();
        }

        return $result;
    }
}
