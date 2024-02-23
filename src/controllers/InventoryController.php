<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryMovementType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\InventoryMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryItem;
use craft\commerce\Plugin;
use craft\commerce\web\assets\inventory\InventoryAsset;
use craft\enums\MenuItemType;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\assets\htmx\HtmxAsset;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Inventory controller
 */
class InventoryController extends Controller
{
    public $defaultAction = 'index';

    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function actionItemEdit(?int $inventoryItemId = null, ?InventoryItem $inventoryItem = null): Response
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(HtmxAsset::class);

        if ($inventoryItemId !== null) {
            if ($inventoryItem === null) {
                $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);

                if (!$inventoryItem) {
                    throw new NotFoundHttpException('Inventory Item not found');
                }
            }
        } else {
            if ($inventoryItem === null) {
                throw new NotFoundHttpException('Inventory Item not found');
            }
        }

        $params = [
            'inventoryItem' => $inventoryItem,
        ];

        return $this->asCpScreen()
            ->title('Inventory Item')
            ->action('commerce/inventory/item-save')
            ->submitButtonLabel(Craft::t('app', 'Save'))
            ->redirectUrl('commerce/inventory')
            ->contentTemplate('commerce/inventory/item/_edit.twig', $params)
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->tabs(
                [
                    'details' => [
                        'label' => Craft::t('commerce', 'Details'),
                        'url' => '#details',
                    ],
                    'history' => [
                        'label' => Craft::t('commerce', 'History'),
                        'url' => '#history',
                    ],
                ])
            ->prepareScreen(
                function(Response $response, string $containerId) use ($params) {
                    /** @var CpScreenResponseBehavior $response */
                    $this->getView()->registerJs('htmx.process(document.getElementById("' . $containerId . '"));');
                }
            );
    }

    public function actionItemSave(): Response
    {
        $inventoryItemId = Craft::$app->getRequest()->getRequiredParam('inventoryItemId');

        if ($inventoryItemId) {
            $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);
        } else {
            throw new HttpException(404);
        }

        $inventoryItem->countryCodeOfOrigin = Craft::$app->getRequest()->getParam('countryCodeOfOrigin', $inventoryItem->countryCodeOfOrigin);
        $inventoryItem->administrativeAreaCodeOfOrigin = Craft::$app->getRequest()->getParam('administrativeAreaCodeOfOrigin', $inventoryItem->administrativeAreaCodeOfOrigin);
        $inventoryItem->harmonizedSystemCode = Craft::$app->getRequest()->getParam('harmonizedSystemCode', $inventoryItem->harmonizedSystemCode);

        $success = Plugin::getInstance()->getInventory()->saveInventoryItem($inventoryItem);

        if (!$success) {
            return $this->asModelFailure($inventoryItem, Craft::t('app', 'Couldn’t save inventory item.'), 'inventoryItem');
        }

        return $this->asModelSuccess($inventoryItem, Craft::t('app', 'inventory Item saved.'), 'inventoryItem');
    }

    /**
     * commerce/inventory action
     */
    public function actionEditLocationLevels(?string $inventoryLocationHandle = null): Response
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(InventoryAsset::class);

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        if (!$inventoryLocationHandle) {
            $inventoryLocationHandle = Craft::$app->getRequest()->getParam('inventoryLocationHandle');

            if (!$inventoryLocationHandle) {
                return $this->redirect('commerce/inventory/levels/' . $inventoryLocations[0]->handle);
            }
        }

        $search = Craft::$app->getRequest()->getQueryParam('search');

        $currentLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationByHandle($inventoryLocationHandle);
        $selectedItem = 'levels-' . $currentLocation->handle;
        $title = $currentLocation->name . ' ' . Craft::t('commerce', 'Inventory');

        return $this->asCpScreen()
            ->title($title)
            ->action(null)
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->addCrumb($title, 'commerce/inventory')
            ->contentTemplate('commerce/inventory/levels/_index', compact(
                'inventoryLocations',
                'currentLocation',
                'selectedItem',
                'search',
            ))
            ->selectedSubnavItem('inventory')
            ->pageSidebarTemplate('commerce/inventory/_sidebar', compact(
                'inventoryLocations',
                'currentLocation',
                'selectedItem',
            ));
    }

    public function actionInventoryLevelsTableData(): Response
    {
        $inventoryLevelsManagerContainerId = $this->request->getRequiredParam('containerId');
        $page = $this->request->getParam('page', 1);
        $limit = $this->request->getParam('per_page', 100);
        $offset = ($page - 1) * $limit;
        $inventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryLocationId');
        $search = $this->request->getParam('search');

        $inventoryQuery = Plugin::getInstance()->getInventory()->getInventoryLevelQuery(limit: $limit, offset: $offset)
            ->andWhere(['inventoryLocationId' => $inventoryLocationId]);

        if ($search) {
            $inventoryQuery->addSelect(['purchasables.description', 'purchasables.sku']);
            $inventoryQuery->leftJoin(['purchasables' => Table::PURCHASABLES], '[[ii.purchasableId]] = [[purchasables.id]]');
            $inventoryQuery->andWhere(['or', ['like', 'purchasables.description', $search], ['like', 'purchasables.sku', $search]]);
        }

        $sort = $this->request->getParam('sort');
        if ($sort) {
            $field = $sort[0]['sortField'];
            $direction = $sort[0]['direction'];

            if ($field && $direction) {
                $inventoryQuery->addOrderBy($field . ' ' . $direction);
            }
        }

        $total = Plugin::getInstance()->getInventory()->getInventoryLevelQuery()
            ->andWhere(['inventoryLocationId' => $inventoryLocationId])
            ->count();

        $inventoryTableData = $inventoryQuery->all();

        $view = Craft::$app->getView();
        $time = microtime(true);
        foreach ($inventoryTableData as $key => &$inventoryLevel) {
            $inventoryItemModel = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryLevel['inventoryItemId']);
            $id = $inventoryLevel['inventoryItemId'];
            $purchasable = $inventoryItemModel->getPurchasable();
            $inventoryItemDomId = sprintf("edit-$id-link-%s", mt_rand());;
            $inventoryLevel['title'] = $purchasable?->getDescription() ?? '';
            $inventoryLevel['url'] = $purchasable?->getCpEditUrl() ?? '';
            $inventoryLevel['id'] = $inventoryLevel['inventoryItemId'];

            if ($purchasable) {
                $purchasableChip = Cp::elementChipHtml($purchasable, [
                    'id' => $id,
                    'url' => $purchasable->getCpEditUrl(),
                ]);
                $purchasableChip = Html::tag('div',  $purchasableChip, ['class' => 'flex-grow']);
                $itemLink = Html::tag('div',Html::a($purchasable?->getSku() , "#", ['id' => "$inventoryItemDomId"]));
                $inventoryLevel['item'] = Html::tag('div', $purchasableChip . $itemLink, ['class' => 'flex']);
            } else {
                $inventoryLevel['item'] = '';
            }


            $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
	e.preventDefault();
	const slideout = new Craft.CpScreenSlideout('commerce/inventory/item-edit', $params);
	slideout.on('close', (e) => {
	  $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
	});
});
JS, [
                $inventoryItemDomId,
                ['params' => ['inventoryItemId' => $id]],
                $inventoryLevelsManagerContainerId,
            ]);

            // TODO: Look to reduce the number of modal click listeners.
            $columnTypes = [...InventoryMovementType::values(), 'onHand'];
            foreach ($columnTypes as $type) {
                $items = [];
                $id = $inventoryLevel['id'];

                $showOrderLinks = (
                    $type == InventoryMovementType::COMMITTED->value &&
                    $inventoryLevel['committedTotal'] > 0
                );

                if ($showOrderLinks) {
                    $showOrderLinksId = sprintf("$type-show-$id-order-links-%s", mt_rand());
                    $items['orderLinks'] = [
                        'type' => MenuItemType::Button,
                        'id' => $showOrderLinksId,
                        'label' => Craft::t('commerce', 'See Orders'),
                        'icon' => 'cart-shopping',
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.CpModal('commerce/inventory/unfulfilled-orders', {
        containerElement: 'div',
        showSubmitButton: false,
        params: $params
    })
    modal.on('close', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $showOrderLinksId,
                        [
                            'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                $showSet = (
                    $type == 'onHand' ||
                    in_array(InventoryMovementType::from($type), InventoryMovementType::allowedManualAdjustmentTypes())
                );

                if ($showSet) {
                    $setId = sprintf("$type-update-level-$id-set-%s", mt_rand());
                    $items['set'] = [
                        'type' => MenuItemType::Button,
                        'id' => $setId,
                        'label' => Craft::t('commerce', 'Set Quantity'),
                        'icon' => 'bullseye',
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.UpdateInventoryLevelModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $setId,
                        [
                            'ids' => [$inventoryLevel['inventoryItemId']],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            'updateAction' => InventoryUpdateQuantityType::SET->value,
                            'type' => $type,
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                // Leave as it until we add more conditions for showing an adjustment
                $showAdjust = $showSet;

                if ($showAdjust) {
                    $adjustId = sprintf("$type-update-level-$id-adjust-%s", mt_rand());
                    $items['adjust'] = [
                        'type' => MenuItemType::Button,
                        'id' => $adjustId,
                        'icon' => 'arrow-trend-up',
                        'label' => Craft::t('commerce', 'Adjust Quantity'),
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.UpdateInventoryLevelModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $adjustId,
                        [
                            'ids' => [$inventoryLevel['inventoryItemId']],
                            'inventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            'updateAction' => InventoryUpdateQuantityType::ADJUST->value,
                            'type' => $type,
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }

                $showMovement = (
                    $type !== 'onHand' &&
                    in_array(InventoryMovementType::from($type), InventoryMovementType::allowedManualMovementTypes()) &&
                    $inventoryLevel[$type . 'Total'] > 0);

                if ($showMovement) {
                    $movementId = sprintf("$type-inventory-movement-$id-%s", mt_rand());
                    $items['movement'] = [
                        'type' => MenuItemType::Button,
                        'id' => $movementId,
                        'icon' => 'arrow-right',
                        'label' => Craft::t('commerce', 'Move Inventory'),
                    ];

                    $view->registerJsWithVars(fn($id, $params, $inventoryLevelsManagerContainerId) => <<<JS
$('#' + $id).on('click', (e) => {
    e.preventDefault();
    let modal = new Craft.Commerce.InventoryMovementModal({
        params: $params,
        showHeader: true
    })
    modal.on('submit', (e) => {
      console.log(e);
      $($inventoryLevelsManagerContainerId).data('inventoryLevelsManager').adminTable.reload();
    });
});
JS, [
                        $movementId,
                        [
                            'inventoryMovement' => [
                                'note' => '',
                                'fromInventoryMovementType' => $type,
                                'quantity' => '0',
                                'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                                'fromInventoryLocationId' => $inventoryLevel['inventoryLocationId'],
                            ],
                        ],
                        $inventoryLevelsManagerContainerId,
                    ]);
                }


                $config = [
                    'class' => '',
                    'hiddenLabel' => Craft::t('app', 'Actions'),
                    'buttonAttributes' => [
                        'class' => ['action-btn'],
                        'data' => [
                            'icon' => 'ellipsis',
                            'inventoryItemId' => $inventoryLevel['inventoryItemId'],
                            'inventoryLocationId' => $inventoryLocationId,
                            'type' => $type,
                        ],
                    ],
                ];
                $valueDiv = Html::tag('div', $inventoryLevel[$type . 'Total'], ['class' => '']);
                $actionButton = Html::tag('div', Cp::disclosureMenu($items, $config), ['class' => 'flex-grow']);
                $inventoryLevel[$type] = Html::tag('div',
                    $valueDiv .
                    (count($items) ? $actionButton : ''),
                    ['class' => 'flex']);
            }
        }

        $totalTime = sprintf(' (time: %.3fs)', microtime(true) - $time);
        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $total, $limit),
            'data' => $inventoryTableData,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    public function actionUpdateLevels(): Response
    {
        $updateAction = InventoryUpdateQuantityType::from(Craft::$app->getRequest()->getRequiredParam('updateAction'));
        $quantity = (int)Craft::$app->getRequest()->getRequiredParam('quantity', 0);
        $note = Craft::$app->getRequest()->getRequiredParam('note', '');
        $inventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryLocationId');
        $inventoryItemIds = Craft::$app->getRequest()->getRequiredParam('ids', []);
        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
        $type = Craft::$app->getRequest()->getParam('type', 'onHand');

        // We don't add zero amounts as transactions movements
        if ($updateAction === InventoryUpdateQuantityType::ADJUST && $quantity == 0) {
            return $this->asSuccess(Craft::t('commerce', 'No inventory changes made.'));
        }

        $errors = [];
        $updateInventoryLevels = UpdateInventoryLevelCollection::make();
        foreach ($inventoryItemIds as $inventoryItemId) {
            $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);

            $updateInventoryLevels->push(new UpdateInventoryLevel([
                    'type' => $type,
                    'updateAction' => $updateAction,
                    'inventoryItem' => $inventoryItem,
                    'inventoryLocation' => $inventoryLocation,
                    'quantity' => $quantity,
                    'note' => $note,
                ])
            );
        }


        if (!Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels($updateInventoryLevels)) {
            $errors['updateQuantities'] = [Craft::t('commerce', 'Inventory could not be set.')];
        }

        if (count($errors) > 0) {
            return $this->asFailure(Craft::t('commerce', 'Inventory was not updated.',),
                ['errors' => $errors]
            );
        }

        return $this->asSuccess(Craft::t('commerce', 'Inventory updated.'));
    }

    /**
     * @return Response
     */
    public function actionEditUpdateLevels(): Response
    {
        $inventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryLocationId');
        $note = Craft::$app->getRequest()->getParam('note', '');
        $inventoryItemIds = (array)Craft::$app->getRequest()->getParam('ids', []); // param needs to be 'ids' to be compatible with admin table
        $updateAction = Craft::$app->getRequest()->getParam('updateAction', 'adjust');
        $quantity = (int)Craft::$app->getRequest()->getParam('quantity', 0);
        $type = Craft::$app->getRequest()->getParam('type', 'onHand');

        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);

        $inventoryLevels = [];
        foreach ($inventoryItemIds as $inventoryItemId) {
            $item = Plugin::getInstance()->getInventory()->getInventoryItemById((int)$inventoryItemId);
            $inventoryLevels[] = Plugin::getInstance()->getInventory()->getInventoryLevel($item, $inventoryLocation);
        }

        $params = [
            'inventoryLocationId' => $inventoryLocationId,
            'inventoryItemIds' => $inventoryItemIds,
            'inventoryLevels' => $inventoryLevels,
            'updateAction' => $updateAction,
            'inventoryLocationOptions' => Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations()->mapWithKeys(function($location) {
                return [$location->id => $location->name];
            })->all(),
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
        ];

        return $this->asCpModal()
            ->action('commerce/inventory/update-levels')
            ->submitButtonLabel(Craft::t('commerce', 'Update'))
            ->contentTemplate('commerce/inventory/levels/_updateInventoryLevelModal', $params);
    }

    public function actionSaveInventoryMovement(): Response
    {
        $fromInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.toInventoryLocationId');
        $note = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.note');
        $fromInventoryMovementType = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryMovementType');
        $toInventoryMovementType = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.toInventoryMovementType');
        $inventoryItemId = Craft::$app->getRequest()->getRequiredParam('inventoryMovement.inventoryItemId');
        $quantity = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.quantity');

        $inventoryMovement = new InventoryMovement(
            [
                'inventoryItem' => Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId),
                'fromInventoryLocation' => Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($fromInventoryLocationId),
                'toInventoryLocation' => Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($toInventoryLocationId),
                'fromInventoryMovementType' => InventoryMovementType::from($fromInventoryMovementType),
                'toInventoryMovementType' => InventoryMovementType::from($toInventoryMovementType),
                'quantity' => $quantity,
                'note' => $note,
            ]
        );

        if ($inventoryMovement->validate()) {
            $inventoryMovementCollection = InventoryMovementCollection::make()->push($inventoryMovement);
            if (!Plugin::getInstance()->getInventory()->executeInventoryMovements($inventoryMovementCollection)) {
                return $this->asFailure(Craft::t('commerce', 'Inventory movement could not be saved.'));
            }
        }

        return $this->asSuccess(Craft::t('commerce', 'Inventory movement saved.'));
    }

    /**
     * @return Response
     */
    public function actionEditMovement(): Response
    {
        $fromInventoryLocationId = (int)Craft::$app->getRequest()->getRequiredParam('inventoryMovement.fromInventoryLocationId');
        $toInventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryMovement.toInventoryLocationId', $fromInventoryLocationId);
        $note = Craft::$app->getRequest()->getParam('inventoryMovement.note', '');
        $fromInventoryMovementType = Craft::$app->getRequest()->getParam('inventoryMovement.fromInventoryMovementType');
        $toInventoryMovementType = Craft::$app->getRequest()->getParam('inventoryMovement.toInventoryMovementType');
        $inventoryItemId = Craft::$app->getRequest()->getParam('inventoryMovement.inventoryItemId');
        $quantity = (int)Craft::$app->getRequest()->getParam('inventoryMovement.quantity', 0);

        $movableTo = collect(InventoryMovementType::allowedManualMovementTypes())
            ->filter(fn($type) => $type->value !== $fromInventoryMovementType)
            ->mapWithKeys(fn($type) => [$type->value => $type->typeAsLabel()]);

        $toInventoryMovementType = InventoryMovementType::tryFrom($toInventoryMovementType);
        if (!$toInventoryMovementType) {
            $toInventoryMovementType = $movableTo->keys()->first();
        }

        $inventoryMovement = new InventoryMovement(
            [
                'inventoryItem' => Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId),
                'fromInventoryLocation' => Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($fromInventoryLocationId),
                'toInventoryLocation' => Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($toInventoryLocationId),
                'fromInventoryMovementType' => InventoryMovementType::from($fromInventoryMovementType),
                'toInventoryMovementType' => InventoryMovementType::from($toInventoryMovementType),
                'quantity' => $quantity,
                'note' => $note,
            ]
        );

        $fromLevel = Plugin::getInstance()->getInventory()->getInventoryLevel($inventoryMovement->inventoryItem, $inventoryMovement->fromInventoryLocation);
        $fromTotal = $fromLevel->{$fromInventoryMovementType . 'Total'};

        $movableTo = $movableTo->toArray();
        $params = [
            'inventoryMovement' => $inventoryMovement,
            'toInventoryMovementTypes' => $movableTo,
            'maxFromQuantity' => $fromTotal,
        ];

        return $this->asCpModal()
            ->action('commerce/inventory/save-inventory-movement')
            ->submitButtonLabel(Craft::t('commerce', 'Move'))
            ->contentTemplate('commerce/inventory/levels/_inventoryMovementModal', $params);
    }

    public function actionUnfulfilledOrders(): Response
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(InventoryAsset::class);

        $inventoryLocationId = Craft::$app->getRequest()->getParam('inventoryLocationId');
        $inventoryItemId = Craft::$app->getRequest()->getParam('inventoryItemId');

        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);
        $inventoryItem = Plugin::getInstance()->getInventory()->getInventoryItemById($inventoryItemId);

        $orders = Plugin::getInstance()->getInventory()->getUnfulfilledOrders($inventoryItem, $inventoryLocation);

        $title = Craft::t('commerce', '{count} Unfulfilled Orders', [
            'count' => count($orders),
        ]);

        return $this->asCpModal()
            ->action(null)
            ->contentTemplate('commerce/inventory/levels/_unfulfilledOrdersModal', compact(
                'title',
                'orders'
            ));
    }
}