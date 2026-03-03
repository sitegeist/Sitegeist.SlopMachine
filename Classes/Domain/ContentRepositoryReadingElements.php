<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpResourceTemplate;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Neos\Domain\Service\NodeSearchServiceInterface;
use Neos\Utility\Arrays;

#[Flow\Scope('singleton')]
class ContentRepositoryReadingElements
{
    public const FIND_CHILDREN_URI = 'contentsubgraph://find-children/{dimensionSpacePoint}/{parentNodeAggregateId}/{nodeTypeNames}/{limitToPropertyNames}';
    public const FIND_DESCENDANTS_URI = 'contentsubgraph://find-descendants/{dimensionSpacePoint}/{ancestorNodeAggregateId}/{nodeTypeNames}/{searchTerm}/{limitToPropertyNames}';
    public const FIND_SUBTREE_URI = 'contentsubgraph://find-subtree/{dimensionSpacePoint}/{entryNodeAggregateId}/{nodeTypeNames}/{maximumLevels}/{limitToPropertyNames}';

    public function __construct(
        protected ContentContextFactory $contentContextFactory,
        protected ContentDimensionPresetSourceInterface $contentDimensionPresetSource,
        protected Context $securityContext,
        protected NodeSearchServiceInterface $nodeSearchService,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_CHILDREN_URI,
        name: 'find-children',
        description: 'A list of all available child nodes of a given parent that are of a given type.
            This is a rather efficient query; use this if you already know the parent under which you want to search.
            To reduce response size, you can limit the returned properties to the list of given names in the parameter limitToPropertyNames.
            To skip a parameter, provide an asterisk (*) as value.
            The returned data describes the current state of the content graph and does not give any reliable information on structural constraints.',
        meta: [
            'purpose' => 'content search'
        ],
    )]
    public function findChildren(
        string $dimensionSpacePoint,
        string $parentNodeAggregateId,
        string $nodeTypeNames,
        string $limitToPropertyNames,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeNames,
                $dimensionSpacePoint,
                $parentNodeAggregateId,
                $limitToPropertyNames,
                &$result,
            ) {
                try {
                    $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                    $nodeTypeNames = $this->resolveSingleValue($nodeTypeNames);
                    $limitToPropertyNames = $this->resolveListValue($limitToPropertyNames);
                    $dimensions = [];
                    foreach ($dimensionSpacePoint as $dimensionName => $dimensionValue) {
                        $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                    }
                    $contentContext = $this->contentContextFactory->create([
                        'workspaceName' => 'user-admin',
                        'dimensions' => $dimensions,
                        'targetDimensions' => $dimensionSpacePoint,
                        'invisibleContentShown' => true,
                    ]);

                    $ancestorNode = $contentContext->getNodeByIdentifier($parentNodeAggregateId);
                    $flowQuery = new FlowQuery([$ancestorNode]);
                    $nodes = [];
                    foreach ($flowQuery->children('[instanceof ' . $nodeTypeNames . ']') as $node) {
                        /** @var Node $node */
                        $nodes[] = $this->serializeNode($node, $limitToPropertyNames);
                    }

                    $result = [
                        'uri' => self::FIND_CHILDREN_URI,
                        'name' => 'Child nodes',
                        'description' => 'A list of nodes. The aggregateId identifies a node in the subgraph. The properties field contains the current state of the properties of that node. The nodeTypeName field contains the name of the type of that node, for more information see the ' . NodeTypeSchemaResource::FULL_URI . ' resource.',
                        'mimeType' => 'application/json',
                        'text' => \json_encode($nodes),
                    ];
                } catch (\Throwable $t) {
                    $result = [
                        'success' => false,
                        'message' => $t->getMessage(),
                    ];
                    return;
                }
            }
        );

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_DESCENDANTS_URI,
        name: 'find-descendants',
        description: 'A list of all available descendant nodes of a given ancestor that are of a given type and match an optional search term.
            This is a rather expensive query; use this if do not yet know the structure or the parent to search children of.
            To reduce response size, you can limit the returned properties to the list of given names in the parameter limitToPropertyNames.
            To skip a parameter, provide an asterisk (*) as value.
            The returned data describes the current state of the content graph and does not give any reliable information on structural constraints.',
        meta: [
            'purpose' => 'content search'
        ],
    )]
    public function findDescendants(
        string $dimensionSpacePoint,
        string $ancestorNodeAggregateId,
        string $nodeTypeNames,
        string $searchTerm,
        string $limitToPropertyNames,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function() use(
                $nodeTypeNames,
                $dimensionSpacePoint,
                $ancestorNodeAggregateId,
                $limitToPropertyNames,
                $searchTerm,
                &$result,
            ) {
                try {
                    $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                    $nodeTypeNames = $this->resolveSingleValue($nodeTypeNames);
                    $limitToPropertyNames = $this->resolveListValue($limitToPropertyNames);

                    $dimensions = [];
                    foreach ($dimensionSpacePoint as $dimensionName => $dimensionValue) {
                        $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                    }
                    $contentContext = $this->contentContextFactory->create([
                        'workspaceName' => 'user-admin',
                        'dimensions' => $dimensions,
                        'targetDimensions' => $dimensionSpacePoint,
                        'invisibleContentShown' => true,
                    ]);

                    if ($searchTerm = $this->resolveSingleValue($searchTerm)) {
                        /** @var Node[] $nodes */
                        $nodes = $this->nodeSearchService->findByProperties($searchTerm, $nodeTypeNames ? [$nodeTypeNames] : [], $contentContext);
                    } else {
                        $ancestorNode = $contentContext->getNodeByIdentifier($ancestorNodeAggregateId);
                        $flowQuery = new FlowQuery([$ancestorNode]);
                        /** @var Node[] $nodes */
                        $nodes = $nodeTypeNames
                            ? $flowQuery->find('[instanceof ' . $nodeTypeNames . ']')->get()
                            : $flowQuery->find()->get();
                    }
                    $payload = \array_map(
                        fn (Node $node): array => $this->serializeNode($node, $limitToPropertyNames),
                        $nodes,
                    );

                    $result = [
                        'uri' => self::FIND_DESCENDANTS_URI,
                        'name' => 'Descendant nodes',
                        'description' => 'A list of nodes. The aggregateId identifies a node in the subgraph. The properties field contains the current state of the properties of that node. The nodeTypeName field contains the name of the type of that node, for more information see the ' . NodeTypeSchemaResource::FULL_URI . ' resource.',
                        'mimeType' => 'application/json',
                        'text' => \json_encode($payload),
                    ];
                } catch (\Throwable $t) {
                    $result = [
                        'success' => false,
                        'message' => $t->getMessage(),
                    ];
                    return;
                }
            }
        );

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_SUBTREE_URI,
        name: 'find-subtree',
        description: 'A hierarchical subtree for a single page only.
            Includes only nodes matching the given base node type.
            Explicitly excludes nodes under descendant document nodes (subpages).
            PRIMARY QUERY for editorial single-page content inspection when a page nodeAggregateId is known.
            To fetch all content from a document, set the node type names to `Neos.Neos:ContentCollection,Neos.Neos:Content`.
            Use find-descendants only as fallback when the page root is unknown or a cross-page search is explicitly required.
            To reduce response size, you can limit the returned properties to the list of given names in the parameter limitToPropertyNames.
            To skip a parameter, provide an asterisk (*) as value.',
        meta: [
            'purpose' => 'content search'
        ],
    )]
    public function findSubtree(
        string $dimensionSpacePoint,
        string $entryNodeAggregateId,
        string $nodeTypeNames,
        int $maximumLevels,
        string $limitToPropertyNames,
    ): array {
        $result = [];
        $this->securityContext->withoutAuthorizationChecks(
            function () use (
                $dimensionSpacePoint,
                $entryNodeAggregateId,
                $nodeTypeNames,
                $maximumLevels,
                $limitToPropertyNames,
                &$result,
            ) {
                try {
                    $dimensionSpacePoint = \json_decode(\urldecode($dimensionSpacePoint), true, 512, JSON_THROW_ON_ERROR);
                    $nodeTypeNames = $this->resolveSingleValue($nodeTypeNames);
                    $limitToPropertyNames = $this->resolveListValue($limitToPropertyNames);

                    $dimensions = [];
                    foreach ($dimensionSpacePoint as $dimensionName => $dimensionValue) {
                        $dimensions[$dimensionName] = $this->contentDimensionPresetSource->getAllPresets()[$dimensionName]['presets'][$dimensionValue]['values'];
                    }

                    $contentContext = $this->contentContextFactory->create([
                        'workspaceName' => 'user-admin',
                        'dimensions' => $dimensions,
                        'targetDimensions' => $dimensionSpacePoint,
                        'invisibleContentShown' => true,
                    ]);

                    $entryNode = $contentContext->getNodeByIdentifier($entryNodeAggregateId);
                    if (!$entryNode instanceof Node) {
                        throw new \RuntimeException('entry node not found for given nodeAggregateId.');
                    }

                    $subtree = $this->collectMatchingSubtrees(
                        currentNode: $entryNode,
                        nodeTypeNames: $nodeTypeNames,
                        limitToPropertyNames: $limitToPropertyNames,
                        level: 0,
                        maximumLevels: $maximumLevels,
                    );

                    $result = [
                        'uri' => self::FIND_SUBTREE_URI,
                        'name' => 'Page subtree',
                        'description' => 'A hierarchical subtree with level, node and children each.',
                        'mimeType' => 'application/json',
                        'text' => \json_encode($subtree),
                    ];
                } catch (\Throwable $t) {
                    $result = [
                        'success' => false,
                        'message' => $t->getMessage(),
                    ];
                    return;
                }
            }
        );

        return $result;
    }

    private function serializeNode(Node $node, ?array $limitToPropertyNames): array
    {
        return [
            'aggregateId' => $node->getIdentifier(),
            'label' => $node->getLabel(),
            'properties' => array_filter(
                iterator_to_array($node->getProperties()),
                fn (string $propertyName) => !$limitToPropertyNames || in_array(
                        $propertyName,
                        $limitToPropertyNames
                    ),
                ARRAY_FILTER_USE_KEY
            ),
            'nodeTypeName' => $node->getNodeTypeName(),
        ];
    }

    /**
     * @return array{level: int, node: array<string,mixed>, children: array<int,array<string,mixed>>}
     */
    private function collectMatchingSubtrees(
        Node $currentNode,
        string $nodeTypeNames,
        ?array $limitToPropertyNames,
        int $level,
        int $maximumLevels,
    ): array {
        $childSubtrees = [];
        if ($level <= $maximumLevels) {
            foreach ($currentNode->getChildNodes($nodeTypeNames) as $childNode) {
                /** @var Node $childNode */
                $childSubtrees[] = $this->collectMatchingSubtrees(
                    currentNode: $childNode,
                    nodeTypeNames: $nodeTypeNames,
                    limitToPropertyNames: $limitToPropertyNames,
                    level: $level + 1,
                    maximumLevels: $maximumLevels
                );
            }
        }

        return [
            'level' => $level,
            'node' => $this->serializeNode($currentNode, $limitToPropertyNames),
            'children' => $childSubtrees,
        ];
    }

    private function resolveSingleValue(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $value = \urldecode($value);

        if ($value === '*') {
            return null;
        }

        return $value;
    }

    private function resolveListValue(string $limitToPropertyNames): ?array
    {
        $limitToPropertyNames = \urldecode($limitToPropertyNames);
        if ($limitToPropertyNames === '*') {
            return null;
        }

        $jsonDecodedValue = \json_decode($limitToPropertyNames, true);
        if ($jsonDecodedValue !== null) {
            return $jsonDecodedValue;
        }

        return Arrays::trimExplode(',', $limitToPropertyNames);
    }
}
