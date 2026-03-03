<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

final class SchemaLibrary
{
    public const CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA = [
        'type' => 'object',
        'description' => 'A single CreateNodeAggregateWithNode command. Creates a new node with the given parameters.
            If you want to perform subsequent actions using the created node, you can provide its nodeAggregateId with the optional nodeAggregateId parameter. By default, UUIDs are to be used.
            Node names are strictly optional and should not be used for regular editorial nodes.
            The succeedingSiblingNodeAggregateId is optional, only use it if a position relative to the siblings is explicitly requested.
            The optional references property accepts a single or an array of nodeAggregateIds per reference name.
            References must be set via the references property, as a single node aggregate id or a list of them per reference name.
            Remember that tethered children don\'t need to be created explicitly as they are created automatically created together with their parent.
            Returns the nodeAggregateId and nodeName of the created node as well as the nodeAggregateIds of the tethered descendants that were created additionally.',
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
            'references' => [
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
            'nodeTypeName',
            'originDimensionSpacePoint',
            'parentNodeAggregateId',
            'initialPropertyValues',
        ],
        'additionalProperties' => false,
    ];

    public const SET_NODE_PROPERTIES_SCHEMA = [
        'type' => 'object',
        'description' => 'A single SetNodeProperties command. References must not be set with this, use SetNodeReferences instead.',
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
        'description' => 'A single SetNodeReferences command. Sets references on an existing node to one or more other existing nodes.
            Use this tool for setting properties of type reference or references.
            The references parameter accepts a single or an array of nodeAggregateIds per reference name.
            NodeAggregateIds must be sent without any prefix.',
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
            'references' => [
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
            'references',
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
