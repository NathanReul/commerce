<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\base;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\Store;

/**
 * Interface Purchasable
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
interface PurchasableInterface
{
    /**
     * Returns the element’s ID.
     */
    public function getId(): ?int;

    /**
     * Returns the base price the item will be added to the line item with.
     *
     * @return float|null decimal(14,4)
     */
    public function getBasePrice(string|Store|null $store = null): ?float;

    /**
     * @param string|Store|null $store
     * @return float|null
     * @since 5.0.0
     */
    public function getBasePromotionalPrice(string|Store|null $store = null): ?float;

    /**
     * @param string|Store|null $store
     * @return int|null
     * @since 5.0.0
     */
    public function getStock(string|Store|null $store = null): ?int;

    /**
     * @param string|Store|null $store
     * @return bool
     * @since 5.0.0
     */
    public function getHasUnlimitedStock(string|Store|null $store = null): bool;

    /**
     * @param string|Store|null $store
     * @return bool
     * @since 5.0.0
     */
    public function getPromotable(string|Store|null $store = null): bool;

    /**
     * @param string|Store|null $store
     * @return bool
     * @since 5.0.0
     */
    public function getAvailableForPurchase(string|Store|null $store = null): bool;

    /**
     * @param string|Store|null $store
     * @return int|null
     * @since 5.0.0
     */
    public function getMinQty(string|Store|null $store = null): ?int;

    /**
     * @param string|Store|null $store
     * @return int|null
     * @since 5.0.0
     */
    public function getMaxQty(string|Store|null $store = null): ?int;

    /**
     * @param string|Store|null $store
     * @return bool|null
     * @since 5.0.0
     */
    public function getFreeShipping(string|Store|null $store = null): ?bool;

    /**
     * @param float|null $price
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setBasePrice(?float $price, string|Store|null $store = null): void;

    /**
     * @param float|null $price
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setBasePromotionalPrice(?float $price, string|Store|null $store = null): void;

    /**
     * @param int|null $stock
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setStock(?int $stock, string|Store|null $store = null): void;

    /**
     * @param bool $hasUnlimitedStock
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setHasUnlimitedStock(bool $hasUnlimitedStock, string|Store|null $store = null): void;

    /**
     * @param bool $promotable
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setPromotable(bool $promotable, string|Store|null $store = null): void;

    /**
     * @param bool $availableForPurchase
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setAvailableForPurchase(bool $availableForPurchase, string|Store|null $store = null): void;

    /**
     * @param int|null $minQty
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setMinQty(?int $minQty, string|Store|null $store = null): void;

    /**
     * @param int|null $maxQty
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setMaxQty(?int $maxQty, string|Store|null $store = null): void;

    /**
     * @param bool $freeShipping
     * @param string|Store|null $store
     * @return void
     * @since 5.0.0
     */
    public function setFreeShipping(bool $freeShipping, string|Store|null $store = null): void;

    /**
     * Returns the base price the item will be added to the line item with.
     * It provides opportunity to populate the salePrice if sales have not already been applied.
     *
     * @return float decimal(14,4)
     */
    public function getSalePrice(): float;

    /**
     * Returns a unique code. Unique as per the commerce_purchasables table.
     */
    public function getSku(): string;

    /**
     * Returns your element's title or any additional descriptive information.
     */
    public function getDescription(): string;

    /**
     * Returns the purchasable's tax category ID.
     */
    public function getTaxCategoryId(): int;

    /**
     * Returns the purchasable's shipping category ID.
     */
    public function getShippingCategoryId(): int;

    /**
     * Returns whether the purchasable is currently available for purchase.
     */
    public function getIsAvailable(): bool;

    /**
     * Populates the line item when this purchasable is found on it. Called when
     * Purchasable is added to the cart and when the cart recalculates.
     * This is your chance to modify the weight, height, width, length, price
     * and saleAmount. This is called before any LineItems::EVENT_POPULATE_LINE_ITEM event listeners.
     */
    public function populateLineItem(LineItem $lineItem): void;

    /**
     * Returns an array of data that is serializable to json for storing a line
     * item at time of adding to the cart or order.
     */
    public function getSnapshot(): array;

    /**
     * Returns any validation rules this purchasable required the line item to have.
     *
     * @param LineItem $lineItem
     * @return array
     */
    public function getLineItemRules(LineItem $lineItem): array;

    /**
     * Runs any logic needed for this purchasable after it was on an order that was just completed (not when an order was paid, although paying an order will complete it).
     *
     * This is called for each line item the purchasable was contained within.
     *
     * @param Order $order
     * @param LineItem $lineItem
     */
    public function afterOrderComplete(Order $order, LineItem $lineItem): void;

    /**
     * Returns whether this purchasable has free shipping.
     */
    public function hasFreeShipping(): bool;

    /**
     * Returns whether this purchasable can be shipped and whether it is counted in shipping calculations.
     */
    public function getIsShippable(): bool;

    /**
     * Returns whether this purchasable is exempt from taxes.
     */
    public function getIsTaxable(): bool;

    /**
     * Returns whether this purchasable can be subject to discounts or sales.
     */
    public function getIsPromotable(): bool;

    /**
     * Returns the source param used for knowing if a promotion category is related to this purchasable.
     *
     * @return mixed
     */
    public function getPromotionRelationSource(): mixed;
}
