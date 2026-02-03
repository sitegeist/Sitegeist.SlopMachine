<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Neos\Flow\Annotations as Flow;
use Mcp\Capability\Attribute\McpResource;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

#[Flow\Scope('singleton')]
class NodeTypeSchemaResource
{
    public function __construct(
        protected NodeTypeManager $nodeTypeManager,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(uri: 'nodetypes://schema', name: 'nodetype-schema')]
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
                    ],
                    array_filter(
                        $nodeType->getProperties(),
                        fn (string $propertyName): bool => !\str_starts_with($propertyName, '_'),
                        ARRAY_FILTER_USE_KEY,
                    ),
                ),
            ];
        }

        return [
            'uri' => 'nodetypes://schema',
            'name' => 'Node type schema',
            'description' => 'A list of available node types that can be handled by MCP clients',
            'mimeType' => 'application/json',
            'text' => \json_encode($schema),
        ];
    }
}
