<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Route;

use Valasiadis\SecondaryVariantProperties\Service\SecondaryGroupConfig;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Variant lookup when the customer switches a value.
 *
 * If a primary value is switched and no available variant exists with the previous
 * secondary value, all primary values are kept and the first available secondary value
 * (in sort order) is selected. Shopware's default would instead drop properties from the
 * top, which can silently change another primary value.
 */
class FindProductVariantRouteDecorator extends AbstractFindProductVariantRoute
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly AbstractFindProductVariantRoute $decorated,
        private readonly SecondaryGroupConfig $config,
        private readonly SalesChannelRepository $productRepository,
        private readonly AbstractProductCloseoutFilterFactory $closeoutFilterFactory,
    ) {
    }

    public function getDecorated(): AbstractFindProductVariantRoute
    {
        return $this->decorated;
    }

    public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
    {
        $secondary = $this->config->getSecondaryGroupIds($context->getSalesChannelId());
        $options = RequestParamHelper::get($request, 'options', []);
        $switchedGroup = RequestParamHelper::get($request, 'switchedGroup');

        if (!\is_array($options) || array_intersect(array_keys($options), $secondary) === []) {
            return $this->decorated->load($productId, $request, $context);
        }
        foreach ($options as $optionId) {
            if (!\is_string($optionId)) {
                return $this->decorated->load($productId, $request, $context);
            }
        }

        // 1. exact variant, if available
        $exact = $this->search($productId, $context, array_values($options));
        foreach ($exact as $variant) {
            if ($variant->getAvailable()) {
                return $this->found($variant);
            }
        }

        // a secondary value was switched: default behaviour
        if (\is_string($switchedGroup) && \in_array($switchedGroup, $secondary, true)) {
            return $this->decorated->load($productId, $request, $context);
        }

        // 2. keep all primary values, choose the first available secondary value
        $primary = array_diff_key($options, array_flip($secondary));
        $candidates = $primary === [] ? [] : $this->search($productId, $context, array_values($primary));

        if ($candidates === []) {
            // the primary combination does not exist: default lookup without secondary values
            $fallback = $request->duplicate(['switchedGroup' => $switchedGroup, 'options' => $primary]);

            return $this->decorated->load($productId, $fallback, $context);
        }

        usort($candidates, fn (SalesChannelProductEntity $a, SalesChannelProductEntity $b): int => $this->sortKey($a, $secondary) <=> $this->sortKey($b, $secondary));

        return $this->found($candidates[0]);
    }

    /**
     * @param list<string> $optionIds
     *
     * @return list<SalesChannelProductEntity>
     */
    private function search(string $parentId, SalesChannelContext $context, array $optionIds): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('product.parentId', $parentId))
            ->addAssociation('options');

        foreach ($optionIds as $optionId) {
            $criteria->addFilter(new EqualsFilter('product.optionIds', $optionId));
        }

        // same as the core lookup: never pick a variant the storefront hides
        if ($this->config->hideSoldOutCloseouts($context->getSalesChannelId())) {
            $criteria->addFilter($this->closeoutFilterFactory->create($context));
        }

        /** @var list<SalesChannelProductEntity> $result */
        $result = array_values($this->productRepository->search($criteria, $context)->getEntities()->getElements());

        return $result;
    }

    /**
     * Available variants first, then by position and name of the secondary values.
     *
     * @param list<string> $secondary
     *
     * @return list<int|string>
     */
    private function sortKey(SalesChannelProductEntity $variant, array $secondary): array
    {
        $key = [$variant->getAvailable() ? 0 : 1];

        foreach ($secondary as $groupId) {
            foreach ($variant->getOptions() ?? [] as $option) {
                if ($option->getGroupId() === $groupId) {
                    $key[] = $option->getPosition() ?? 0;
                    $key[] = $this->naturalKey((string) $option->getTranslation('name'));
                }
            }
        }

        return $key;
    }

    /**
     * Pads numbers so that a plain string comparison sorts naturally ("9,5" < "10,1").
     */
    private function naturalKey(string $name): string
    {
        return (string) preg_replace_callback('/\d+/', static fn (array $m): string => str_pad($m[0], 10, '0', \STR_PAD_LEFT), $name);
    }

    private function found(SalesChannelProductEntity $variant): FindProductVariantRouteResponse
    {
        $options = [];
        foreach ($variant->getOptions() ?? [] as $option) {
            $options[$option->getGroupId()] = $option->getId();
        }

        return new FindProductVariantRouteResponse(new FoundCombination($variant->getId(), $options));
    }
}
