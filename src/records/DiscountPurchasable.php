<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Discount product record.
 *
 * @property ActiveQueryInterface $discount
 * @property int $discountId
 * @property int $id
 * @property ActiveQueryInterface $purchasable
 * @property int $purchasableId
 * @property string $purchasableType
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class DiscountPurchasable extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::DISCOUNT_PURCHASABLES;
    }

    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['id' => 'discountId']);
    }

    public function getPurchasable(): ActiveQueryInterface
    {
        return $this->hasOne(Purchasable::class, ['id' => 'purchasableId']);
    }
}
