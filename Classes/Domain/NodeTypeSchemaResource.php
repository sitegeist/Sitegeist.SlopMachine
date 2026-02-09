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
    public const URI = 'nodetypes://schema';

    public function __construct(
        protected NodeTypeManager $nodeTypeManager,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::URI,
        name: 'nodetype-schema',
        description: 'A list of available node types that can be handled by MCP clients',
    )]
    public function get(): array
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
            'uri' => self::URI,
            'name' => 'Node Type Schema',
            'description' => 'The list of available node types. The properties field defines all properties that can be set on a node of that type. The constraints field defines structural restrictions the node type imposes, e.g. what type children of a node of this type must be of. The tetheredChildren field defines which child nodes are automatically created under a new node of this type.',
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
