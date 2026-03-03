<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Annotations as Flow;
use Mcp\Capability\Attribute\McpResource;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

#[Flow\Scope('singleton')]
class NodeTypeSchemaResource
{
    public const FULL_URI = 'nodetypes://full-schema';
    public const BASIC_URI = 'nodetypes://basic-schema';

    public function __construct(
        protected NodeTypeManager $nodeTypeManager,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::FULL_URI,
        name: 'nodetype-full-schema',
        description: 'A list of available node types that can be handled by MCP clients. Results in a rather large set of data. To fetch only basic information, use `nodetype-basic-schema` instead.
            This contains all necessary structural data like
              - which node type has what properties of what type
              - which constraints regarding properties or allowed child nodes exist
              - which tethered nodes exist per node type.
            Constraints are enforced on the write side and use the node type schema for this, so this is the single source of truth for all schema validation information.',
        meta: [
            'purpose' => 'structure information'
        ],
    )]
    public function getFull(): array
    {
        $schema = [];
        foreach ($this->nodeTypeManager->getSubNodeTypes('Sitegeist.SlopMachine:Mixin.MCPExposed', false) as $nodeType) {
            $schema[$nodeType->getName()] = [
                'name' => $nodeType->getConfiguration('options.mcp.name'),
                'description' => $nodeType->getConfiguration('options.mcp.description'),
                'properties' => array_map(
                    fn (array $propertyConfiguration): array => [
                        'type' => $propertyConfiguration['type'] ?? 'string',
                        /** @todo handle i18n */
                        'label' => $propertyConfiguration['ui']['label'] ?? '',
                        'defaultValue' => $propertyConfiguration['defaultValue'] ?? '',
                        'required' => ($propertyConfiguration['validation']['Neos.Neos/Validation/NotEmptyValidator'] ?? null) !== null,
                    ],
                    array_filter(
                        $nodeType->getProperties(),
                        fn (string $propertyName): bool => !\str_starts_with($propertyName, '_'),
                        ARRAY_FILTER_USE_KEY,
                    ),
                ),
                'supertypes' => array_values(array_unique($this->resolveSuperTypeNames($nodeType))),
                'constraints' => $nodeType->getConfiguration('constraints'),
                'tetheredChildren' => $nodeType->getConfiguration('childNodes'),
            ];
        }

        return [
            'uri' => self::FULL_URI,
            'name' => 'Node Type Schema',
            'description' => 'The list of available node types.
                The properties field defines all properties that can be set on a node of that type.
                An exception are properties of type reference or references. Those can reference other nodes instead. Single reference properties can be set to the target node\'s aggregate id. Properties of type references can be set to a list of node aggregate ids. Use the SetNodeReferences tool for writing references.
                The constraints field defines structural restrictions the node type imposes, e.g. what type children of a node of this type must be of.
                The tetheredChildren field defines which child nodes are automatically created under a new node of this type.',
            'mimeType' => 'application/json',
            'text' => \json_encode($schema),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::BASIC_URI,
        name: 'nodetype-basic-schema',
        description: 'A list of available node types reduced to basic information.  Results in a rather small set of incomplete data. To fetch full information, use `nodetype-full-schema` instead.
            This contains basic structural data like
              - which node type has what properties of what type
              - which tethered nodes exist per node type.
            Constraints and additional property information are excluded from this limited overview.',
        meta: [
            'purpose' => 'basic node type information'
        ],
    )]
    public function getBasic(): array
    {
        $schema = [];
        foreach ($this->nodeTypeManager->getSubNodeTypes('Sitegeist.SlopMachine:Mixin.MCPExposed', false) as $nodeType) {
            $schema[$nodeType->getName()] = [
                'name' => $nodeType->getConfiguration('options.mcp.name'),
                'description' => $nodeType->getConfiguration('options.mcp.description'),
                'properties' => array_map(
                    fn (array $propertyConfiguration): array => [
                        'type' => $propertyConfiguration['type'] ?? 'string',
                        'required' => ($propertyConfiguration['validation']['Neos.Neos/Validation/NotEmptyValidator'] ?? null) !== null,
                    ],
                    array_filter(
                        $nodeType->getProperties(),
                        fn (string $propertyName): bool => !\str_starts_with($propertyName, '_'),
                        ARRAY_FILTER_USE_KEY,
                    ),
                ),
                'tetheredChildren' => $nodeType->getConfiguration('childNodes'),
            ];
        }

        return [
            'uri' => self::FULL_URI,
            'name' => 'Node Type Schema',
            'description' => 'The list of available node types with reduced information.
                The properties field defines all properties that can be set on a node of that type.
                An exception are properties of type reference or references. Those can reference other nodes instead. Single reference properties can be set to the target node\'s aggregate id. Properties of type references can be set to a list of node aggregate ids. Use the SetNodeReferences tool for writing references.
                The tetheredChildren field defines which child nodes are automatically created under a new node of this type.',
            'mimeType' => 'application/json',
            'text' => \json_encode($schema),
        ];
    }

    /**
     * @return array<string>
     */
    private function resolveSuperTypeNames(NodeType $nodeType): array
    {
        $superTypeNames = [];
        foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
            $superTypeNames[] = $superType->getName();
            $superTypeNames = array_merge($superTypeNames, $this->resolveSuperTypeNames($superType));
        }

        return $superTypeNames;
    }
}
