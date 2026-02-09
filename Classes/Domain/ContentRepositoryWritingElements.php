<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpTool;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Service\ContentContextFactory;

#[Flow\Scope('singleton')]
class ContentRepositoryWritingElements
{
    public function __construct(
        protected ContentContextFactory $contentContextFactory,
        protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource,
        protected NodeTypeManager $nodeTypeManager,
        protected Context $securityContext,
        protected PropertyMapper $propertyMapper,
    ) {
    }

    /**
     * @param \stdClass<string,string> $originDimensionSpacePoint
     * @param \stdClass<string,mixed> $initialPropertyValues
     * @return array<string,mixed>
     */
    #[McpTool(
        name: 'CreateNodeAggregateWithNode',
        description: 'Create a new node with the given parameters. Node names are strictly optional and should not be used for regular editorial nodes. The succeedingSiblingNodeAggregateId is optional, only use it if a position relative to the siblings is explicitly requested. Remember that tethered children don\'t need to be created explicitly as they are created automatically created together with their parent. Returns the nodeAggregateId of the created node as well as the nodeAggregateIds of the tethered descendants that were created as well.'
    )]
    public function createNodeAggregateWithNode(
        string $nodeTypeName,
        $originDimensionSpacePoint,
        string $parentNodeAggregateId,
        $initialPropertyValues,
        ?string $succeedingSiblingNodeAggregateId = null,
        ?string $nodeName = null,
    ): array {
        $originDimensionSpacePoint = \json_decode(json_encode($originDimensionSpacePoint), true);
        $initialPropertyValues = \json_decode(json_encode($initialPropertyValues), true);
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $originDimensionSpacePoint,
                $parentNodeAggregateId,
                $initialPropertyValues,
                $succeedingSiblingNodeAggregateId,
                $nodeName,
                &$result,
            ) {
                $dimensions = [];
                foreach ($originDimensionSpacePoint as $dimensionName => $dimensionValue) {
                    $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                }
                $contentContext = $this->contentContextFactory->create([
                    'workspaceName' => 'user-admin',
                    'dimensions' => $dimensions,
                    'targetDimensions' => $originDimensionSpacePoint,
                    'invisibleContentShown' => true,
                ]);

                $parentNode = $contentContext->getNodeByIdentifier($parentNodeAggregateId);
                $createdNode = $parentNode->createNode(
                    $nodeName ?: NodePaths::generateRandomNodeName(),
                    $this->nodeTypeManager->getNodeType($nodeTypeName),
                );
                $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
                foreach ($initialPropertyValues as $propertyName => $propertyValue) {
                    $expectedType = $nodeType->getPropertyType($propertyName);
                    if (class_exists($expectedType) && !$propertyValue instanceof $expectedType) {
                        $propertyValue = $this->propertyMapper->convert($propertyValue, $expectedType);
                    }
                    $createdNode->setProperty($propertyName, $propertyValue);
                }
                if ($succeedingSibling = $succeedingSiblingNodeAggregateId ? $contentContext->getNodeByIdentifier($succeedingSiblingNodeAggregateId) : null) {
                    $createdNode->moveBefore($succeedingSibling);
                }
                $tetheredDescendantIds = [];
                foreach ($nodeType->getAutoCreatedChildNodes() as $nodeName => $tetheredNodeType) {
                    $tetheredDescendantIds[$nodeName] = $createdNode->getNode($nodeName)->getIdentifier();
                }

                $nodeRecord = [
                    'nodeAggregateId' => $createdNode->getIdentifier(),
                    'tetheredDescendantAggregateIds' => $tetheredDescendantIds,
                ];

                $result = [
                    'content' => [
                        'type' => 'text',
                        'text' => \json_encode($nodeRecord),
                    ],
                    'structuredContent' => $nodeRecord,
                ];
            }
        );

        return $result;
    }

    /**
     * @param \stdClass<string,string> $originDimensionSpacePoint
     * @param \stdClass<string,mixed> $propertyValues
     */
    #[McpTool(
        name: 'SetNodeProperties',
        description: 'Sets properties on an existing node.'
    )]
    public function setNodeProperties(
        string $nodeAggregateId,
        $originDimensionSpacePoint,
        $propertyValues,
    ): void {
        $originDimensionSpacePoint = \json_decode(json_encode($originDimensionSpacePoint), true);
        $propertyValues = \json_decode(json_encode($propertyValues), true);
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeAggregateId,
                $originDimensionSpacePoint,
                $propertyValues,
            ) {
                $dimensions = [];
                foreach ($originDimensionSpacePoint as $dimensionName => $dimensionValue) {
                    $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                }
                $contentContext = $this->contentContextFactory->create([
                    'workspaceName' => 'user-admin',
                    'dimensions' => $dimensions,
                    'targetDimensions' => $originDimensionSpacePoint,
                    'invisibleContentShown' => true,
                ]);

                $node = $contentContext->getNodeByIdentifier($nodeAggregateId);
                foreach ($propertyValues as $propertyName => $propertyValue) {
                    $nodeType = $node->getNodeType();
                    $expectedType = $nodeType->getPropertyType($propertyName);
                    if (class_exists($expectedType) && !$propertyValue instanceof $expectedType) {
                        $propertyValue = $this->propertyMapper->convert($propertyValue, $expectedType);
                    }
                    $node->setProperty($propertyName, $propertyValue);
                }
            }
        );
    }
}
