<?php
namespace craft\gql\types;

use craft\gql\common\SchemaObject;
use craft\gql\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Class FieldGroup
 */
class FieldGroup extends SchemaObject
{
     /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'FieldGroup';
    }

    /**
     * @inheritdoc
     */
    public static function getFields(): array
    {
        return array_merge(parent::getCommonFields(), [
            'name' => Type::nonNull(Type::string()),
            // TODO fields?
            // 'fields' =>
        ]);
    }
}
