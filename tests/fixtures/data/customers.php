<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

return [
    'customer1' => [
        'active' => true,
        'username' => 'customer1',
        'email' => 'customer1@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
        '_userGroups' => [1002],
    ],
    'customer2' => [
        'active' => true,
        'firstName' => 'Customer',
        'lastName' => 'Two',
        'username' => 'customer2',
        'email' => 'customer2@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
        'field:myTestText' => 'Some test text.',
    ],
    'customer3' => [
        'active' => true,
        'firstName' => 'Customer',
        'lastName' => 'Three',
        'username' => 'customer3',
        'email' => 'customer3@crafttest.com',
        'fieldLayoutType' => 'craft\elements\User',
    ],
];
