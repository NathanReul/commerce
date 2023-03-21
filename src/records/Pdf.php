<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\base\StoreRecordTrait;
use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Email record.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property string $templatePath
 * @property string $fileNameFormat
 * @property string $sortOrder
 * @property bool $enabled
 * @property bool $isDefault
 * @property string $language
 * @property int $storeId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class Pdf extends ActiveRecord
{
    use StoreRecordTrait;

    public const LOCALE_ORDER_LANGUAGE = 'orderLanguage';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PDFS;
    }
}
