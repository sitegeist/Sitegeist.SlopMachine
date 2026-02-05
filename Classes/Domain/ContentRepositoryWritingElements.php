<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpTool;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Flow\Annotations as Flow;
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
    ) {
    }

    /**
     * @param \stdClass<string,string> $originDimensionSpacePoint
     * @param \stdClass<string,mixed> $initialPropertyValues
     */
    #[McpTool(name: 'CreateNodeAggregateWithNode')]
    public function createNode(
        string $nodeTypeName,
        \stdClass $originDimensionSpacePoint,
        string $parentNodeAggregateId,
        \stdClass $initialPropertyValues,
        ?string $succeedingSiblingNodeAggregateId = null,
        ?string $nodeName = null,
    ): void {
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeName,
                $originDimensionSpacePoint,
                $parentNodeAggregateId,
                $initialPropertyValues,
                $succeedingSiblingNodeAggregateId,
                $nodeName,
            ) {
                $originDimensionSpacePoint = (array)$originDimensionSpacePoint;
                $initialPropertyValues = (array)$initialPropertyValues;
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
                foreach ($initialPropertyValues as $propertyName => $propertyValue) {
                    $createdNode->setProperty($propertyName, $propertyValue);
                }
                if ($succeedingSibling = $succeedingSiblingNodeAggregateId ? $contentContext->getNodeByIdentifier($succeedingSiblingNodeAggregateId) : null) {
                    $createdNode->moveBefore($succeedingSibling);
                }
            }
        );
    }
}
