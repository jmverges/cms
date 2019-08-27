<?php
namespace craft\gql\base;

use GraphQL\Type\Definition\Type;

/**
 * Class Arguments
 */
abstract class Arguments
{
    /**
     * Returns the argument fields to use in GQL type definitions.
     *
     * @return array $fields
     */
    public static function getArguments(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::listOf(Type::int()),
                'description' => 'Narrows the query results based on the {elements}’ IDs.'
            ],
            'uid' => [
                'name' => 'uid',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the {elements}’ UIDs.'
            ],
        ];
    }
}