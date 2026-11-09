<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Tests\Unit;

use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Valasiadis\SecondaryVariantProperties\Service\SecondaryGroupConfig;

/**
 * Test product with three property groups:
 *   S (straightness, primary), P (spine, primary), W (weight, secondary)
 *
 * Variants (options, available):
 *   v1: s1 p1 w1  yes
 *   v2: s1 p1 w2  yes
 *   v3: s1 p2 w3  no (sold out)
 *   v4: s1 p2 w4  yes
 *   v5: s2 p1 w4  no (sold out)
 */
final class Fixture
{
    public const VARIANTS = [
        'v1' => [['s1', 'p1', 'w1'], true],
        'v2' => [['s1', 'p1', 'w2'], true],
        'v3' => [['s1', 'p2', 'w3'], false],
        'v4' => [['s1', 'p2', 'w4'], true],
        'v5' => [['s2', 'p1', 'w4'], false],
    ];

    /**
     * @return array<string, PropertyGroupOptionEntity>
     */
    public static function options(): array
    {
        $result = [];
        foreach (['s1' => 'S', 's2' => 'S', 'p1' => 'P', 'p2' => 'P', 'w1' => 'W', 'w2' => 'W', 'w3' => 'W', 'w4' => 'W'] as $id => $group) {
            $option = new PropertyGroupOptionEntity();
            $option->setId($id);
            $option->setGroupId($group);
            $option->setName($id);
            $option->setPosition((int) substr($id, 1));
            $option->setTranslated(['name' => $id]);
            $result[$id] = $option;
        }

        return $result;
    }

    public static function groups(): PropertyGroupCollection
    {
        $collection = new PropertyGroupCollection();
        foreach (['S', 'P', 'W'] as $groupId) {
            $group = new PropertyGroupEntity();
            $group->setId($groupId);
            $group->setName($groupId);
            $options = new PropertyGroupOptionCollection();
            foreach (self::options() as $option) {
                if ($option->getGroupId() === $groupId) {
                    $options->add($option);
                }
            }
            $group->setOptions($options);
            $collection->add($group);
        }

        return $collection;
    }

    /**
     * @param list<string> $optionIds
     */
    public static function product(string $id, array $optionIds, bool $available = true): SalesChannelProductEntity
    {
        $all = self::options();
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setParentId('parent');
        $product->setOptionIds($optionIds);
        $product->setAvailable($available);
        $options = new PropertyGroupOptionCollection();
        foreach ($optionIds as $optionId) {
            $options->add($all[$optionId]);
        }
        $product->setOptions($options);

        return $product;
    }

    public static function combinations(): AvailableCombinationResult
    {
        $result = new AvailableCombinationResult();
        foreach (self::VARIANTS as [$optionIds, $available]) {
            $result->addCombination($optionIds, $available);
        }

        return $result;
    }

    public static function combinationLoader(): AbstractAvailableCombinationLoader
    {
        return new class extends AbstractAvailableCombinationLoader {
            public function getDecorated(): AbstractAvailableCombinationLoader
            {
                throw new \LogicException('not decorated');
            }

            public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
            {
                return Fixture::combinations();
            }
        };
    }

    /**
     * @param list<string> $secondary
     */
    public static function config(array $secondary = ['W'], bool $hide = true, bool $hideCloseouts = false, bool $excludeFromSearch = true, bool $hideInFilter = true): SecondaryGroupConfig
    {
        return new class($secondary, $hide, $hideCloseouts, $excludeFromSearch, $hideInFilter) extends SecondaryGroupConfig {
            /**
             * @param list<string> $secondary
             */
            public function __construct(
                private readonly array $secondary,
                private readonly bool $hide,
                private readonly bool $hideCloseouts,
                private readonly bool $exclude,
                private readonly bool $hideInFilter,
            ) {
            }

            public function getSecondaryGroupIds(?string $salesChannelId): array
            {
                return $this->secondary;
            }

            public function hideUnavailable(?string $salesChannelId): bool
            {
                return $this->hide;
            }

            public function hideInListingFilter(?string $salesChannelId): bool
            {
                return $this->hideInFilter;
            }

            public function excludeFromSearch(): bool
            {
                return $this->exclude;
            }

            public function hideSoldOutCloseouts(?string $salesChannelId): bool
            {
                return $this->hideCloseouts;
            }
        };
    }

    public static function context(): SalesChannelContext
    {
        return new class extends SalesChannelContext {
            public function __construct()
            {
            }

            public function getSalesChannelId(): string
            {
                return 'sales-channel';
            }

            public function getContext(): Context
            {
                return Context::createDefaultContext();
            }
        };
    }
}
