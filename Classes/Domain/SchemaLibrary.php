<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

final class SchemaLibrary
{
    public const CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA = [
        'type' => 'object',
        'description' => 'A single CreateNodeAggregateWithNode command',
        'properties' => [
            'type' => [
                'type' => 'string',
                'const' => 'CreateNodeAggregateWithNode',
            ],
            'nodeTypeName' => ['type' => 'string'],
            'originDimensionSpacePoint' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
            'parentNodeAggregateId' => ['type' => 'string'],
            'initialPropertyValues' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
            'nodeAggregateId' => ['type' => 'string'],
            'succeedingSiblingNodeAggregateId' => ['type' => 'string'],
            'nodeName' => ['type' => 'string'],
        ],
        'required' => [
            'type',
            'nodeTypeName',
            'originDimensionSpacePoint',
            'parentNodeAggregateId',
            'initialPropertyValues',
        ],
        'additionalProperties' => false,
    ];

    public const SET_NODE_PROPERTIES_SCHEMA = [
        'type' => 'object',
        'description' => 'A single SetNodeProperties command',
        'properties' => [
            'type' => [
                'type' => 'string',
                'const' => 'SetNodeProperties',
            ],
            'nodeAggregateId' => ['type' => 'string'],
            'originDimensionSpacePoint' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
            'propertyValues' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ],
        'required' => [
            'type',
            'nodeAggregateId',
            'originDimensionSpacePoint',
            'propertyValues',
        ],
        'additionalProperties' => false,
    ];

    public const SET_NODE_REFERENCES_SCHEMA = [
        'type' => 'object',
        'description' => 'A single SetNodeReferences command',
        'properties' => [
            'type' => [
                'type' => 'string',
                'const' => 'SetNodeReferences',
            ],
            'nodeAggregateId' => ['type' => 'string'],
            'originDimensionSpacePoint' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string'],
            ],
            'referencesToWrite' => [
                'type' => 'object',
                'additionalProperties' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
        'required' => [
            'type',
            'nodeAggregateId',
            'originDimensionSpacePoint',
            'referencesToWrite',
        ],
        'additionalProperties' => false,
    ];

    public const COMMANDS_SCHEMA = [
        'oneOf' => [
            self::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA,
            self::SET_NODE_PROPERTIES_SCHEMA,
            self::SET_NODE_REFERENCES_SCHEMA,
        ],
        'discriminator' => [
            'propertyName' => 'type'
        ]
    ];
}
