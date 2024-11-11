<?php

declare(strict_types=1);

namespace Tests\ProductFeed\MergadoBundle\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig;
use Shopsys\FrameworkBundle\Component\Domain\Domain;
use Shopsys\FrameworkBundle\Component\Money\Money;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency;
use Shopsys\FrameworkBundle\Model\Pricing\Currency\CurrencyFacade;
use Shopsys\FrameworkBundle\Model\Pricing\Price;
use Shopsys\FrameworkBundle\Model\Product\Availability\ProductAvailabilityFacade;
use Shopsys\FrameworkBundle\Model\Product\Brand\Brand;
use Shopsys\FrameworkBundle\Model\Product\Collection\ProductUrlsBatchLoader;
use Shopsys\FrameworkBundle\Model\Product\Pricing\ProductPrice;
use Shopsys\FrameworkBundle\Model\Product\Pricing\ProductPriceCalculationForCustomerUser;
use Shopsys\FrameworkBundle\Model\Product\Product;
use Shopsys\ProductFeed\MergadoBundle\Model\FeedItem\MergadoFeedItem;
use Shopsys\ProductFeed\MergadoBundle\Model\FeedItem\MergadoFeedItemFactory;
use Tests\FrameworkBundle\Test\IsMoneyEqual;

// TODO add proper tests
class MergadoFeedItemTest extends TestCase
{
    private ProductPriceCalculationForCustomerUser|MockObject $productPriceCalculationForCustomerUserMock;

    private CurrencyFacade|MockObject $currencyFacadeMock;

    private ProductUrlsBatchLoader|MockObject $productUrlsBatchLoaderMock;

    private ProductAvailabilityFacade|MockObject $productAvailabilityFacadeMock;

    private MergadoFeedItemFactory $mergadoFeedItemFactory;

    private Currency $defaultCurrency;

    private DomainConfig $defaultDomain;

    private Product|MockObject $defaultProduct;

    protected function setUp(): void
    {
        $this->productPriceCalculationForCustomerUserMock = $this->createMock(
            ProductPriceCalculationForCustomerUser::class,
        );
        $this->currencyFacadeMock = $this->createMock(CurrencyFacade::class);
        $this->productUrlsBatchLoaderMock = $this->createMock(ProductUrlsBatchLoader::class);
        $this->productAvailabilityFacadeMock = $this->getMockBuilder(ProductAvailabilityFacade::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isProductAvailableOnDomainCached'])
            ->getMock();
        $this->productAvailabilityFacadeMock->method('isProductAvailableOnDomainCached')->willReturn(true);

        $this->mergadoFeedItemFactory = new MergadoFeedItemFactory(
            $this->productPriceCalculationForCustomerUserMock,
            $this->currencyFacadeMock,
            $this->productUrlsBatchLoaderMock,
            $this->productAvailabilityFacadeMock,
        );

        $this->defaultCurrency = $this->createCurrencyMock(1, 'EUR');
        $this->defaultDomain = $this->createDomainConfigMock(
            Domain::FIRST_DOMAIN_ID,
            'https://example.com',
            'en',
            $this->defaultCurrency,
        );

        $this->defaultProduct = $this->createMock(Product::class);
        $this->defaultProduct->method('getId')->willReturn(1);
        $this->defaultProduct->method('getName')->with('en')->willReturn('product name');

        $this->mockProductPrice($this->defaultProduct, $this->defaultDomain, Price::zero());
        $this->mockProductUrl($this->defaultProduct, $this->defaultDomain, 'https://example.com/product-1');
    }

    /**
     * @param int $id
     * @param string $code
     * @return \Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency
     */
    private function createCurrencyMock(int $id, string $code): Currency
    {
        $currencyMock = $this->createMock(Currency::class);

        $currencyMock->method('getId')->willReturn($id);
        $currencyMock->method('getCode')->willReturn($code);

        return $currencyMock;
    }

    /**
     * @param int $id
     * @param string $url
     * @param string $locale
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Currency\Currency $currency
     * @return \Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig
     */
    private function createDomainConfigMock(int $id, string $url, string $locale, Currency $currency): DomainConfig
    {
        $domainConfigMock = $this->createMock(DomainConfig::class);

        $domainConfigMock->method('getId')->willReturn($id);
        $domainConfigMock->method('getUrl')->willReturn($url);
        $domainConfigMock->method('getLocale')->willReturn($locale);

        $this->currencyFacadeMock->method('getDomainDefaultCurrencyByDomainId')
            ->with($id)->willReturn($currency);

        return $domainConfigMock;
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product $product
     * @param \Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig $domain
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Price $price
     */
    private function mockProductPrice(Product $product, DomainConfig $domain, Price $price): void
    {
        $productPrice = new ProductPrice($price, false);
        $this->productPriceCalculationForCustomerUserMock->method('calculatePriceForCustomerUserAndDomainId')
            ->with($product, $domain->getId(), null)->willReturn($productPrice);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product $product
     * @param \Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig $domain
     * @param string $url
     */
    private function mockProductUrl(Product $product, DomainConfig $domain, string $url): void
    {
        $this->productUrlsBatchLoaderMock->method('getProductUrl')
            ->with($product, $domain)->willReturn($url);
    }

    /**
     * @param \Shopsys\FrameworkBundle\Model\Product\Product $product
     * @param \Shopsys\FrameworkBundle\Component\Domain\Config\DomainConfig $domain
     * @param string $url
     */
    private function mockProductImageUrl(Product $product, DomainConfig $domain, string $url): void
    {
        $this->productUrlsBatchLoaderMock->method('getResizedProductImageUrl')
            ->with($product, $domain)->willReturn($url);
    }

    public function testMinimalMergadoFeedItemIsCreatable(): void
    {
        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertInstanceOf(MergadoFeedItem::class, $mergadoFeedItem);

        self::assertEquals(1, $mergadoFeedItem->getId());
        self::assertEquals(1, $mergadoFeedItem->getSeekId());
        self::assertEquals('product name', $mergadoFeedItem->getName());
        self::assertNull($mergadoFeedItem->getBrand());
        self::assertNull($mergadoFeedItem->getDescription());
        self::assertEquals('https://example.com/product-1', $mergadoFeedItem->getUrl());
        self::assertNull($mergadoFeedItem->getImageUrl());
        self::assertEquals('in_stock', $mergadoFeedItem->getAvailability());
        self::assertThat($mergadoFeedItem->getPrice()->getPriceWithoutVat(), new IsMoneyEqual(Money::zero()));
        self::assertThat($mergadoFeedItem->getPrice()->getPriceWithVat(), new IsMoneyEqual(Money::zero()));
        self::assertEquals('EUR', $mergadoFeedItem->getCurrency()->getCode());
        self::assertEquals([], $mergadoFeedItem->getIdentifiers());
    }

    public function testMergadoFeedItemWithBrand(): void
    {
        /** @var \Shopsys\FrameworkBundle\Model\Product\Brand\Brand|\PHPUnit\Framework\MockObject\MockObject $brand */
        $brand = $this->createMock(Brand::class);
        $brand->method('getName')->willReturn('brand name');
        $this->defaultProduct->method('getBrand')->willReturn($brand);

        $mergadoFeedItem = $this->mergadoFeedItemFactory->create($this->defaultProduct, $this->defaultDomain);

        self::assertEquals('brand name', $mergadoFeedItem->getBrand());
    }

    public function testMergadoFeedItemWithDescription()
    {
        $this->defaultProduct->method('getDescriptionAsPlainText')
            ->with(1)->willReturn('product description');

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertEquals('product description', $mergadoFeedItem->getDescription());
    }

    public function testMergadoFeedItemWithImageLink()
    {
        $this->mockProductImageUrl($this->defaultProduct, $this->defaultDomain, 'https://example.com/img/product/1');

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertEquals('https://example.com/img/product/1', $mergadoFeedItem->getImageUrl());
    }

    public function testMergadoFeedItemWithSellingDenied()
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(1);
        $product->method('getName')->with('en')->willReturn('product name');
        $product->method('getCalculatedSellingDenied')->willReturn(true);

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($product, $this->defaultDomain);

        self::assertEquals('out_of_stock', $mergadoFeedItem->getAvailability());
    }

    public function testMergadoFeedItemWithEan()
    {
        $this->defaultProduct->method('getEan')->willReturn('1234567890123');

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertEquals(['gtin' => '1234567890123'], $mergadoFeedItem->getIdentifiers());
    }

    public function testMergadoFeedItemWithPartno()
    {
        $this->defaultProduct->method('getPartno')->willReturn('HSC0424PP');

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertEquals(['mpn' => 'HSC0424PP'], $mergadoFeedItem->getIdentifiers());
    }

    public function testMergadoFeedItemWithEanAndPartno()
    {
        $this->defaultProduct->method('getEan')->willReturn('1234567890123');
        $this->defaultProduct->method('getPartno')->willReturn('HSC0424PP');

        $mergadoFeedItem = $this->mergadoFeedItemFactory->createForProduct($this->defaultProduct, $this->defaultDomain);

        self::assertEquals(['gtin' => '1234567890123', 'mpn' => 'HSC0424PP'], $mergadoFeedItem->getIdentifiers());
    }
}
