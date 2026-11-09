<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeywordCollection;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Framework\Context;
use Valasiadis\SecondaryVariantProperties\Service\SearchKeywordAnalyzerDecorator;
use Valasiadis\SecondaryVariantProperties\Service\SecondaryGroupConfig;

final class SearchKeywordAnalyzerDecoratorTest extends TestCase
{
    public function testSecondaryValuesAreNotIndexed(): void
    {
        static::assertSame(['s1', 'p1', 'w1'], $this->keywords(Fixture::config(excludeFromSearch: false)));
        static::assertSame(['s1', 'p1'], $this->keywords(Fixture::config()));
    }

    public function testProductPassedByCallerIsUnchanged(): void
    {
        $product = $this->product();
        $this->decorator(Fixture::config())->analyze($product, Context::createDefaultContext(), []);

        static::assertSame(3, $product->getOptions()?->count());
        static::assertSame(3, $product->getProperties()?->count());
    }

    /**
     * Keywords produced from the option names.
     *
     * @return list<string>
     */
    private function keywords(SecondaryGroupConfig $config): array
    {
        $result = $this->decorator($config)->analyze($this->product(), Context::createDefaultContext(), []);

        return array_values(array_map(static fn (AnalyzedKeyword $k): string => $k->getKeyword(), $result->getElements()));
    }

    private function decorator(SecondaryGroupConfig $config): SearchKeywordAnalyzerDecorator
    {
        // inner analyzer: one keyword per option name, like a search config with "options.name"
        $inner = new class implements ProductSearchKeywordAnalyzerInterface {
            public function analyze(ProductEntity $product, Context $context, array $configFields): AnalyzedKeywordCollection
            {
                $keywords = new AnalyzedKeywordCollection();
                foreach ($product->getOptions() ?? [] as $option) {
                    $keywords->add(new AnalyzedKeyword((string) $option->getName(), 1.0));
                }

                return $keywords;
            }
        };

        return new SearchKeywordAnalyzerDecorator($inner, $config);
    }

    private function product(): ProductEntity
    {
        $product = Fixture::product('v1', ['s1', 'p1', 'w1']);
        $options = $product->getOptions();
        static::assertNotNull($options);
        $product->setProperties(clone $options);

        return $product;
    }
}
