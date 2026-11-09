<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Service;

use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Recalculates which variant values are selectable.
 *
 * - A primary value is selectable if an available variant exists with this value and the
 *   currently selected values of the other primary properties. Secondary values are ignored.
 * - A secondary value is selectable if an available variant exists with this value and all
 *   currently selected primary values. Depending on the configuration, non-selectable
 *   secondary values are removed or greyed out. The selected value always stays visible.
 *
 * ProductConfiguratorLoader is decorated (instead of the product detail route) because the
 * CMS buy box element calls it directly. ProductConfiguratorLoader has no abstract base class,
 * so this class extends it and only delegates; the parent constructor is intentionally not called.
 */
class ConfiguratorLoaderDecorator extends ProductConfiguratorLoader
{
    public function __construct(
        private readonly ProductConfiguratorLoader $decorated,
        private readonly SecondaryGroupConfig $config,
        private readonly AbstractAvailableCombinationLoader $combinationLoader,
    ) {
    }

    public function load(SalesChannelProductEntity $product, SalesChannelContext $context): PropertyGroupCollection
    {
        $groups = $this->decorated->load($product, $context);
        $parentId = $product->getParentId();

        if ($parentId === null || !$this->isRelevant($groups, $context)) {
            return $groups;
        }

        return $this->adjust($groups, $product, $this->combinationLoader->loadCombinations($parentId, $context), $context);
    }

    /**
     * Available since Shopware 6.7.15. Overridden so callers using it are adjusted as well.
     */
    public function loadFromCombinations(
        SalesChannelProductEntity $product,
        AvailableCombinationResult $combinations,
        SalesChannelContext $context,
    ): PropertyGroupCollection {
        // only needed for Shopware < 6.7.15, where the method does not exist yet
        // @phpstan-ignore function.alreadyNarrowedType
        if (!method_exists($this->decorated, 'loadFromCombinations')) {
            return $this->load($product, $context);
        }

        /** @var PropertyGroupCollection $groups */
        $groups = $this->decorated->loadFromCombinations($product, $combinations, $context);

        if ($product->getParentId() === null || !$this->isRelevant($groups, $context)) {
            return $groups;
        }

        return $this->adjust($groups, $product, $combinations, $context);
    }

    private function isRelevant(PropertyGroupCollection $groups, SalesChannelContext $context): bool
    {
        if ($groups->count() === 0) {
            return false;
        }

        $secondary = $this->config->getSecondaryGroupIds($context->getSalesChannelId());

        return array_intersect($secondary, $groups->getIds()) !== [];
    }

    private function adjust(
        PropertyGroupCollection $groups,
        SalesChannelProductEntity $product,
        AvailableCombinationResult $combinations,
        SalesChannelContext $context,
    ): PropertyGroupCollection {
        $secondary = $this->config->getSecondaryGroupIds($context->getSalesChannelId());
        $hide = $this->config->hideUnavailable($context->getSalesChannelId());

        // available combinations as lists of option IDs
        $available = [];
        foreach ($combinations->getCombinations() as $optionIds) {
            $optionIds = array_values($optionIds);
            if ($combinations->isAvailable($optionIds)) {
                $available[] = $optionIds;
            }
        }

        // currently selected option per property group
        $selectedIds = $product->getOptionIds() ?? [];
        $current = [];
        foreach ($groups as $group) {
            foreach ($group->getOptions() ?? [] as $option) {
                if (\in_array($option->getId(), $selectedIds, true)) {
                    $current[$group->getId()] = $option->getId();
                }
            }
        }
        $currentPrimary = array_diff_key($current, array_flip($secondary));

        foreach ($groups as $group) {
            $options = $group->getOptions();
            if ($options === null) {
                continue;
            }
            $isSecondary = \in_array($group->getId(), $secondary, true);

            foreach ($options as $option) {
                $required = $currentPrimary;
                unset($required[$group->getId()]);
                $required[] = $option->getId();

                $combinable = $this->existsAvailable($available, $required);
                $isSelected = ($current[$group->getId()] ?? null) === $option->getId();

                if ($isSecondary && $hide && !$combinable && !$isSelected) {
                    $options->remove($option->getId());

                    continue;
                }

                $option->setCombinable($combinable);
            }
        }

        return $groups;
    }

    /**
     * @param list<list<string>> $available
     * @param array<string> $required
     */
    private function existsAvailable(array $available, array $required): bool
    {
        foreach ($available as $optionIds) {
            if (array_diff($required, $optionIds) === []) {
                return true;
            }
        }

        return false;
    }
}
