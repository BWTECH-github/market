<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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

namespace OCA\Market\Tests\Unit\Command;

use OCA\Market\Command\ListApps;
use OCA\Market\MarketService;
use OCP\IL10N;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

class ListAppsTest extends TestCase {
	/** @var CommandTester */
	private $commandTester;
	/** @var MarketService | \PHPUnit\Framework\MockObject\MockObject */
	private $marketService;

	public function setUp(): void {
		parent::setUp();

		$this->marketService = $this->createMock(MarketService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text): string => $text === '[support contract]' ? '[Support-Vertrag]' : $text
		);
		$command = new ListApps($this->marketService, $l10n);
		$this->commandTester = new CommandTester($command);
	}

	public function testListApps() {
		$this->marketService->expects($this->once())->method('listApps')->willReturn([
			['id' => 'foo'],
			['id' => 'bar'],
		]);
		$this->commandTester->execute([]);
		$output = $this->commandTester->getDisplay();
		$this->assertStringContainsString("bar\nfoo", $output);
	}

	public function testAppsWithoutSupportContractAreMarked() {
		$this->marketService->method('listApps')->willReturn([
			['id' => 'wnd', 'requiresKey' => true, 'entitled' => false],
			['id' => 'activity', 'requiresKey' => false, 'entitled' => true],
			// Mit gültigem Schlüssel freigeschaltet: keine Marke.
			['id' => 'theme-owncloudonline', 'requiresKey' => true, 'entitled' => true],
		]);

		$this->assertSame(0, $this->commandTester->execute([]));
		$this->assertSame(
			"activity\ntheme-owncloudonline\nwnd [Support-Vertrag]\n",
			$this->commandTester->getDisplay()
		);
	}

	public function testOldMarketplaceWithoutNewFieldsListsAsBefore() {
		$this->marketService->method('listApps')->willReturn([
			['id' => 'wnd'],
			['id' => 'activity'],
		]);

		$this->commandTester->execute([]);

		$this->assertSame("activity\nwnd\n", $this->commandTester->getDisplay());
	}
}
