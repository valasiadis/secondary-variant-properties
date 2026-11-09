<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\Context;

/**
 * Leaves secondary values out of the product search keywords, so that searching for
 * e.g. a weight class does not return products. Applies when the search configuration
 * indexes option or property names. Primary values and all other fields are unchanged.
 */
class SearchKeywordAnalyzerDecorator implements ProductSearchKeywordAnalyzerInterface
{
    public function __construct(
        private readonly ProductSearchKeywordAnalyzerInterface $decorated,
        private readonly SecondaryGroupConfig $config,
    ) {
    }

    public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection
    {
        $secondary = $this->config->getSecondaryGroupIds(null);

        if ($secondary === [] || !$this->config->excludeFromSearch()) {
            return $this->decorated->analyze($product, $context, $configFields);
        }

        $isPrimary = static fn (PropertyGroupOptionEntity $option): bool => !\in_array($option->getGroupId(), $secondary, true);

        // work on a copy, the caller may still use the product
        $copy = clone $product;
        $options = $product->getOptions();
        if ($options !== null) {
            $copy->setOptions($options->filter($isPrimary));
        }
        $properties = $product->getProperties();
        if ($properties !== null) {
            $copy->setProperties($properties->filter($isPrimary));
        }

        return $this->decorated->analyze($copy, $context, $configFields);
    }
}
