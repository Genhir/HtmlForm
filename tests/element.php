<?php

require_once( 'simpletest/unit_tester.php' );

abstract class element_test extends UnitTestCase
{
  static protected $name = 0;
  protected $form = null;
  public function setUp()
  {
    $this->form = HtmlForm::hie('test'.++self::$name);
    $this->form->setGet()->submit();
  }
  public function tearDown()
  {
    unset($this->form);
  }

  public function add()
  {
    $args = func_get_args();
    call_user_func_array( array($this->form,$this->name()), $args );
    return $this->form;
  }

  public function name()
  {
    return substr(get_class($this),0,-5);
  }

  public function assertString( $value, $message = '%s' )
  {
    $dumper = new SimpleDumper();
    $message = sprintf(
      $message,
      '[' . $dumper->describeValue($value) . '] should be a string');
    return $this->assertTrue( is_string($value), $message );
  }

  public function assertArray( $value, $message = '%s' )
  {
    $dumper = new SimpleDumper();
    $message = sprintf(
      $message,
      '[' . $dumper->describeValue($value) . '] should be an array');
    return $this->assertTrue( is_array($value), $message );
  }

  public function testName()
  {
    $this->add('test');
    $this->assertEqual('test',$this->form['test']->name);
  }

  public function testRequired()
  {
    $this->add('1')->required();
    $this->add('2')->required(false);
    $this->add('3')->required(true);
    $this->add('4')->required('required');

    $this->assertString($this->form['1']->required);
    $this->assertFalse($this->form['2']->required);
    $this->assertString($this->form['3']->required);
    $this->assertString($this->form['4']->required);
  }

  public function testCheck()
  {
    $this->add('1')->check('value');
    $this->add('2')->check(array('class','function'));
    $this->add('3')->check('1','2','3');
    $this->add('4')->check('before',true);

    $this->assertArray($this->form['1']->check);
    $this->assertArray($this->form['2']->check);
    $this->assertArray($this->form['3']->check);
    $this->assertArray($this->form['4']->check);

    $this->assertEqual($this->form['1']->check[count($this->form['1']->check)-1],'value');
    $this->assertEqual($this->form['2']->check[count($this->form['2']->check)-1],array('class','function'));
    $this->assertEqual($this->form['3']->check[count($this->form['3']->check)-3],'1');
    $this->assertEqual($this->form['3']->check[count($this->form['3']->check)-2],'2');
    $this->assertEqual($this->form['3']->check[count($this->form['3']->check)-1],'3');
    $this->assertEqual($this->form['4']->check[0],'before');
  }

  public function testMap()
  {
    $this->add('test');
    $this->assertEqual('test',$this->form['test']->map);
  }

  public function testLabel()
  {
    $this->add('test');
    $this->add('1')->label('test');
    $this->add('insert')->label('test $map $name');

    $this->assertEqual('Test',$this->form['test']->label);
    $this->assertEqual('test',$this->form['1']->label);
    $this->assertEqual('test insert insert',$this->form['insert']->label);
  }

  public function testAlert()
  {
    $this->add('1')->alert('string');
    $this->add('2')->alert('add','key');
    $this->add('3')->alert(array('key'=>'value'));

    $this->assertString($this->form['1']->alert);
    $this->assertArray($this->form['2']->alert);
    $this->assertArray($this->form['3']->alert);

    $this->assertEqual('string',$this->form['1']->alert);
    $this->assertEqual('add',$this->form['2']->alert['key']);
    $this->assertEqual(array('key'=>'value'),$this->form['3']->alert);
  }

  public function testReadonly()
  {
    $this->add('1')->readonly();
    $this->add('2')->readonly(false);
    $this->add('3')->readonly(true);

    $this->assertTrue($this->form['1']->readonly);
    $this->assertFalse($this->form['2']->readonly);
    $this->assertTrue($this->form['3']->readonly);
  }

  public function testDefault()
  {
    $this->add('1')->default('test');
    $this->add('2')->default('test $map $name');
    $this->assertEqual('test',$this->form['1']->default);
    $this->assertEqual('test 2 2',$this->form['2']->default);
  }
}

?>
