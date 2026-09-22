<?php

namespace OCA\Market\Tests\Unit;

// Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).

use OCA\Market\Controller\PageController;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IRequest;
use Test\TestCase;

class PageControllerTest extends TestCase {
	private $appName = 'market';
	/** @var IRequest */
	private $request;
	/** @var PageController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->controller = new PageController($this->appName, $this->request);
	}

	/**
	 * Bilderquellen, die die Seite des Marktes zulässt – ohne den Speicher des
	 * fremden Marktplatzes (marketplace-storage.owncloud.com/.services).
	 */
	private function expectedPolicy(): ContentSecurityPolicy {
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://owncloud.online');
		$policy->addAllowedImageDomain('https://*.owncloud.online');
		$policy->addAllowedImageDomain('https://*.bw.tech');
		$policy->addAllowedImageDomain('https://raw.githubusercontent.com');
		$policy->addAllowedImageDomain('https://github.com');
		$policy->addAllowedImageDomain('http://minio:9000');
		return $policy;
	}

	public function testIndex() {
		$response = $this->controller->index();

		$this->assertEquals($this->expectedPolicy(), $response->getContentSecurityPolicy());
		$this->assertEquals('index', $response->getTemplateName());
	}

	public function testIndexHash() {
		$response = $this->controller->indexHash();

		$this->assertEquals($this->expectedPolicy(), $response->getContentSecurityPolicy());
		$this->assertEquals('index', $response->getTemplateName());
	}
}
