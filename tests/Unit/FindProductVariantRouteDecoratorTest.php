<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\AbstractFindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Valasiadis\SecondaryVariantProperties\Route\FindProductVariantRouteDecorator;

final class FindProductVariantRouteDecoratorTest extends TestCase
{
    public function testSwitchingPrimaryKeepsPrimariesAndPicksFirstAvailableSecondary(): void
    {
        // p2 has no variant with w1; v3 (w3) is sold out, so v4 (w4) is expected
        static::assertSame('v4', $this->find(['S' => 's1', 'P' => 'p2', 'W' => 'w1'], 'P'));
    }

    public function testExactAvailableVariantIsUsed(): void
    {
        static::assertSame('v2', $this->find(['S' => 's1', 'P' => 'p1', 'W' => 'w2'], 'W'));
    }

    public function testSoldOutVariantIsUsedIfNothingElseMatchesThePrimaries(): void
    {
        static::assertSame('v5', $this->find(['S' => 's2', 'P' => 'p1', 'W' => 'w1'], 'S'));
    }

    public function testHiddenSoldOutCloseoutIsNeverPicked(): void
    {
        // v5 is sold out; with hidden closeouts it must not be returned
        static::assertSame('DEFAULT', $this->find(['S' => 's2', 'P' => 'p1', 'W' => 'w1'], 'S', hideCloseouts: true));
    }

    public function testNonExistingPrimaryCombinationFallsBackToDefault(): void
    {
        static::assertSame('DEFAULT', $this->find(['S' => 's2', 'P' => 'p2', 'W' => 'w3'], 'S'));
    }

    public function testProductWithoutSecondaryGroupUsesDefault(): void
    {
        static::assertSame('DEFAULT', $this->find(['S' => 's1', 'P' => 'p1'], 'P'));
    }

    /**
     * @param array<string, string> $options
     */
    private function find(array $options, string $switchedGroup, bool $hideCloseouts = false): string
    {
        $decorator = new FindProductVariantRouteDecorator(
            $this->defaultRoute(),
            Fixture::config(hideCloseouts: $hideCloseouts),
            $this->repository(),
            new ProductCloseoutFilterFactory(),
        );
        $request = new Request(['options' => $options, 'switchedGroup' => $switchedGroup]);

        return $decorator->load('parent', $request, Fixture::context())->getFoundCombination()->getVariantId();
    }

    private function defaultRoute(): AbstractFindProductVariantRoute
    {
        return new class extends AbstractFindProductVariantRoute {
            public function getDecorated(): AbstractFindProductVariantRoute
            {
                throw new \LogicException('not decorated');
            }

            public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse
            {
                return new FindProductVariantRouteResponse(new FoundCombination('DEFAULT', []));
            }
        };
    }

    /**
     * @return SalesChannelRepository<ProductCollection>
     */
    private function repository(): SalesChannelRepository
    {
        /** @var SalesChannelRepository<ProductCollection>&\PHPUnit\Framework\MockObject\MockObject $repository */
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, SalesChannelContext $context): EntitySearchResult {
                $required = [];
                $onlyAvailable = false;
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof ProductCloseoutFilter) {
                        // simplified: every test variant counts as closeout
                        $onlyAvailable = true;
                    }
                    if ($filter instanceof EqualsFilter && $filter->getField() === 'product.optionIds') {
                        $required[] = (string) $filter->getValue();
                    }
                }

                $products = new ProductCollection();
                foreach (Fixture::VARIANTS as $id => [$optionIds, $available]) {
                    if (array_diff($required, $optionIds) === [] && ($available || !$onlyAvailable)) {
                        $products->add(Fixture::product($id, $optionIds, $available));
                    }
                }

                return new EntitySearchResult('sales_channel_product', $products->count(), $products, null, $criteria, $context->getContext());
            }
        );

        return $repository;
    }
}
