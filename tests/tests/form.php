<?php

require_once( '../prepare.php' );

require_once( 'simpletest/unit_tester.php' );

class form_test extends UnitTestCase
{
  public function testArrayAccess()
  {
    $form = HtmlForm::hie('arrayaccess')
      ->text('1')
      ->text('2');

    $this->assertTrue( isset($form['1']) );
    $this->assertTrue( isset($form['2']) );
    $this->assertFalse( isset($form['3']) );

    $field = HtmlFormElement::hie( $form, 'HtmlFormElement', '3' ); 

    $this->assertFalse( isset($form['3']) );

    $form['3'] = $field;

    $this->assertTrue( isset($form['3']) );

  }
}

?>
