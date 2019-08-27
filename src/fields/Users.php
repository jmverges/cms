<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\fields;

use Craft;
use craft\elements\db\UserQuery;
use craft\elements\User;
use craft\gql\arguments\elements\User as UserArguments;
use craft\gql\interfaces\elements\User as UserInterface;
use craft\gql\resolvers\elements\User as UserResolver;
use GraphQL\Type\Definition\Type;

/**
 * Users represents a Users field.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class Users extends BaseRelationField
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Users');
    }

    /**
     * @inheritdoc
     */
    protected static function elementType(): string
    {
        return User::class;
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('app', 'Add a user');
    }

    /**
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return UserQuery::class;
    }

    // Public methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function getContentGqlType()
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(UserInterface::getType()),
            'args' => UserArguments::getArguments(),
            'resolve' => UserResolver::class . '::resolve',
        ];
    }
}
