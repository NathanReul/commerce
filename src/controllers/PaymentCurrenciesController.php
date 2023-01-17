<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use craft\db\Table as CraftTable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\Exception as DbException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Currencies Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrenciesController extends BaseStoreSettingsController
{
    /**
     * @throws CurrencyException
     */
    public function actionIndex(): Response
    {
        $currencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
        return $this->renderTemplate('commerce/store-settings/paymentcurrencies/index', compact('currencies'));
    }

    /**
     * @param int|null $id
     * @param PaymentCurrency|null $currency
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $id = null, PaymentCurrency $currency = null): Response
    {
        $variables = compact('id', 'currency');

        if (!$variables['currency']) {
            if ($variables['id']) {
                $variables['currency'] = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($variables['id']);

                if (!$variables['currency']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['currency'] = new PaymentCurrency();
            }
        }

        if ($variables['currency']->id) {
            $variables['title'] = $variables['currency']->currency . ' (' . $variables['currency']->iso . ')';
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new currency');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['currency'], prepend: true);

        $variables['storeCurrency'] = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $variables['currencies'] = array_keys(Plugin::getInstance()->getCurrencies()->getAllCurrencies());

        $variables['hasCompletedOrders'] = Order::find()->isCompleted(true)->exists();

        return $this->renderTemplate('commerce/store-settings/paymentcurrencies/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws DbException
     * @throws BadRequestHttpException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();

        $currency = new PaymentCurrency();

        // Shared attributes
        $currency->id = $this->request->getBodyParam('currencyId');
        $currency->storeId = $this->request->getBodyParam('storeId');
        $currency->iso = $this->request->getBodyParam('iso');
        $currency->rate = $this->request->getBodyParam('rate', 1);
        $currency->primary = (bool)$this->request->getBodyParam('primary');

        // Check to see if the primary currency is being changed
        $primaryCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
        $changingPrimaryCurrency = false;
        if ($currency->id && $currency->primary && $primaryCurrency && $primaryCurrency->iso != $currency->iso) {
            $changingPrimaryCurrency = true;
        }

        // Save it
        if (Plugin::getInstance()->getPaymentCurrencies()->savePaymentCurrency($currency)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Currency saved.'));

            // Delete all carts if primary currency is being changed
            if ($changingPrimaryCurrency) {
                $cartIds = Order::find()->isCompleted(false)->ids();
                if (!empty($cartIds)) {
                    // Delete in the same way that carts are purged
                    Craft::$app->getDb()->createCommand()
                        ->delete(CraftTable::ELEMENTS, ['id' => $cartIds])
                        ->execute();

                    Craft::$app->getDb()->createCommand()
                        ->delete(CraftTable::SEARCHINDEX, ['elementId' => $cartIds])
                        ->execute();
                }
            }
            $this->redirectToPostedUrl($currency);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save currency.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['currency' => $currency]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyById($id);

        if (!$currency || $currency->primary) {
            return $this->asFailure(Craft::t('commerce', 'You can not delete that currency.'));
        }

        Plugin::getInstance()->getPaymentCurrencies()->deletePaymentCurrencyById($id);
        return $this->asSuccess();
    }
}
