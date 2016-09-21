<?php
require_once(__DIR__.'/../bootstrap.php');
require_once(__DIR__.'/../../src/Log/Cleanup.php');
require_once(__DIR__.'/../../src/Log/Cleanup/Database/Access.php');
class PapayaModuleNewsletterLogCleanupTest extends PapayaTestCase {
  /**
   * @covers PapayaModuleNewsletterLogCleanup::cleanup
   */
  public function testCleanup() {
    $cleanup = new PapayaModuleNewsletterLogCleanup();
    $databaseAccess = $this
      ->getMockBuilder('PapayaModuleNewsletterLogCleanupDatabaseAccess')
      ->getMock();
    $databaseAccess
      ->expects($this->once())
      ->method('cleanup')
      ->will($this->returnValue(FALSE));
    $cleanup->databaseAccess($databaseAccess);
    $this->assertFalse($cleanup->cleanup(7, 'unsubscriptions'));
  }

  /**
   * @covers PapayaModuleNewsletterLogCleanup::databaseAccess
   */
  public function testDatabaseAccessSet() {
    $cleanup = new PapayaModuleNewsletterLogCleanup();
    $databaseAccess = $this
      ->getMockBuilder('PapayaModuleNewsletterLogCleanupDatabaseAccess')
      ->getMock();
    $this->assertSame($databaseAccess, $cleanup->databaseAccess($databaseAccess));
  }

  /**
   * @covers PapayaModuleNewsletterLogCleanup::databaseAccess
   */
  public function testDatabaseAccessGet() {
    $cleanup = new PapayaModuleNewsletterLogCleanup();
    $this->assertInstanceOf('PapayaModuleNewsletterLogCleanupDatabaseAccess', $cleanup->databaseAccess());
  }
}
