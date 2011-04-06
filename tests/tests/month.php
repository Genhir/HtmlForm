<?php

require_once( '../prepare.php' );
require_once( '../element.php' );

require_once( 'simpletest/unit_tester.php' );

class month_test extends element_test
{
  public function testDefaultProperty()
  {
    $this->add();
    $this->assertEqual('month',$this->form['month']->name);
    $this->assertEqual('month',$this->form['month']->map);
  }

  public function testDefault()
  {
    $this->add('1')->default('now');
    $this->add('2')->default('11');

    $this->assertEqual(date('n',time()),$this->form['1']->default);
    $this->assertEqual(11,$this->form['2']->default);
  }

  public function testInstance()
  {
    $this->add('test',2007,29);

    $this->assertEqual(2007,$this->form['test']->year);
    $this->assertEqual(29,$this->form['test']->day);
  }

  public function testRelation()
  {
    $this->form
      ->day('day')->default('29')
      ->year('year')->default(2007);

    $this->add()->ofDay()->ofYear();

    $this->assertTrue( $this->form['month']->day instanceof HtmlFormDay );
    $this->assertTrue( $this->form['month']->year instanceof HtmlFormYear );

    $this->assertEqual(29,(string)$this->form['month']->day);
    $this->assertEqual(2007,(string)$this->form['month']->year);
  }

  public function testValid()
  {
    $this->add('1',2007,29);
    $this->form[$this->form->name]->value='submit';

    $this->form['1']->value = '1';
    $this->assertTrue($this->form->isValid());
    $this->assertFalse($this->form['1']->error);
    $this->form->unvalidate();
    $this->form['1']->value = '2';
    $this->assertFalse($this->form->isValid());
    $this->assertString($this->form['1']->error);
  }
}

?>
