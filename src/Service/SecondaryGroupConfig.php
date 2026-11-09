<?php declare(strict_types=1);

namespace Valasiadis\SecondaryVariantProperties\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Reads the plugin configuration (per sales channel, falling back to the global value).
 */
class SecondaryGroupConfig
{
    private const PREFIX = 'SecondaryVariantProperties.config.';

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    /**
     * IDs of the property groups that are configured as secondary.
     *
     * @return list<string>
     */
    public function getSecondaryGroupIds(?string $salesChannelId): array
    {
        $ids = $this->systemConfig->get(self::PREFIX . 'secondaryGroups', $salesChannelId);

        return \is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    /**
     * Whether non-matching secondary values are removed (true) or only greyed out (false).
     */
    public function hideUnavailable(?string $salesChannelId): bool
    {
        $value = $this->systemConfig->get(self::PREFIX . 'hideUnavailableSecondary', $salesChannelId);

        return $value === null ? true : (bool) $value;
    }

    /**
     * Whether secondary property groups are removed from the listing filter.
     */
    public function hideInListingFilter(?string $salesChannelId): bool
    {
        $value = $this->systemConfig->get(self::PREFIX . 'hideInListingFilter', $salesChannelId);

        return $value === null ? true : (bool) $value;
    }

    /**
     * Whether secondary values are left out of the product search keywords.
     * The search index is not built per sales channel, so only the global value is used.
     */
    public function excludeFromSearch(): bool
    {
        $value = $this->systemConfig->get(self::PREFIX . 'excludeFromSearch');

        return $value === null ? true : (bool) $value;
    }

    /**
     * Core setting: sold-out closeout products are hidden in the storefront.
     */
    public function hideSoldOutCloseouts(?string $salesChannelId): bool
    {
        return $this->systemConfig->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);
    }
}
