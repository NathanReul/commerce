<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\purchasables;

use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\SiteConditionRule;
use craft\elements\conditions\users\LastLoginDateConditionRule;

/**
 * Catalog Pricing Rule Purchasable condition builder.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRulePurchasableCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        $types = array_filter(parent::conditionRuleTypes(), static function($type) {
            return !in_array($type, [
                SiteConditionRule::class,
            ], true);
        });

        $types[] = SkuConditionRule::class;

        return $types;
    }
}