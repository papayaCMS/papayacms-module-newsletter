<?php
require_once(__DIR__.'/../../../bootstrap.php');
require_once(__DIR__.'/../../../../src/Log/Cleanup/Database/Access.php');
class PapayaModuleNewsletterLogCleanupDatabaseAccessTest extends PapayaTestCase {
  /**
   * @covers PapayaModuleNewsletterLogCleanupDatabaseAccess::cleanup
   */
  public function testCleanup() {
    $access = new PapayaModuleNewsletterLogCleanupDatabaseAccess();
    $papayaDatabaseAccess = $this
      ->getMockBuilder('PapayaDatabaseAccess')
      ->setMethods(['getTableName', 'queryFmtWrite'])
      ->disableOriginalConstructor()
      ->getMock();
    $papayaDatabaseAccess
      ->expects($this->once())
      ->method('getTableName')
      ->will($this->returnValue('papaya_newsletter_protocol'));
    $papayaDatabaseAccess
      ->expects($this->once())
      ->method('queryFmtWrite')
      ->will($this->returnValue(7));
    $access->setDatabaseAccess($papayaDatabaseAccess);
    $this->assertTrue($access->cleanup(7, 'both'));
  }
}
