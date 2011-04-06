<?php

require_once( '../prepare.php' );
require_once( '../element.php' );

require_once( 'simpletest/unit_tester.php' );

class day_test extends element_test
{
  public function testDefaultProperty()
  {
    $this->add();
    $this->assertEqual('day',$this->form['day']->name);
    $this->assertEqual('day',$this->form['day']->map);
  }

  public function testDefault()
  {
    $this->add('1')->default('now');
    $this->add('2')->default('11');

    $this->assertEqual(date('j',time()),$this->form['1']->default);
    $this->assertEqual(11,$this->form['2']->default);
  }

  public function testInstance()
  {
    $this->add('test','February', 2008);

    $this->assertEqual(2,$this->form['test']->month);
    $this->assertEqual(2008,$this->form['test']->year);
  }

  public function testRelation()
  {
    $this->form
      ->month('month')->default(2)
      ->year('year')->default(2008);

    $this->add()->ofMonth('month')->ofYear();

    $this->assertTrue( $this->form['day']->month instanceof HtmlFormMonth );
    $this->assertTrue( $this->form['day']->year instanceof HtmlFormYear );

    $this->assertEqual(2,(string)$this->form['day']->month);
    $this->assertEqual(2008,(string)$this->form['day']->year);
  }

  public function testClientValue()
  {
    $this->add();
    $this->form[$this->form->name]->value='submit';
    $this->form['day']->value = '11';

    $this->form->isValid();
    $this->assertEqual('11', $this->form['day']->value );
  }

  public function testValid()
  {
    $this->add('1',2,2008);
    $this->add('2',2,2007);
    $this->add('3');
    $this->form[$this->form->name]->value='submit';

    $this->form['1']->value = '29';
    $this->assertTrue($this->form->isValid());
    $this->assertFalse($this->form['1']->error);
    $this->form->unvalidate();
    $this->form['1']->value = '30';
    $this->assertFalse($this->form->isValid());
    $this->assertString($this->form['1']->error);

    $this->form['1']->value = null;
    $this->form->unvalidate();

    $this->form['2']->value = '28';
    $this->assertTrue($this->form->isValid());
    $this->assertFalse($this->form['2']->error);
    $this->form->unvalidate();
    $this->form['2']->value = '29';
    $this->assertFalse($this->form->isValid());
    $this->assertString($this->form['2']->error);

    $this->form['2']->value = null;
    $this->form->unvalidate();

    $this->form['3']->value = '-3';
    $this->assertFalse($this->form->isValid());
    $this->assertString($this->form['3']->error);
  }
}

?>
