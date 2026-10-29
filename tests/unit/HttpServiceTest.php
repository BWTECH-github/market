<?php
/**
 * @author Viktar Dubiniuk <dubinuk@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Market\Tests\Unit;

// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OCA\Market\HttpService;
use OCA\Market\VersionHelper;
use OCP\App\AppManagerException;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use Test\TestCase;

/**
 * Class HttpServiceTest
 *
 * @package OCA\Market\Tests\Unit
 */
class HttpServiceTest extends TestCase {
	/** @var IClientService | \PHPUnit\Framework\MockObject\MockObject */
	private $httpClientService;
	/** @var VersionHelper | \PHPUnit\Framework\MockObject\MockObject */
	private $versionHelper;
	/** @var ICacheFactory | \PHPUnit\Framework\MockObject\MockObject */
	private $cacheFactory;
	/** @var IConfig | \PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IL10N | \PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var IAppManager | \PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var HttpService */
	private $httpService;

	/** @var string[] */
	private $createdPaths = [];

	private const STORE = 'https://marketplace.example.test';
	private const KEY = 'bwmp_0123456789abcdef0123456789abcdef0123456789abcdef';
	private const INSTANCE_ID = 'oc1a2b3c4d5e';
	private const CATEGORIES_URL = self::STORE . '/api/v1/categories.json';
	private const WND_URL = self::STORE . '/downloads/wnd/wnd-2.0.2.tar.gz';

	/** @var array<int, array{url: string, options: array}> Anfragen in der Reihenfolge, in der sie rausgingen */
	private array $requests = [];
	/** Was getAppInfo('market') liefert; einzelne Tests ändern es vor dem Aufruf. */
	private ?array $marketAppInfo = ['id' => 'market', 'version' => '0.10.11'];

	protected function tearDown(): void {
		foreach (\array_reverse($this->createdPaths) as $path) {
			if (\is_file($path)) {
				@\unlink($path);
			} elseif (\is_dir($path)) {
				foreach ((array)\glob($path . '/*') as $file) {
					@\unlink($file);
				}
				@\rmdir($path);
				@\rmdir(\dirname($path));
			}
		}
		$this->createdPaths = [];
		parent::tearDown();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->httpClientService = $this->createMock(IClientService::class);
		$this->versionHelper =  $this->createMock(VersionHelper::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('getAppInfo')
			->willReturnCallback(fn (string $appId) => $appId === 'market' ? $this->marketAppInfo : null);
		$this->versionHelper->method('getPlatformVersion')
			->willReturnCallback(static fn (?int $cutTo = null) => $cutTo === 3 ? '11.0.25' : '11.0.25.2');
		$this->httpService = new HttpService(
			$this->httpClientService,
			$this->versionHelper,
			$this->config,
			$this->cacheFactory,
			$this->l10n,
			$this->appManager
		);
	}

	/**
	 * @dataProvider checkInternetConnectionDataProvider
	 */
	public function testCheckInternetConnection($connectionStatus, $expectedExceptionClass) {
		$this->config->method('getSystemValue')
			->willReturnCallback(
				static fn (string $key, $default = null) => match ($key) {
					'appstoreurl' => 'https://marketplace.example.test',
					'has_internet_connection' => $connectionStatus,
					default => $default,
				}
			);
		if ($expectedExceptionClass !== '') {
			$this->expectException($expectedExceptionClass);
		}
		$this->assertNull($this->httpService->checkInternetConnection());
	}

	public function checkInternetConnectionDataProvider() {
		return [
			[true, ''],
			[false, AppManagerException::class]
		];
	}

	public function testGetApps() {
		$expectedApps = [];
		$this->config
			->expects($this->any())
			->method('getSystemValue')
			->willReturnCallback(
				static fn (string $key, $default = null) => match ($key) {
					'appstoreurl' => 'https://marketplace.example.test',
					'has_internet_connection' => true,
					'marketplace.key' => '',
					default => $default,
				}
			);

		$clientMock = $this->getClientResponseMockForGet(\json_encode($expectedApps));
		$this->httpClientService->method('newClient')->willReturn($clientMock);
		$apps = $this->httpService->getApps();
		$this->assertEquals($expectedApps, $apps);
	}

	// -----------------------------------------------------------------------
	// downloadApp: die Adresse steht im Katalog, stammt also von der
	// Gegenstelle. Ein lokaler Pfad darf daraus nur im lokalen Katalogbetrieb
	// werden - und nur auf eine Datei im Katalogverzeichnis. Sonst koennte ein
	// Marktplatz mit 'file:///.../config.php' eine beliebige lokale Datei in
	// den App-Installer schieben, statt ein Paket zu liefern.
	// -----------------------------------------------------------------------

	public function testRemoteCatalogRefusesAFileUrlFromTheCatalog() {
		$this->givenAppstoreUrl('https://marketplace.example.test');

		$this->httpClientService->expects($this->never())->method('newClient');
		$this->expectException(AppManagerException::class);

		$this->httpService->downloadApp('file:///etc/passwd', $this->targetPath());
	}

	public function testRemoteCatalogRefusesABarePathFromTheCatalog() {
		$this->givenAppstoreUrl('https://marketplace.example.test');

		$this->httpClientService->expects($this->never())->method('newClient');
		$this->expectException(AppManagerException::class);

		$this->httpService->downloadApp('/etc/passwd', $this->targetPath());
	}

	public function testRemoteCatalogRefusesASchemeThatMerelyStartsWithHttp() {
		// 'httpfoo://' beginnt mit 'http' - eine Pruefung auf das Praefix
		// allein liesse es durch.
		$this->givenAppstoreUrl('https://marketplace.example.test');

		$this->httpClientService->expects($this->never())->method('newClient');
		$this->expectException(AppManagerException::class);

		$this->httpService->downloadApp('httpfoo://example.test/app.tar.gz', $this->targetPath());
	}

	public function testLocalCatalogCopiesAnArchiveFromTheCatalogDirectory() {
		$catalog = $this->givenLocalCatalog();
		\file_put_contents($catalog . '/app.tar.gz', 'payload');
		$target = $this->targetPath();

		$this->httpService->downloadApp('app.tar.gz', $target);

		$this->assertSame('payload', \file_get_contents($target));
	}

	public function testLocalCatalogAcceptsAFileUrlInsideTheCatalogDirectory() {
		$catalog = $this->givenLocalCatalog();
		\file_put_contents($catalog . '/app.tar.gz', 'payload');
		$target = $this->targetPath();

		$this->httpService->downloadApp('file://' . $catalog . '/app.tar.gz', $target);

		$this->assertSame('payload', \file_get_contents($target));
	}

	public function testLocalCatalogRefusesATraversalOutOfTheCatalogDirectory() {
		$catalog = $this->givenLocalCatalog();
		$outside = \dirname($catalog) . '/secret.txt';
		\file_put_contents($outside, 'secret');

		$this->expectException(AppManagerException::class);
		try {
			$this->httpService->downloadApp('../secret.txt', $this->targetPath());
		} finally {
			@\unlink($outside);
		}
	}

	public function testLocalCatalogRefusesAnAbsolutePathOutsideTheCatalogDirectory() {
		$this->givenLocalCatalog();

		$this->expectException(AppManagerException::class);
		$this->httpService->downloadApp('/etc/passwd', $this->targetPath());
	}

	public function testLocalCatalogRefusesAFileUrlOutsideTheCatalogDirectory() {
		$this->givenLocalCatalog();

		$this->expectException(AppManagerException::class);
		$this->httpService->downloadApp('file:///etc/passwd', $this->targetPath());
	}

	// -----------------------------------------------------------------------
	// Instanzangaben: Mit ihnen ordnet der Marktplatz einen Schlüssel den
	// Instanzen zu, die ihn benutzen. Sie gehen nur an die Herkunft von
	// `appstoreurl` (Schema, Host, Port) und nur zusammen mit dem Schlüssel –
	// nie an einen fremden Host, auch nicht über eine Weiterleitung.
	// -----------------------------------------------------------------------

	public function testMarketplaceRequestWithKeyCarriesTheInstanceHeaders() {
		$this->givenSystemConfig([]);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		$this->assertSame(self::CATEGORIES_URL, $this->requests[0]['url']);
		$this->assertSame([
			'Authorization' => 'apikey: ' . self::KEY,
			'User-Agent' => 'owncloud.online-market/0.10.11',
			'X-Market-Client' => 'market/0.10.11',
			'X-Market-Instance' => self::INSTANCE_ID,
			'X-Market-Platform' => '11.0.25.2',
			'X-Market-Server-Host' => 'cloud.example.test',
		], $this->sortedHeaders(0));
	}

	public function testMarketplaceRequestWithoutKeyIsSentAsBefore() {
		$this->givenSystemConfig(['marketplace.key' => null]);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		// Kein eigenes headers-Feld: Der Kern setzt weiter seinen User-Agent,
		// und Weiterleitungen folgt Guzzle wie bisher.
		$this->assertArrayNotHasKey('headers', $this->requests[0]['options']);
		$this->assertArrayNotHasKey('allow_redirects', $this->requests[0]['options']);
	}

	public function testDownloadFromTheMarketplaceCarriesTheInstanceHeaders() {
		$this->givenSystemConfig([]);
		$this->givenServerResponds();
		$target = $this->targetPath();

		$this->httpService->downloadApp(self::WND_URL, $target);

		$options = $this->requests[0]['options'];
		$this->assertSame(self::INSTANCE_ID, $options['headers']['X-Market-Instance']);
		$this->assertSame($target, $options['sink']);
		$this->assertSame(300, $options['timeout']);
	}

	/**
	 * @dataProvider foreignUrls
	 */
	public function testForeignAddressGetsNoInstanceHeaders(string $url) {
		$this->givenSystemConfig([]);
		$this->givenServerResponds();

		$this->httpService->downloadApp($url, $this->targetPath());

		$this->assertSame([], $this->instanceHeaderNames(0));
		$this->assertArrayNotHasKey('allow_redirects', $this->requests[0]['options']);
	}

	public function foreignUrls(): array {
		return [
			'anderer Host' => ['https://github.example.test/wnd.tar.gz'],
			'anderes Schema' => ['http://marketplace.example.test/downloads/wnd.tar.gz'],
			'anderer Port' => ['https://marketplace.example.test:8443/downloads/wnd.tar.gz'],
			'Host nur als Anfang' => ['https://marketplace.example.test.evil.test/wnd.tar.gz'],
			'Host nur im Pfad' => ['https://evil.test/marketplace.example.test/wnd.tar.gz'],
			'Host nur als Benutzerangabe' => ['https://marketplace.example.test@evil.test/wnd.tar.gz'],
		];
	}

	/**
	 * @dataProvider sameOriginUrls
	 */
	public function testSameOriginWrittenDifferentlyGetsInstanceHeaders(string $storeUrl, string $url) {
		$this->givenSystemConfig(['appstoreurl' => $storeUrl]);
		$this->givenServerResponds();

		$this->httpService->downloadApp($url, $this->targetPath());

		$this->assertContains('X-Market-Instance', $this->instanceHeaderNames(0));
	}

	public function sameOriginUrls(): array {
		return [
			'Standardport ausgeschrieben' => [self::STORE, 'https://marketplace.example.test:443/downloads/wnd.tar.gz'],
			'Host in Großbuchstaben' => [self::STORE, 'https://MARKETPLACE.Example.test/downloads/wnd.tar.gz'],
			'appstoreurl mit Pfad' => [self::STORE . '/index.php/', self::STORE . '/downloads/wnd.tar.gz'],
		];
	}

	public function testLocalCatalogSendsNoInstanceHeaders() {
		$this->givenSystemConfig(['appstoreurl' => 'local']);
		$this->givenServerResponds();

		$this->httpService->downloadApp('https://github.example.test/wnd.tar.gz', $this->targetPath());

		$this->assertSame([], $this->instanceHeaderNames(0));
	}

	/**
	 * @dataProvider unusableInstanceIds
	 */
	public function testUnusableInstanceIdIsLeftOut($instanceId) {
		$this->givenSystemConfig(['instanceid' => $instanceId]);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		$headers = $this->requests[0]['options']['headers'];
		$this->assertArrayNotHasKey('X-Market-Instance', $headers);
		$this->assertSame('cloud.example.test', $headers['X-Market-Server-Host']);
	}

	public function unusableInstanceIds(): array {
		return [
			'nicht gesetzt' => [null],
			'Zeilenumbruch mitten drin' => ["oc1a2b3c4d5e\r\nX-Evil: 1"],
			'Zeilenumbruch am Ende' => ["oc1a2b3c4d5e\n"],
			'Großbuchstaben' => ['OC1A2B3C4D5E'],
			'zu kurz' => ['oc1'],
			'kein Text' => [['oc1a2b3c4d5e']],
		];
	}

	/**
	 * @dataProvider serverHosts
	 */
	public function testServerHostComesFromTheConfiguredAddresses(array $config, ?string $expected) {
		$this->givenSystemConfig($config);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		$this->assertSame($expected, $this->requests[0]['options']['headers']['X-Market-Server-Host'] ?? null);
	}

	public function serverHosts(): array {
		return [
			'aus overwrite.cli.url, ohne Port und Pfad' => [['overwrite.cli.url' => 'https://Cloud.Example.TEST:8443/owncloud'], 'cloud.example.test'],
			'abschließender Punkt' => [['overwrite.cli.url' => 'https://cloud.example.test./'], 'cloud.example.test'],
			'overwrite.cli.url ohne Schema' => [['overwrite.cli.url' => 'cloud.example.test/owncloud'], 'cloud.example.test'],
			'sonst erste vertrauenswürdige Domain' => [['overwrite.cli.url' => null, 'trusted_domains' => ['Files.Example.TEST:8443', 'other.example.test']], 'files.example.test'],
			'unbrauchbares overwrite.cli.url' => [['overwrite.cli.url' => 'https://*.example.test', 'trusted_domains' => ['files.example.test']], 'files.example.test'],
			'nichts Brauchbares' => [['overwrite.cli.url' => 'http://[2001:db8::1]:8080', 'trusted_domains' => ['*']], null],
			// Nach einer Installation über occ steht dort http://localhost und
			// localhost an erster Stelle der vertrauenswürdigen Domains.
			'localhost zählt nicht' => [['overwrite.cli.url' => 'http://localhost', 'trusted_domains' => ['localhost', 'cloud.example.test']], 'cloud.example.test'],
			'Loopback-Adresse zählt nicht' => [['overwrite.cli.url' => 'http://127.0.0.1:8080', 'trusted_domains' => ['127.0.0.1', 'dev.localhost']], null],
			'kein Text' => [['overwrite.cli.url' => ['https://cloud.example.test'], 'trusted_domains' => 'files.example.test'], null],
			'abgeschaltet' => [['marketplace.send_host' => false], null],
			// occ config:system:set ohne --type=boolean speichert Text
			'abgeschaltet als Text' => [['marketplace.send_host' => 'false'], null],
			'eingeschaltet als Text' => [['marketplace.send_host' => 'true'], 'cloud.example.test'],
		];
	}

	public function testInternationalServerHostIsSentAsPunycode() {
		if (!\function_exists('idn_to_ascii')) {
			$this->markTestSkipped('Ohne intl bleibt ein internationaler Hostname weg.');
		}
		$this->givenSystemConfig(['overwrite.cli.url' => 'https://Bücher.example.test/']);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		$this->assertSame('xn--bcher-kva.example.test', $this->requests[0]['options']['headers']['X-Market-Server-Host']);
	}

	public function testUnknownOwnVersionLeavesOutTheClientHeader() {
		$this->marketAppInfo = null;
		$this->givenSystemConfig([]);
		$this->givenServerResponds();

		$this->httpService->getCategories();

		$headers = $this->requests[0]['options']['headers'];
		$this->assertArrayNotHasKey('X-Market-Client', $headers);
		$this->assertSame('owncloud.online-market', $headers['User-Agent']);
	}

	public function testRedirectWithinTheMarketplaceKeepsAllHeaders() {
		// Live leitet /index.php/… per 302 auf die kurze Adresse um.
		$this->givenSystemConfig(['appstoreurl' => self::STORE . '/index.php']);
		$this->givenServerResponds([
			self::STORE . '/index.php/api/v1/categories.json' => [302, '/api/v1/categories.json'],
		], '[{"id":"tools"}]');

		$categories = $this->httpService->getCategories();

		$this->assertSame([['id' => 'tools']], $categories);
		$this->assertFalse($this->requests[0]['options']['allow_redirects']);
		$this->assertSame(self::CATEGORIES_URL, $this->requests[1]['url']);
		$this->assertSame($this->sortedHeaders(0), $this->sortedHeaders(1));
	}

	public function testRedirectToAnotherHostDropsKeyAndInstanceHeaders() {
		$this->givenSystemConfig([]);
		$this->givenServerResponds([
			self::WND_URL => [302, 'https://cdn.example.test/wnd-2.0.2.tar.gz'],
		]);

		$this->httpService->downloadApp(self::WND_URL, $this->targetPath());

		$this->assertSame('https://cdn.example.test/wnd-2.0.2.tar.gz', $this->requests[1]['url']);
		// Wie Guzzle selbst: kein Schlüssel über die Herkunftsgrenze. Ohne
		// eigene Kopfzeilen darf Guzzle weiteren Weiterleitungen selbst folgen.
		$this->assertArrayNotHasKey('headers', $this->requests[1]['options']);
		$this->assertArrayNotHasKey('allow_redirects', $this->requests[1]['options']);
	}

	public function testRedirectFromHttpToHttpsCountsAsAnotherOrigin() {
		// Mit appstoreurl über http leitet live per 301 auf https um. Guzzle
		// ließ den Schlüssel dabei schon bisher weg; das bleibt so.
		$this->givenSystemConfig(['appstoreurl' => 'http://marketplace.example.test']);
		$this->givenServerResponds([
			'http://marketplace.example.test/api/v1/categories.json' => [301, self::CATEGORIES_URL],
		]);

		$this->httpService->getCategories();

		$this->assertSame(self::INSTANCE_ID, $this->requests[0]['options']['headers']['X-Market-Instance']);
		$this->assertSame(self::CATEGORIES_URL, $this->requests[1]['url']);
		$this->assertArrayNotHasKey('headers', $this->requests[1]['options']);
	}

	public function testEndlessRedirectsAreStopped() {
		$url = self::CATEGORIES_URL;
		$this->givenSystemConfig([]);
		$this->givenServerResponds([$url => [302, $url]]);

		try {
			$this->httpService->getCategories();
			$this->fail('Die endlose Weiterleitung wurde nicht abgebrochen.');
		} catch (AppManagerException) {
			$this->assertCount(6, $this->requests);
		}
	}

	public function testRedirectToANonHttpLocationIsRefused() {
		$this->givenSystemConfig([]);
		$this->givenServerResponds([
			self::WND_URL => [302, 'file:///etc/passwd'],
		]);

		try {
			$this->httpService->downloadApp(self::WND_URL, $this->targetPath());
			$this->fail('Die Weiterleitung auf eine lokale Datei wurde angenommen.');
		} catch (AppManagerException) {
			$this->assertCount(1, $this->requests);
		}
	}

	public function testRejectedKeyIsStillReportedAsInvalidKey() {
		$url = self::CATEGORIES_URL;
		$this->givenSystemConfig([]);
		$client = $this->createMock(IClient::class);
		$client->method('get')->willThrowException(
			new ClientException('401 Unauthorized', new Request('GET', $url), new Response(401))
		);
		$this->httpClientService->method('newClient')->willReturn($client);

		$this->expectException(AppManagerException::class);
		$this->expectExceptionMessage('Invalid marketplace API key provided');
		$this->httpService->getCategories();
	}

	/**
	 * Systemwerte wie bei einer Instanz mit Marktplatz-Schlüssel; null heißt
	 * „nicht gesetzt“.
	 */
	private function givenSystemConfig(array $overrides): void {
		$values = \array_merge([
			'appstoreurl' => self::STORE,
			'has_internet_connection' => true,
			'marketplace.key' => self::KEY,
			'instanceid' => self::INSTANCE_ID,
			'overwrite.cli.url' => 'https://Cloud.Example.TEST:8443/owncloud',
			'trusted_domains' => ['localhost'],
		], $overrides);
		$this->config->method('getSystemValue')
			->willReturnCallback(
				static fn (string $key, $default = null) => $values[$key] ?? $default
			);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	/**
	 * Gegenstelle für alle Anfragen: Weiterleitungen je Adresse als
	 * [Status, Location], sonst 200 mit $body. Jede Anfrage wird mitgeschrieben.
	 *
	 * @param array<string, array{0: int, 1: string}> $redirects
	 */
	private function givenServerResponds(array $redirects = [], string $body = '[]'): void {
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options = []) use ($redirects, $body): IResponse {
				$this->requests[] = ['url' => $url, 'options' => $options];
				[$status, $location] = $redirects[$url] ?? [200, ''];
				$response = $this->createMock(IResponse::class);
				$response->method('getStatusCode')->willReturn($status);
				$response->method('getHeader')->willReturnCallback(
					static fn (string $name) => \strcasecmp($name, 'Location') === 0 ? $location : ''
				);
				$response->method('getBody')->willReturn($body);
				return $response;
			}
		);
		$this->httpClientService->method('newClient')->willReturn($client);
	}

	/** Kopfzeilen der n-ten Anfrage, nach Namen sortiert. */
	private function sortedHeaders(int $index): array {
		$headers = $this->requests[$index]['options']['headers'] ?? [];
		\ksort($headers);
		return $headers;
	}

	/** Namen der X-Market-Kopfzeilen der n-ten Anfrage. */
	private function instanceHeaderNames(int $index): array {
		return \array_values(\array_filter(
			\array_keys($this->requests[$index]['options']['headers'] ?? []),
			static fn (string $name): bool => \stripos($name, 'x-market-') === 0
		));
	}

	private function givenAppstoreUrl(string $url): void {
		$this->config->method('getSystemValue')
			->willReturnCallback(
				static fn (string $key, $default = null) => match ($key) {
					'appstoreurl' => $url,
					default => $default,
				}
			);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function givenLocalCatalog(): string {
		$catalog = \sys_get_temp_dir() . '/market-catalog-' . \uniqid('', true) . '/catalog';
		\mkdir($catalog, 0o700, true);
		$this->createdPaths[] = $catalog;
		$this->givenAppstoreUrl('file://' . $catalog);

		return $catalog;
	}

	private function targetPath(): string {
		$path = \sys_get_temp_dir() . '/market-target-' . \uniqid('', true);
		$this->createdPaths[] = $path;

		return $path;
	}

	private function getClientResponseMockForGet($body) {
		$responseMock = $this->createMock(IResponse::class);
		$responseMock->method('getBody')->willReturn($body);
		$clientMock = $this->createMock(IClient::class);
		$clientMock->method('get')->willReturn($responseMock);
		return $clientMock;
	}

	private function getClientResponseMockForPost($body) {
		$responseMock = $this->createMock(IResponse::class);
		$responseMock->method('getBody')->willReturn($body);
		$clientMock = $this->createMock(IClient::class);
		$clientMock->method('post')->willReturn($responseMock);
		return $clientMock;
	}
}
