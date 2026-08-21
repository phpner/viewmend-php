<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ViewMend\PluginCron\PluginCronClient;
use ViewMend\SiteTracker\PendingEvent;
use ViewMend\ViewMend;

final class PublicApiTest extends TestCase
{
    public function testSimpleHappyPathBuildsWithoutInfrastructureImportsOrSideEffects(): void
    {
        $viewmend = ViewMend::client(token: 'vmt_' . bin2hex(random_bytes(16)));

        $event = $viewmend
            ->siteTracker('integration-id')
            ->events()
            ->deployment(
                id: 'deploy-abc123',
                title: 'Homepage deployed',
            )
            ->page('https://example.com')
            ->contentChanged()
            ->metadataChanged();

        self::assertInstanceOf(PendingEvent::class, $event);
        self::assertSame('https://viewmend.com/api/v1', ViewMend::PRODUCTION_API_BASE_URL);
    }

    public function testPluginCronModuleCanBeSelectedWithoutNetworkSideEffects(): void
    {
        $viewmend = ViewMend::client(
            token: 'vmcron1_pcn_' . str_repeat('a', 26) . '_' . str_repeat('A', 64) . '_' . str_repeat('S', 64),
        );

        self::assertInstanceOf(PluginCronClient::class, $viewmend->pluginCron());
    }
}
