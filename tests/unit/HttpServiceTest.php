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

use OCA\Market\HttpService;
use OCA\Market\VersionHelper;
use OCP\App\AppManagerException;
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
	/** @var HttpService */
	private $httpService;

	/** @var string[] */
	private $createdPaths = [];

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
		$this->httpService = new HttpService(
			$this->httpClientService,
			$this->versionHelper,
			$this->config,
			$this->cacheFactory,
			$this->l10n
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
