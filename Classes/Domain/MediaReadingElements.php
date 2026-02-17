<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Domain;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\AssetCollection;
use Neos\Media\Domain\Model\Audio;
use Neos\Media\Domain\Model\Document;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\Tag;
use Neos\Media\Domain\Model\Video;
use Neos\Media\Domain\Repository\AssetCollectionRepository;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\AudioRepository;
use Neos\Media\Domain\Repository\DocumentRepository;
use Neos\Media\Domain\Repository\ImageRepository;
use Neos\Media\Domain\Repository\TagRepository;
use Neos\Media\Domain\Repository\VideoRepository;

#[Flow\Scope('singleton')]
class MediaReadingElements
{
    public const FIND_ASSET_COLLECTIONS_URI = 'media://find-assetcollections';
    public const FIND_TAGS_URI = 'media://find-tags';
    public const FIND_TYPES_URI = 'media://find-types';
    public const FIND_ASSETS_URI = 'media://find-assets/{assetCollection}/{tag}/{type}';

    public function __construct(
        protected AudioRepository $audioRepository,
        protected DocumentRepository $documentRepository,
        protected ImageRepository $imageRepository,
        protected VideoRepository $videoRepository,
        protected AssetCollectionRepository $assetCollectionRepository,
        protected TagRepository $tagRepository,
        protected PersistenceManagerInterface $persistenceManager,
        protected ResourceManager $resourceManager,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::FIND_ASSET_COLLECTIONS_URI,
        name: 'find-asset-collections',
        description: 'A list of all available asset collections. Asset collections contain media assets like images, documents etc.',
    )]
    public function findAssetCollections(): array
    {
        $payload = [];
        foreach ($this->assetCollectionRepository->findAll() as $assetCollection) {
            /** @var AssetCollection $assetCollection */
            $payload[] = [
                'id' => $this->persistenceManager->getIdentifierByObject($assetCollection),
                'title' => $assetCollection->getTitle(),
            ];
        }

        return [
            'uri' => self::FIND_ASSET_COLLECTIONS_URI,
            'name' => 'Asset collections',
            'description' => 'A list of all available asset collections. The id identifies the asset collection and is to be sent as parameter for subsequent calls.',
            'mimeType' => 'application/json',
            'text' => \json_encode($payload),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::FIND_TAGS_URI,
        name: 'find-tags',
        description: 'A list of all available asset collections. Asset collections contain media assets like images, documents etc.',
    )]
    public function findTags(): array
    {
        $payload = [];
        foreach ($this->tagRepository->findAll() as $tag) {
            /** @var Tag $tag */
            $payload[] = [
                'id' => $this->persistenceManager->getIdentifierByObject($tag),
                'label' => $tag->getLabel(),
            ];
        }

        return [
            'uri' => self::FIND_TAGS_URI,
            'name' => 'Tags',
            'description' => 'A list of all available tags. The id identifies the tag and is to be sent as parameter for subsequent calls.',
            'mimeType' => 'application/json',
            'text' => \json_encode($payload),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResource(
        uri: self::FIND_TYPES_URI,
        name: 'find-types',
        description: 'A list of all available media types.',
    )]
    public function findTypes(): array
    {
        $payload = [
            'Audio',
            'Document',
            'Image',
            'Video',
        ];

        return [
            'uri' => self::FIND_TAGS_URI,
            'name' => 'Tags',
            'description' => 'A list of all available tags. The id identifies the tag and is to be sent as parameter for subsequent calls.',
            'mimeType' => 'application/json',
            'text' => \json_encode($payload),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    #[McpResourceTemplate(
        uriTemplate: self::FIND_ASSETS_URI,
        name: 'find-assets',
        description: 'A list of all available assets. Can be filtered by asset collection, tag or type. The asset collection and tag parameters are optional and can be set to * if to be ignored. Each parameter has its own resource to determine the available values.',
    )]
    public function findAssets(
        string $type,
        ?string $assetCollection = null,
        ?string $tag = null,
    ): array {
        $type = \urldecode($type);
        $query = match ($type) {
            'Audio' => $this->audioRepository->createQuery(),
            'Document' => $this->documentRepository->createQuery(),
            'Image' => $this->imageRepository->createQuery(),
            'Video' => $this->videoRepository->createQuery(),
            default => throw new \Exception('Invalid type ' . $type . ', must be one of Audio, Document, Image or Video.'),
        };
        $filters = [];
        $assetCollection = $assetCollection ? \urldecode($assetCollection) : $assetCollection;
        if ($assetCollection && $assetCollection !== '*') {
            $filters[] = $query->contains('assetCollections', $assetCollection);
        }
        $tag = $tag ? \urldecode($tag) : $tag;
        if ($tag && $tag !== '*') {
            $filters[] = $query->contains('tags', $tag);
        }

        $payload = [];
        $assets = $filters === []
            ? $query->execute()
            : $query->matching($query->logicalAnd(...$filters))->execute();
        foreach ($assets as $asset) {
            /** @var Asset $asset */
            $payload[] = [
                'id' => $asset->getIdentifier(),
                'uri' => $this->resourceManager->getPublicPersistentResourceUri($asset->getResource()),
                'title' => $asset->getTitle(),
                'caption' => $asset->getCaption(),
                'copyrightNotice' => $asset->getCopyrightNotice(),
                'mediaType' => $asset->getMediaType(),
            ];
        }

        return [
            'uri' => self::FIND_ASSETS_URI,
            'name' => 'Assets',
            'description' => 'A list of assets. The id identifies the asset if it is to be referenced. The uri can be used to evaluate the file contents.',
            'mimeType' => 'application/json',
            'text' => \json_encode($payload),
        ];
    }
}
