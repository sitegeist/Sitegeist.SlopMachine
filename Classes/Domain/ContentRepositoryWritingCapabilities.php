<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Security\Context as SecurityContext;

#[Flow\Proxy(false)]
class ContentRepositoryWritingCapabilities
{
    public function __construct(
        protected MCPContentContextFactory $contentContextFactory,
        protected NodeTypeManager $nodeTypeManager,
        protected SecurityContext $securityContext,
        protected PropertyMapper $propertyMapper,
    ) {
    }

    /**
     * @param array<string,mixed> $command
     * @return array<string,mixed>
     */
    #[McpTool(
        name: 'CreateNodeAggregateWithNode',
        description: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['description'],
        outputSchema: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_RESULT_SCHEMA,
    )]
    public function createNodeAggregateWithNode(
        #[Schema(
            type: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['type'],
            description: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['description'],
            properties: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['properties'],
            required: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['required'],
            additionalProperties: SchemaLibrary::CREATE_NODEAGGREGATE_WITH_NODE_SCHEMA['additionalProperties'],
        )]
        array $command,
    ): array {
        return $this->handleCreateNodeAggregateWithNode(
            nodeTypeName: $command['nodeTypeName'],
            originDimensionSpacePoint: $command['originDimensionSpacePoint'],
            parentNodeAggregateId: $command['parentNodeAggregateId'],
            initialPropertyValues: $command['initialPropertyValues'] ?? [],
            nodeAggregateId: $command['nodeAggregateId'] ?? null,
            succeedingSiblingNodeAggregateId: $command['succeedingSiblingNodeAggregateId'] ?? null,
            nodeName: $command['nodeName'] ?? null,
            references: $command['references'] ?? [],
        );
    }

    /**
     * @param array<string,mixed> $command
     * @return array<string,mixed>
     */
    #[McpTool(
        name: 'SetNodeProperties',
        description: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['description'],
        outputSchema: SchemaLibrary::SET_NODE_PROPERTIES_RESULT_SCHEMA,
    )]
    public function setNodeProperties(
        #[Schema(
            type: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['type'],
            description: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['description'],
            properties: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['properties'],
            required: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['required'],
            additionalProperties: SchemaLibrary::SET_NODE_PROPERTIES_SCHEMA['additionalProperties'],
        )]
        array $command,
    ): array {
        return $this->handleSetNodeProperties($command['nodeAggregateId'], $command['originDimensionSpacePoint'], $command['propertyValues']);
    }

    /**
     * @param array<string,mixed> $command
     * @return array<string,mixed>
     */
    #[McpTool(
        name: 'SetNodeReferences',
        description: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['description'],
        outputSchema: SchemaLibrary::SET_NODE_REFERENCES_RESULT_SCHEMA,
    )]
    public function setNodeReferences(
        #[Schema(
            type: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['type'],
            description: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['description'],
            properties: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['properties'],
            required: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['required'],
            additionalProperties: SchemaLibrary::SET_NODE_REFERENCES_SCHEMA['additionalProperties'],
        )]
        array $command,
    ): array {
        return $this->handleSetNodeReferences(
            nodeAggregateId: $command['nodeAggregateId'],
            originDimensionSpacePoint: $command['originDimensionSpacePoint'],
            references: $command['references']
        );
    }

    /**
     * @param array<int,array<string,mixed>> $commands
     */
    #[McpTool(
        name: 'BulkWriting',
        description: 'Allows for issuing multiple write commands at once.
            Use this if you want to e.g. create a completely new page and add its content in one batch.
            Commands must be sent as objects, not as JSON strings.
            Each command must define its `type` (CreateNodeAggregateWithNode, SetNodeProperties, SetNodeReferences) and has the same properties as the corresponding tools.
            An example command would be {"type": "SetNodeProperties", "nodeAggregateId": "8a887309-5803-458d-b30a-40736f5a5d82", "originDimensionSpacePoint": {"language":"de"}, "propertyValues": {"title": "My Title"}}
            The commands are unserialized along with the `commands` parameter and must not be double encoded.
            Returns an object with a `results` property holding one result per issued command, in the order the commands were given. Each result repeats the command\'s `type` and carries a boolean `success`.
            This tool does not work transactionally, commands that succeeded have been applied and can be considered as much for the current content graph state.
            If more than one write operation is required, always prefer this tool.
            Single-operation tools (CreateNodeAggregateWithNode, SetNodeProperties, SetNodeReferences) are only allowed if:
                1. a required ID/dependency can only be determined at runtime, or
                2. BulkWriting technically fails for that specific step.
            - Error handling:
                - With BulkWriting, failed individual commands must be corrected and retried via BulkWriting.
            - Decision rule:
                - Default = BulkWriting
                - Single-operation tools = exception case',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: true,
            idempotentHint: false,
            openWorldHint: false,
        ),
        outputSchema: SchemaLibrary::BULK_WRITING_RESULT_SCHEMA,
    )]
    public function bulkWriting(
        #[Schema(
            type: 'array',
            description: 'List of write commands. Each item must be one valid command object, not a JSON string.',
            items: SchemaLibrary::COMMANDS_SCHEMA,
            minItems: 1,
        )]
        array $commands,
    ): array {
        $results = [];
        foreach ($commands as $command) {
            $type = $command['type'] ?? null;
            $commandResult = match ($type) {
                'CreateNodeAggregateWithNode' => $this->handleCreateNodeAggregateWithNode(
                    nodeTypeName: $command['nodeTypeName'],
                    originDimensionSpacePoint: $command['originDimensionSpacePoint'],
                    parentNodeAggregateId: $command['parentNodeAggregateId'],
                    initialPropertyValues: $command['initialPropertyValues'] ?? [],
                    nodeAggregateId: $command['nodeAggregateId'] ?? null,
                    succeedingSiblingNodeAggregateId: $command['succeedingSiblingNodeAggregateId'] ?? null,
                    nodeName: $command['nodeName'] ?? null,
                    references: $command['references'] ?? [],
                ),
                'SetNodeProperties' => $this->handleSetNodeProperties(
                    nodeAggregateId: $command['nodeAggregateId'],
                    originDimensionSpacePoint: $command['originDimensionSpacePoint'],
                    propertyValues: $command['propertyValues'],
                ),
                'SetNodeReferences' => $this->handleSetNodeReferences(
                    nodeAggregateId: $command['nodeAggregateId'],
                    originDimensionSpacePoint: $command['originDimensionSpacePoint'],
                    references: $command['references'],
                ),
                null => [
                    'success' => false,
                    'message' => 'The command type must be given',
                ],
                default => [
                    'success' => false,
                    'message' => 'Invalid command type ' . $command['type'],
                ]
            };

            // Echoing the type back keeps mixed batches interpretable: without it, a result can
            // only be matched to its command by position.
            $results[] = $type === null ? $commandResult : ['type' => $type] + $commandResult;
        }

        return ['results' => $results];
    }

    /**
     * @param array<string,string> $originDimensionSpacePoint
     * @param array<string,mixed> $initialPropertyValues
     * @param array<string,mixed> $references
     * @return array<string,mixed>
     */
    private function handleCreateNodeAggregateWithNode(
        string $nodeTypeName,
        array $originDimensionSpacePoint,
        string $parentNodeAggregateId,
        array $initialPropertyValues,
        ?string $nodeAggregateId = null,
        ?string $succeedingSiblingNodeAggregateId = null,
        ?string $nodeName = null,
        array $references = [],
    ): array {
        $contentContext = $this->contentContextFactory->getWritingContentContext($originDimensionSpacePoint);
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $contentContext,
                $parentNodeAggregateId,
                $initialPropertyValues,
                $nodeAggregateId,
                $succeedingSiblingNodeAggregateId,
                $nodeName,
                $references,
                &$result,
            ) {
                try {
                    $parentNode = $contentContext->getNodeByIdentifier($parentNodeAggregateId);
                    /** @var Node $createdNode */
                    $createdNode = $parentNode->createNode(
                        $nodeName ?: NodePaths::generateRandomNodeName(),
                        $this->nodeTypeManager->getNodeType($nodeTypeName),
                        $nodeAggregateId,
                    );
                    $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
                    if ($initialPropertyValues !== []) {
                        $this->setProperties($createdNode, $initialPropertyValues);
                    }
                    if ($references !== []) {
                        $this->setReferences($createdNode, $references);
                    }
                    if ($succeedingSibling = $succeedingSiblingNodeAggregateId ? $contentContext->getNodeByIdentifier($succeedingSiblingNodeAggregateId) : null) {
                        $createdNode->moveBefore($succeedingSibling);
                    }
                    $tetheredDescendantIds = [];
                    foreach ($nodeType->getAutoCreatedChildNodes() as $nodeName => $tetheredNodeType) {
                        $tetheredDescendantIds[$nodeName] = $createdNode->getNode($nodeName)->getIdentifier();
                    }

                    $result = [
                        'success' => true,
                        'nodeAggregateId' => $createdNode->getIdentifier(),
                        'nodeName' => $createdNode->getName(),
                        'tetheredDescendantAggregateIds' => $tetheredDescendantIds,
                    ];
                } catch (\Throwable $exception) {
                    $result = [
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        );

        return $result;
    }

    /**
     * @param array<string,string> $originDimensionSpacePoint
     * @param array<string,mixed> $propertyValues
     * @return array<string,mixed>
     */
    private function handleSetNodeProperties(
        string $nodeAggregateId,
        array $originDimensionSpacePoint,
        array $propertyValues,
    ): array {
        $contentContext = $this->contentContextFactory->getWritingContentContext($originDimensionSpacePoint);
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeAggregateId,
                $contentContext,
                $propertyValues,
                &$result,
            ) {
                try {
                    /** @var Node $node */
                    $node = $contentContext->getNodeByIdentifier($nodeAggregateId);
                    $this->setProperties($node, $propertyValues);

                    $result = [
                        'success' => true,
                        'message' => null,
                    ];
                } catch (\Throwable $exception) {
                    $result = [
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        );

        return $result;
    }

    /**
     * @param array<string,string> $originDimensionSpacePoint
     * @param array<string,mixed> $references
     * @return array<string,mixed>
     */
    private function handleSetNodeReferences(
        string $nodeAggregateId,
        array $originDimensionSpacePoint,
        array $references,
    ): array {
        $contentContext = $this->contentContextFactory->getWritingContentContext($originDimensionSpacePoint);
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeAggregateId,
                $contentContext,
                $references,
                &$result,
            ) {
                try {
                    /** @var Node $node */
                    $node = $contentContext->getNodeByIdentifier($nodeAggregateId);
                    $this->setReferences($node, $references);

                    $result = [
                        'success' => true,
                        'message' => null,
                    ];
                } catch (\Throwable $exception) {
                    $result = [
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        );

        return $result;
    }

    /**
     * @param array<string,mixed> $properties
     */
    private function setProperties(Node $node, array $properties): void
    {
        $nodeType = $node->getNodeType();
        foreach ($properties as $propertyName => $propertyValue) {
            $expectedType = $nodeType->getPropertyType($propertyName);
            if ($expectedType === 'reference' || $expectedType === 'references') {
                throw new \Exception('References must be set as references, not properties.');
            }
            if (class_exists($expectedType) && !$propertyValue instanceof $expectedType) {
                $propertyValue = $this->propertyMapper->convert($propertyValue, $expectedType);
            }
            $node->setProperty($propertyName, $propertyValue);
        }
    }

    /**
     * @param array<string,mixed> $references
     */
    private function setReferences(Node $node, array $references): void
    {
        $nodeType = $node->getNodeType();
        foreach ($references as $propertyName => $propertyValue) {
            $expectedType = $nodeType->getPropertyType($propertyName);
            if ($expectedType === 'reference' && is_array($propertyValue)) {
                $propertyValue = reset($propertyValue);
            }
            if ($expectedType === 'references' && is_string($propertyValue)) {
                $propertyValue = [$propertyValue];
            }
            $node->setProperty($propertyName, $propertyValue);
        }
    }
}
