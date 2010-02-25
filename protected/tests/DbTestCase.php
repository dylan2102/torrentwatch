<?php

class DbTestCase extends CDbTestCase
{
  protected function setUp()
  {
    error_reporting(E_ALL);
    $this->getFixtureManager()->setSubFixture($this->toString());
    parent::setUp();
  }

  protected function tearDown()
  {
    parent::tearDown();
    $this->getFixtureManager()->resetSubFixture();
  }
}
