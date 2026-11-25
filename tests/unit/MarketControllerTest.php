<?php

/**
 * @author Ilja Neumann <ineumann@owncloud.com>
 *
 * @copyright Copyright (c) 2019, ownCloud GmbH
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

use OCA\Market\Controller\MarketController;
use OCA\Market\MarketService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use Test\TestCase;

class MarketControllerTest extends TestCase {
	/** @var MarketController */
	private $controller;
	/** @var IRequest */
	private $request;
	/** @var MarketService|\PHPUnit\Framework\MockObject\MockObject */
	private $marketService;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->marketService = $this->createMock(MarketService::class);

		$this->controller = new MarketController(
			'market',
			$this->request,
			$this->marketService,
			$this->createMock(IL10N::class),
			$this->createMock(IConfig::class)
		);
	}

	public function testApiKeyIsNotReturnedIfNotChangeableByUser() {
		$this->marketService
			->method('isApiKeyChangeableByUser')
			->willReturn(false);

		/** @var array */
		$response = $this->controller->getApiKey()->getData();
		$this->assertArrayNotHasKey('apiKey', $response);
	}

	public function testApiKeyIsReturnedIfChangeableByUser() {
		$this->marketService
			->method('isApiKeyChangeableByUser')
			->willReturn(true);

		/** @var array */
		$response = $this->controller->getApiKey()->getData();
		$this->assertArrayHasKey('apiKey', $response);
	}

	public function testInvalidApiKeyIsFlaggedIndependentOfTranslation() {
		$l10n = $this->createMock(IL10N::class);
		// Übersetzte Meldung: die Oberfläche darf sich nicht am Wortlaut orientieren
		$l10n->method('t')->willReturn('Der API-Key ist ungültig.');
		$controller = new MarketController(
			'market',
			$this->request,
			$this->marketService,
			$l10n,
			$this->createMock(IConfig::class)
		);
		$this->marketService->method('isApiKeyValid')->willReturn(false);
		$this->marketService->expects($this->never())->method('setApiKey');

		/** @var array */
		$response = $controller->changeApiKey('ungueltig')->getData();
		$this->assertFalse($response['valid']);
		$this->assertSame('Der API-Key ist ungültig.', $response['message']);
	}

	public function testKeyFromConfigIsReportedWithoutRevealingIt() {
		$this->marketService->method('isApiKeyChangeableByUser')->willReturn(false);
		$this->marketService->method('getApiKey')->willReturn('bwmp_secret');

		$response = $this->controller->getApiKey()->getData();

		$this->assertTrue($response['hasKey']);
		$this->assertArrayNotHasKey('apiKey', $response);
	}

	public function testCatalogMarksAppsWithoutSupportContractAndShowsGermanTexts() {
		$this->marketService->method('prefersGerman')->willReturn(true);
		$this->marketService->method('isAppInstalled')->willReturn(false);
		$this->marketService->method('getMissingDependencies')->willReturn([]);
		$this->marketService->method('listApps')->willReturn([
			[
				'id' => 'wnd',
				'summary' => 'Windows network drives',
				'summary_de' => 'Windows-Netzlaufwerke',
				'description' => 'Mount SMB shares.',
				'description_de' => '',
				'requiresKey' => true,
				'entitled' => false,
				'releases' => [['version' => '2.0.2', 'download' => '']],
			],
			[
				'id' => 'activity',
				'summary' => 'Activity stream',
				'releases' => [['version' => '2.8.7', 'download' => 'https://example.test/a.tar.gz']],
			],
		]);

		$apps = $this->controller->index();

		$this->assertTrue($apps[0]['locked']);
		$this->assertSame('Windows-Netzlaufwerke', $apps[0]['summary']);
		// Leerer deutscher Text: der englische bleibt.
		$this->assertSame('Mount SMB shares.', $apps[0]['description']);
		$this->assertSame('2.0.2', $apps[0]['release']['version']);
		// Alter Marktplatz ohne entitled: wie bisher installierbar.
		$this->assertFalse($apps[1]['locked']);
		$this->assertSame('Activity stream', $apps[1]['summary']);
	}

	public function testRejectedKeyIsReportedWithFieldAndStatus() {
		$status = ['state' => 'invalid', 'label' => 'Invalid', 'message' => 'This key is not valid.', 'rows' => []];
		$this->marketService->method('isApiKeyValid')->willReturn(false);
		$this->marketService->method('getKeyStatus')->with('bwmp_wrong')->willReturn($status);
		$this->marketService->expects($this->never())->method('setApiKey');

		$response = $this->controller->changeApiKey('bwmp_wrong')->getData();

		$this->assertFalse($response['valid']);
		$this->assertSame($status, $response['status']);
	}

	public function testSavedKeyAnswersWithItsStatus() {
		$status = ['state' => 'active', 'label' => 'Active', 'message' => 'Key valid.', 'rows' => []];
		$this->request->method('getMethod')->willReturn('PUT');
		$this->marketService->method('isApiKeyValid')->willReturn(true);
		$this->marketService->method('setApiKey')->willReturn(true);
		$this->marketService->method('getKeyStatus')->with('bwmp_good')->willReturn($status);

		$response = $this->controller->changeApiKey('bwmp_good')->getData();

		$this->assertTrue($response['valid']);
		$this->assertSame($status, $response['status']);
	}

	public function testKeyStatusAndSupportContactArePassedOn() {
		$status = ['state' => 'none', 'label' => 'No key saved', 'message' => '', 'rows' => []];
		$contact = ['email' => 'support@owncloudonline.com', 'url' => 'https://owncloud.online', 'title' => 'Support contract', 'text' => 'Available with a support contract.'];
		$this->marketService->method('getKeyStatus')->willReturn($status);
		$this->marketService->method('getSupportContact')->willReturn($contact);

		$this->assertSame($status, $this->controller->keyStatus()->getData());
		$this->assertSame($contact, $this->controller->support()->getData());
	}

	/**
	 * Was Schlüssel oder Lizenz schreibt, braucht das CSRF-Token: sonst löst
	 * eine fremde Seite es im Browser eines angemeldeten Admins aus.
	 *
	 * @dataProvider writingMethods
	 */
	public function testWritingMethodsRequireTheCsrfToken(string $method) {
		$doc = (string) (new \ReflectionMethod(MarketController::class, $method))->getDocComment();
		$this->assertStringNotContainsString('@NoCSRFRequired', $doc);
	}

	public function writingMethods(): array {
		return [
			'Schlüssel ändern' => ['changeApiKey'],
			'Demo-Lizenz anfordern' => ['requestDemoLicenseKeyFromMarket'],
		];
	}

	public function testDemoLicenseIsRequestedOnlyByPost() {
		$routes = require __DIR__ . '/../../appinfo/routes.php';
		$verbs = [];
		foreach ($routes['routes'] as $route) {
			if ($route['name'] === 'market#requestDemoLicenseKeyFromMarket') {
				$verbs[] = $route['verb'] ?? 'GET';
			}
		}
		$this->assertSame(['POST'], $verbs);
	}
}
