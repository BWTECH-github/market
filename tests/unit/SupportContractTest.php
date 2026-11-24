<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
 *
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

use OCA\Market\HttpService;
use OCA\Market\MarketService;
use OCA\Market\VersionHelper;
use OCP\App\AppManagerException;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use Test\TestCase;

/**
 * Apps mit Support-Vertrag, Kontakt, Stand des Vertrags und App-Texte in der
 * Sprache des Nutzers.
 */
class SupportContractTest extends TestCase {
	private const KEY = 'bwmp_0123456789abcdef0123456789abcdef0123456789abcdef';

	/** @var HttpService | \PHPUnit\Framework\MockObject\MockObject */
	private $httpService;
	/** @var IAppManager | \PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var IConfig | \PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IL10N | \PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	private MarketService $marketService;
	private string $language = 'en';

	protected function setUp(): void {
		parent::setUp();
		$this->httpService = $this->createMock(HttpService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		// Wie IL10N::t: Platzhalter füllen, Text unübersetzt lassen.
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []): string => \vsprintf($text, (array) $parameters)
		);
		$this->l10n->method('getLanguageCode')->willReturnCallback(fn (): string => $this->language);
		$this->l10n->method('l')->willReturnCallback(
			static fn (string $type, \DateTime $date): string => $date->format('Y-m-d')
		);
		$versionHelper = $this->createMock(VersionHelper::class);
		$versionHelper->method('compare')->willReturn(false);
		$this->appManager->method('canInstall')->willReturn(true);
		$this->marketService = new MarketService(
			$this->httpService,
			$versionHelper,
			$this->appManager,
			$this->config,
			$this->l10n
		);
	}

	// -----------------------------------------------------------------------
	// Apps ohne Support-Vertrag: Der Marktplatz listet sie für Market ab
	// 0.11.0 mit entitled=false und leerer Download-Adresse.
	// -----------------------------------------------------------------------

	public function testOnlyEntitledFalseLocksAnApp() {
		$this->assertTrue(MarketService::isLocked(['id' => 'wnd', 'requiresKey' => true, 'entitled' => false]));
		$this->assertFalse(MarketService::isLocked(['id' => 'wnd', 'requiresKey' => true, 'entitled' => true]));
		$this->assertFalse(MarketService::isLocked(['id' => 'activity', 'requiresKey' => false, 'entitled' => true]));
		// Alter Marktplatz ohne die neuen Felder: wie bisher freigegeben.
		$this->assertFalse(MarketService::isLocked(['id' => 'activity']));
	}

	public function testLockedAppIsNotDownloadedAndTheMessageNamesTheContact() {
		$this->givenCatalog([$this->lockedApp()]);
		$this->givenNoInstalledApps();
		$this->httpService->method('getSupport')->willReturn(['email' => 'vertrieb@example.test', 'url' => 'https://example.test/vertrag']);
		$this->httpService->expects($this->never())->method('downloadApp');

		$this->expectException(AppManagerException::class);
		$this->expectExceptionMessage('Available with a support contract. Request one at vertrieb@example.test or https://example.test/vertrag.');
		$this->marketService->installApp('wnd');
	}

	public function testEmptyDownloadIsNeverRequested() {
		// Geschützt, aber ohne entitled (Zwischenstand eines Marktplatzes):
		// Die leere Adresse wird trotzdem nicht angefragt.
		$app = $this->lockedApp();
		unset($app['entitled']);
		$this->givenCatalog([$app]);
		$this->givenNoInstalledApps();
		$this->httpService->expects($this->never())->method('downloadApp');

		$this->expectException(AppManagerException::class);
		$this->expectExceptionMessage('Available with a support contract. Request one at support@owncloudonline.com or https://owncloud.online.');
		$this->marketService->installApp('wnd');
	}

	public function testEmptyDownloadOfAFreeAppDoesNotMentionAContract() {
		$this->givenCatalog([[
			'id' => 'broken',
			'releases' => [['version' => '1.0.0', 'platformMin' => '11', 'platformMax' => '11.99', 'download' => '']],
		]]);
		$this->givenNoInstalledApps();
		$this->httpService->expects($this->never())->method('downloadApp');

		$this->expectException(AppManagerException::class);
		$this->expectExceptionMessage('No download available for broken.');
		$this->marketService->installApp('broken');
	}

	public function testInstalledLockedAppReportsNoUpdate() {
		$this->givenCatalog([$this->lockedApp()]);
		$this->appManager->method('getAllApps')->willReturn(['wnd']);
		$this->appManager->method('getAppInfo')->willReturn(['id' => 'wnd', 'version' => '2.0.1']);

		$this->assertSame(['major' => false, 'minor' => false], $this->marketService->getAvailableUpdateVersions('wnd'));
		$this->assertSame([], $this->marketService->getUpdates());
		$this->assertTrue($this->marketService->isAppLocked('wnd'));
		$this->assertFalse($this->marketService->isAppLocked('unknown'));
	}

	// -----------------------------------------------------------------------
	// Kontakt aus /api/v1/support.json
	// -----------------------------------------------------------------------

	public function testSupportContactFallsBackWithoutAnswer() {
		$this->httpService->method('getSupport')->willThrowException(new AppManagerException('No marketplace connection: 404'));

		$this->assertSame([
			'email' => 'support@owncloudonline.com',
			'url' => 'https://owncloud.online',
			'title' => 'Support contract',
			'text' => 'Available with a support contract.',
		], $this->marketService->getSupportContact());
	}

	public function testSupportContactUsesTheTextsInTheUsersLanguage() {
		$this->language = 'de_DE';
		$this->httpService->expects($this->once())->method('getSupport')->willReturn([
			'email' => 'support@owncloudonline.com',
			'url' => 'https://owncloud.online/support',
			'title_de' => 'Support-Vertrag',
			'title_en' => 'Support contract',
			'text_de' => "Diese App gibt es mit einem Support-Vertrag.\n",
			'text_en' => 'Available with a support contract.',
		]);

		$contact = $this->marketService->getSupportContact();

		$this->assertSame('https://owncloud.online/support', $contact['url']);
		$this->assertSame('Support-Vertrag', $contact['title']);
		$this->assertSame('Diese App gibt es mit einem Support-Vertrag.', $contact['text']);
		// Einmal je Prozess gefragt.
		$this->marketService->getSupportContact();
	}

	/**
	 * @dataProvider unsafeContacts
	 */
	public function testUnsafeContactIsReplacedByTheDefault(array $answer, string $field, string $expected) {
		$this->httpService->method('getSupport')->willReturn($answer);

		$this->assertSame($expected, $this->marketService->getSupportContact()[$field]);
	}

	public function unsafeContacts(): array {
		return [
			'Link mit javascript:' => [['url' => 'javascript:alert(1)'], 'url', 'https://owncloud.online'],
			'Link ohne Schema' => [['url' => 'owncloud.online'], 'url', 'https://owncloud.online'],
			'Link mit Anführungszeichen' => [['url' => 'https://example.test/"onmouseover="x'], 'url', 'https://owncloud.online'],
			'keine Adresse' => [['email' => 'kein-at-zeichen'], 'email', 'support@owncloudonline.com'],
			'Adresse mit Zeilenumbruch' => [['email' => "support@example.test\nBcc: x@example.test"], 'email', 'support@owncloudonline.com'],
			'kein Text' => [['email' => ['support@example.test']], 'email', 'support@owncloudonline.com'],
			'leerer Titel' => [['title_en' => '   '], 'title', 'Support contract'],
		];
	}

	// -----------------------------------------------------------------------
	// Stand des Support-Vertrags (/api/v1/key/status)
	// -----------------------------------------------------------------------

	public function testWithoutKeyNothingIsAsked() {
		$this->givenSavedKey(null);
		$this->httpService->expects($this->never())->method('fetchKeyStatus');

		$status = $this->marketService->getKeyStatus();

		$this->assertSame('none', $status['state']);
		$this->assertSame('No key saved', $status['label']);
	}

	public function testLocalCatalogNeedsNoKey() {
		$this->givenSavedKey(self::KEY);
		$this->httpService->method('isLocalCatalog')->willReturn(true);
		$this->httpService->expects($this->never())->method('fetchKeyStatus');

		$this->assertSame('local', $this->marketService->getKeyStatus()['state']);
	}

	public function testValidContractShowsEndDateSystemsAndThisSystem() {
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, [
			'status' => 'valid',
			'expires_at' => '2099-11-24T22:59:59+00:00',
			'instances' => ['used' => 3, 'max' => 10],
			'instance_status' => 'approved',
			'message_de' => 'Support-Vertrag gültig.',
			'message_en' => 'Support contract valid.',
		]);

		$this->assertSame([
			'state' => 'active',
			'label' => 'Active',
			'message' => 'Support contract valid.',
			'rows' => [
				['label' => 'Valid until', 'value' => '2099-11-24'],
				['label' => 'Systems', 'value' => '3 of 10'],
				['label' => 'This system', 'value' => 'Allowed'],
			],
		], $this->marketService->getKeyStatus());
	}

	public function testMessageAndContractLabelFollowTheUsersLanguage() {
		$this->language = 'de';
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, [
			'status' => 'valid',
			'expires_at' => null,
			'instances' => ['used' => 1, 'max' => null],
			'instance_status' => 'approved',
			'message_de' => 'Support-Vertrag gültig.',
			'message_en' => 'Support contract valid.',
			'contract_label' => 'Scale Logic',
		]);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame('Support-Vertrag gültig.', $status['message']);
		$this->assertSame([
			['label' => 'Contract', 'value' => 'Scale Logic'],
			['label' => 'Valid until', 'value' => 'No end date'],
			['label' => 'Systems', 'value' => '1 (no limit)'],
			['label' => 'This system', 'value' => 'Allowed'],
		], $status['rows']);
	}

	public function testMissingMessageInTheUsersLanguageFallsBackToTheOther() {
		$this->language = 'de_CH';
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, ['status' => 'expired', 'message_en' => 'Key expired.']);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame('expired', $status['state']);
		$this->assertSame('Key expired.', $status['message']);
	}

	/**
	 * @dataProvider payloadStates
	 */
	public function testPayloadStates(array $payload, string $state, string $label) {
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, $payload);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame($state, $status['state']);
		$this->assertSame($label, $status['label']);
	}

	public function payloadStates(): array {
		$soon = \gmdate('c', \time() + 10 * 86400);
		$later = \gmdate('c', \time() + 200 * 86400);
		return [
			'gültig' => [['status' => 'valid', 'expires_at' => $later, 'instance_status' => 'approved'], 'active', 'Active'],
			'läuft bald ab' => [['status' => 'valid', 'expires_at' => $soon, 'instance_status' => 'approved'], 'expiring', 'Expires soon'],
			'Resttage vom Marktplatz' => [['status' => 'valid', 'expires_at' => $later, 'expires_in_days' => 5], 'expiring', 'Expires soon'],
			'noch 30 Tage: wie der Marktplatz aktiv' => [['status' => 'valid', 'expires_in_days' => 30], 'active', 'Active'],
			'noch 29 Tage: läuft bald ab' => [['status' => 'valid', 'expires_in_days' => 29], 'expiring', 'Expires soon'],
			'Zustand vom Marktplatz gilt' => [['status' => 'valid', 'expires_in_days' => 5, 'contract_status' => 'active'], 'active', 'Active'],
			'gültig und markiert: lädt, also aktiv' => [['status' => 'valid', 'instance_status' => 'pending'], 'active', 'Active'],
			'abgelaufen' => [['status' => 'expired'], 'expired', 'Expired'],
			'vorübergehend gesperrt' => [['status' => 'paused'], 'blocked', 'Temporarily blocked'],
			'alle geschützten Apps gesperrt' => [['status' => 'disabled'], 'blocked', 'Temporarily blocked'],
			'System nicht zugelassen' => [['status' => 'instance_pending', 'instance_status' => 'pending'], 'pending', 'Waiting to be allowed'],
			'System gesperrt' => [['status' => 'instance_blocked', 'instance_status' => 'blocked'], 'system_blocked', 'System blocked'],
			'alte Market-App' => [['status' => 'client_update_required'], 'update', 'Update the Market app'],
			'gelöscht' => [['status' => 'revoked'], 'invalid', 'Invalid'],
			'unbekannter Zustand' => [['status' => 'something_new'], 'unknown', 'Status unknown'],
		];
	}

	public function testPendingSystemIsNamedInTheRows() {
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, [
			'status' => 'instance_pending',
			'instances' => ['used' => 1, 'max' => 1],
			'instance_status' => 'pending',
			'message_en' => 'This system is not allowed yet.',
		]);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame('This system is not allowed yet.', $status['message']);
		$this->assertContains(['label' => 'Systems', 'value' => '1 of 1'], $status['rows']);
		$this->assertContains(['label' => 'This system', 'value' => 'Not allowed yet'], $status['rows']);
	}

	public function testValidContractNamesThisSystemAllowed() {
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus(200, [
			'status' => 'valid',
			'instances' => ['used' => 2, 'max' => 1],
			'instance_status' => 'pending',
			'message_en' => 'Support contract active.',
		]);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame('active', $status['state']);
		$this->assertContains(['label' => 'This system', 'value' => 'Allowed'], $status['rows']);
	}

	/**
	 * @dataProvider rejections
	 */
	public function testRejectionsBecomeAReason(int $code, ?array $body, string $state, string $message) {
		$this->givenSavedKey(self::KEY);
		$this->givenKeyStatus($code, $body);

		$status = $this->marketService->getKeyStatus();

		$this->assertSame($state, $status['state']);
		$this->assertSame($message, $status['message']);
		$this->assertSame([], $status['rows']);
	}

	public function rejections(): array {
		return [
			'unbekannter Schlüssel' => [401, ['message' => 'Schlüssel ungültig. (Invalid key.)', 'error' => 'invalid_key'], 'invalid', 'This key is not valid.'],
			'Marktplatz ohne Statusabfrage' => [404, null, 'unknown', 'The marketplace does not show the status of support contracts.'],
			'zu viele Versuche' => [429, ['message' => 'Zu viele Versuche.', 'error' => 'throttled'], 'unknown', 'Too many attempts. Please try again later.'],
			'Serverfehler mit Meldung' => [503, ['message' => 'Wartung'], 'unknown', 'Wartung'],
			'Serverfehler ohne Meldung' => [500, null, 'unknown', 'No marketplace connection: HTTP 500'],
		];
	}

	public function testNoConnectionIsAReasonAndIsNotAskedTwice() {
		// Ein zweiter Versuch in derselben Anfrage liefe nur erneut in das
		// Zeitlimit der Verbindung.
		$this->givenSavedKey(self::KEY);
		$this->httpService->expects($this->once())->method('fetchKeyStatus')
			->willThrowException(new AppManagerException('The Internet connection is disabled.'));

		$this->assertSame('The Internet connection is disabled.', $this->marketService->getKeyStatus()['message']);
		$this->assertSame('unknown', $this->marketService->getKeyStatus()['state']);
	}

	public function testKeyIsNotCheckedASecondTimeWithoutConnection() {
		$this->httpService->method('fetchKeyStatus')
			->willThrowException(new AppManagerException('No marketplace connection: timeout'));
		$this->httpService->expects($this->never())->method('validateKey');

		$this->assertFalse($this->marketService->isApiKeyValid(self::KEY));
		$this->assertSame('No marketplace connection: timeout', $this->marketService->getKeyStatus(self::KEY)['message']);
	}

	public function testAnswerIsRememberedForTheRequest() {
		$this->givenSavedKey(self::KEY);
		$this->httpService->expects($this->once())->method('fetchKeyStatus')
			->willReturn(['status' => 200, 'body' => ['status' => 'valid']]);

		$this->marketService->getKeyStatus();
		$this->marketService->getKeyStatus(self::KEY);
	}

	// -----------------------------------------------------------------------
	// Schlüssel speichern: Was der Marktplatz ablehnt, wird nicht gespeichert.
	// -----------------------------------------------------------------------

	public function testKeyRejectedByTheMarketplaceIsInvalid() {
		$this->givenKeyStatus(401, ['error' => 'invalid_key']);
		$this->httpService->expects($this->never())->method('validateKey');

		$this->assertFalse($this->marketService->isApiKeyValid('bwmp_wrong'));
	}

	public function testKnownKeyIsValidEvenIfTheContractExpired() {
		// Abgelaufen ist kein Tippfehler: speichern, der Stand sagt den Grund.
		$this->givenKeyStatus(200, ['status' => 'expired']);
		$this->httpService->expects($this->never())->method('validateKey');

		$this->assertTrue($this->marketService->isApiKeyValid(self::KEY));
	}

	public function testMarketplaceWithoutStatusIsCheckedAsBefore() {
		$this->givenKeyStatus(404, null);
		$this->httpService->expects($this->once())->method('validateKey')->with(self::KEY);

		$this->assertTrue($this->marketService->isApiKeyValid(self::KEY));
	}

	public function testEmptyKeyIsValidWithoutAsking() {
		$this->httpService->expects($this->never())->method('fetchKeyStatus');

		$this->assertTrue($this->marketService->isApiKeyValid(''));
	}

	// -----------------------------------------------------------------------
	// App-Texte in der Sprache des Nutzers
	// -----------------------------------------------------------------------

	/**
	 * @dataProvider languages
	 */
	public function testGermanIsRecognised(string $language, bool $german) {
		$this->language = $language;

		$this->assertSame($german, $this->marketService->prefersGerman());
	}

	public function languages(): array {
		return [
			['de', true], ['de_DE', true], ['de_CH', true], ['de-AT', true],
			['en', false], ['en_GB', false], ['fr', false], ['', false],
		];
	}

	public function testGermanTextsReplaceTheEnglishOnes() {
		$app = [
			'summary' => 'Windows network drives',
			'summary_de' => 'Windows-Netzlaufwerke',
			'description' => 'Mount SMB shares.',
			'description_de' => 'SMB-Freigaben einbinden.',
		];

		$german = MarketService::localizeTexts($app, true);
		$english = MarketService::localizeTexts($app, false);

		$this->assertSame('Windows-Netzlaufwerke', $german['summary']);
		$this->assertSame('SMB-Freigaben einbinden.', $german['description']);
		$this->assertSame($app, $english);
	}

	public function testEmptyGermanTextKeepsTheEnglishOne() {
		$app = ['summary' => 'Activity stream', 'summary_de' => ' ', 'description' => 'Shows activity.'];

		$this->assertSame($app, MarketService::localizeTexts($app, true));
	}

	private function lockedApp(): array {
		return [
			'id' => 'wnd',
			'requiresKey' => true,
			'entitled' => false,
			'releases' => [
				['version' => '2.0.2', 'platformMin' => '11', 'platformMax' => '11.99', 'download' => ''],
				['version' => '2.0.1', 'platformMin' => '11', 'platformMax' => '11.99', 'download' => ''],
			],
		];
	}

	private function givenCatalog(array $apps): void {
		$this->httpService->method('getApps')->willReturn($apps);
	}

	private function givenNoInstalledApps(): void {
		$this->appManager->method('getAllApps')->willReturn([]);
	}

	private function givenSavedKey(?string $key): void {
		$this->httpService->method('getApiKey')->willReturn($key);
	}

	private function givenKeyStatus(int $code, ?array $body): void {
		$this->httpService->method('fetchKeyStatus')->willReturn(['status' => $code, 'body' => $body]);
	}
}
