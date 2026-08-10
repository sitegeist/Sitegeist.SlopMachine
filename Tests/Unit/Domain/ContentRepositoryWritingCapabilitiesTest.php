<?php

declare(strict_types=1);

namespace Sitegeist\SlopMachine\Tests\Unit\Domain;

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Tests\UnitTestCase;
use Sitegeist\SlopMachine\Domain\ContentRepositoryWritingCapabilities;
use Sitegeist\SlopMachine\Domain\MCPContentContextFactory;

/**
 * The MCP protocol requires `structuredContent` to be a JSON object, and the SDK passes whatever a
 * tool method returns straight into that field (see CallToolHandler and
 * ToolReference::extractStructuredContent). A tool returning a PHP list therefore produces a JSON
 * array and is rejected by the client - after the write has already been applied.
 *
 * These tests pin the result *shape* only. They deliberately use commands that never reach the
 * content repository, so no subgraph is needed.
 */
class ContentRepositoryWritingCapabilitiesTest extends UnitTestCase
{
    private ContentRepositoryWritingCapabilities $capabilities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capabilities = new ContentRepositoryWritingCapabilities(
            $this->createMock(MCPContentContextFactory::class),
            $this->createMock(NodeTypeManager::class),
            $this->createMock(SecurityContext::class),
            $this->createMock(PropertyMapper::class),
        );
    }

    /**
     * @test
     */
    public function bulkWritingReturnsAnObjectRatherThanAList(): void
    {
        $result = $this->capabilities->bulkWriting([
            ['type' => 'NoSuchCommand'],
        ]);

        self::assertArrayHasKey('results', $result);
        self::assertFalse(array_is_list($result), 'The tool result must serialize to a JSON object, not an array.');
    }

    /**
     * @test
     */
    public function bulkWritingReturnsOneResultPerCommandInOrder(): void
    {
        $result = $this->capabilities->bulkWriting([
            ['type' => 'NoSuchCommand'],
            ['nodeAggregateId' => 'some-id'],
            ['type' => 'AnotherMissingCommand'],
        ]);

        self::assertTrue(array_is_list($result['results']), 'The results themselves must stay a list.');
        self::assertCount(3, $result['results']);

        self::assertSame('Invalid command type NoSuchCommand', $result['results'][0]['message']);
        self::assertSame('The command type must be given', $result['results'][1]['message']);
        self::assertSame('Invalid command type AnotherMissingCommand', $result['results'][2]['message']);
    }

    /**
     * @test
     */
    public function bulkWritingEchoesTheCommandTypeBackForEachResult(): void
    {
        $result = $this->capabilities->bulkWriting([
            ['type' => 'NoSuchCommand'],
            ['nodeAggregateId' => 'some-id'],
        ]);

        self::assertSame('NoSuchCommand', $result['results'][0]['type']);
        self::assertArrayNotHasKey('type', $result['results'][1], 'A command without a type cannot echo one back.');
    }

    /**
     * The SDK builds the CallToolResult envelope itself; building one by hand nests it inside its
     * own `structuredContent`.
     *
     * @test
     */
    public function bulkWritingResultsCarryNoHandBuiltEnvelope(): void
    {
        $result = $this->capabilities->bulkWriting([
            ['type' => 'NoSuchCommand'],
        ]);

        self::assertArrayNotHasKey('content', $result);
        self::assertArrayNotHasKey('structuredContent', $result);
        self::assertArrayNotHasKey('content', $result['results'][0]);
        self::assertArrayNotHasKey('structuredContent', $result['results'][0]);
    }
}
